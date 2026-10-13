<?php

namespace App\Services\Users;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StaffUserProvisioningService
{
    public function provision(Employee $employee, User $actor): User
    {
        return DB::transaction(function () use ($employee, $actor): User {
            $lockedEmployee = Employee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedEmployee->user()->exists()) {
                throw ValidationException::withMessages([
                    'candidate' => 'Employee ini sudah mempunyai User.',
                ]);
            }

            return $this->createUsers(collect([$lockedEmployee]), $actor)->firstOrFail();
        });
    }

    public function provisionAll(User $actor, string $expectedBatchToken): int
    {
        return DB::transaction(function () use ($actor, $expectedBatchToken): int {
            $employees = $this->candidateQuery()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($employees->isEmpty()) {
                throw ValidationException::withMessages([
                    'candidates' => 'Tidak ada calon User yang tersedia.',
                ]);
            }

            if (! hash_equals($expectedBatchToken, $this->batchToken($employees))) {
                throw ValidationException::withMessages([
                    'candidates' => 'Data calon User telah berubah. Muat ulang halaman sebelum menyetujui semua calon.',
                ]);
            }

            return $this->createUsers($employees, $actor)->count();
        });
    }

    /** @return Builder<Employee> */
    public function candidateQuery(): Builder
    {
        return Employee::query()->whereDoesntHave('user');
    }

    public function candidateBatchToken(): string
    {
        return $this->batchToken(
            $this->candidateQuery()
                ->orderBy('id')
                ->get(['id', 'personal_number', 'email', 'updated_at']),
        );
    }

    public function temporaryPassword(): string
    {
        $temporaryPassword = config('user_provisioning.temporary_password');

        if (! is_string($temporaryPassword) || $temporaryPassword === '') {
            throw new RuntimeException('STAFF_TEMPORARY_PASSWORD belum dikonfigurasi.');
        }

        return $temporaryPassword;
    }

    public function synchronizeLinkedUser(Employee $employee): ?User
    {
        $user = $employee->user()->lockForUpdate()->first();

        if ($user === null) {
            return null;
        }

        $user->update([
            ...$this->identityFor($employee, $user),
            'role' => UserRole::STAFF->value,
        ]);

        return $user;
    }

    /** @return array{name: string, email: string} */
    public function identityFor(Employee $employee, ?User $ignoredUser = null): array
    {
        $name = $this->normalizePersonalNumber($employee->personal_number);
        $email = $this->normalizeEmail($employee->email);
        $issues = $this->validationIssues($employee, $ignoredUser, $name, $email);

        if ($issues !== []) {
            throw ValidationException::withMessages([
                'candidate' => implode(' ', $issues),
            ]);
        }

        return compact('name', 'email');
    }

    /** @return array<int, string> */
    public function candidateIssues(Employee $employee, ?User $ignoredUser = null): array
    {
        return $this->validationIssues(
            $employee,
            $ignoredUser,
            $this->normalizePersonalNumber($employee->personal_number),
            $this->normalizeEmail($employee->email),
        );
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, User>
     */
    private function createUsers(Collection $employees, User $actor): Collection
    {
        $preparedCandidates = [];
        $candidateEmails = [];

        foreach ($employees as $employee) {
            try {
                $identity = $this->identityFor($employee);
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();

                throw ValidationException::withMessages([
                    'candidates' => "Employee {$employee->sap}: {$message}",
                ]);
            }

            if (isset($candidateEmails[$identity['email']])) {
                throw ValidationException::withMessages([
                    'candidates' => "Email {$identity['email']} digunakan oleh lebih dari satu calon User.",
                ]);
            }

            $candidateEmails[$identity['email']] = true;
            $preparedCandidates[] = compact('employee', 'identity');
        }

        $users = collect();
        foreach ($preparedCandidates as $candidate) {
            $users->push(User::query()->create([
                ...$candidate['identity'],
                'employee_id' => $candidate['employee']->id,
                'role' => UserRole::STAFF->value,
                'password' => Hash::make($this->temporaryPassword()),
                'must_change_password' => true,
            ]));
        }

        Log::info('Staff users provisioned from employees.', [
            'actor_id' => $actor->id,
            'employee_ids' => $employees->pluck('id')->all(),
            'user_ids' => $users->pluck('id')->all(),
        ]);

        return $users;
    }

    /** @return array<int, string> */
    private function validationIssues(
        Employee $employee,
        ?User $ignoredUser,
        string $name,
        string $email,
    ): array {
        $issues = [];

        if ($name === '') {
            $issues[] = 'Personal Number Employee belum tersedia.';
        }

        if ($email === '') {
            $issues[] = 'email Employee belum tersedia.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $issues[] = 'email Employee tidak valid.';
        } elseif (User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->when($ignoredUser !== null, fn ($query) => $query->whereKeyNot($ignoredUser->getKey()))
            ->exists()) {
            $issues[] = 'email Employee sudah digunakan oleh User lain.';
        }

        if ($employee->relationLoaded('user')) {
            $linkedUser = $employee->getRelation('user');
            if ($linkedUser !== null && ! $linkedUser->is($ignoredUser)) {
                $issues[] = 'Employee sudah mempunyai User.';
            }
        }

        return $issues;
    }

    private function normalizePersonalNumber(mixed $personalNumber): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $personalNumber)) ?? '';
    }

    private function normalizeEmail(mixed $email): string
    {
        return Str::lower(trim((string) $email));
    }

    /** @param Collection<int, Employee> $employees */
    private function batchToken(Collection $employees): string
    {
        return hash('sha256', $employees->map(fn (Employee $employee): array => [
            'id' => $employee->getKey(),
            'personal_number' => $employee->getRawOriginal('personal_number'),
            'email' => $employee->getRawOriginal('email'),
            'updated_at' => $employee->getRawOriginal('updated_at'),
        ])->values()->toJson());
    }
}
