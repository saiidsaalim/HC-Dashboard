<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canManageRbac(), 403);

        return view('pages.konfigurasi-user', [
            'users' => User::query()->orderBy('name')->get(),
            'roles' => UserRole::cases(),
            'permissions' => [
                ['label' => 'Data operasional', 'super_admin' => 'Kelola penuh', 'admin_manager' => 'Baca, buat, ubah', 'staff_member' => 'Data sendiri dan tugas'],
                ['label' => 'Data pengguna', 'super_admin' => 'Kelola penuh', 'admin_manager' => 'Staff dan Member', 'staff_member' => 'Data sendiri'],
                ['label' => 'Log sistem', 'super_admin' => 'Baca, ubah, hapus', 'admin_manager' => 'Baca', 'staff_member' => 'Tidak tersedia'],
                ['label' => 'Konfigurasi lanjutan', 'super_admin' => 'Kelola penuh', 'admin_manager' => 'Baca', 'staff_member' => 'Tidak tersedia'],
            ],
            'canManageAllRoles' => $actor->roleEnum()->canManageAllRoles(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->roleEnum()->canManageAllRoles(), 403);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
        ]);

        $newRole = UserRole::from($validated['role']);

        abort_if($actor->is($user) && $newRole !== UserRole::SUPER_ADMIN, 403, 'Anda tidak dapat menurunkan role akun sendiri.');

        $user->update(['role' => $newRole->value]);

        return back()->with('status', 'Role pengguna berhasil diperbarui.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->roleEnum()->canManageAllRoles(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            ...($validated['password'] ?? null ? ['password' => Hash::make($validated['password'])] : []),
        ]);

        return back()->with('status', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->roleEnum()->canManageAllRoles(), 403);
        abort_if($actor->is($user), 403, 'Anda tidak dapat menghapus akun sendiri.');

        $user->delete();

        return back()->with('status', 'Pengguna berhasil dihapus.');
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canManageRbac(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:'.implode(',', $this->manageableRoleValues($actor))],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'Pengguna baru berhasil ditambahkan.');
    }

    public function import(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canManageRbac(), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt'],
        ]);

        try {
            $rows = $this->readSpreadsheet($request->file('file'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $usersToCreate = [];
        $skipped = 0;

        foreach ($rows as $rowNumber => $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $password = (string) ($row['password'] ?? '');

            if ($email === '' && $password === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                return back()->withErrors(['file' => "Baris {$rowNumber} harus memiliki email valid dan password minimal 8 karakter."]);
            }

            if (User::query()->where('email', $email)->exists() || collect($usersToCreate)->contains('email', $email)) {
                $skipped++;

                continue;
            }

            $usersToCreate[] = [
                'name' => str($email)->before('@')->replace(['.', '_', '-'], ' ')->title()->toString(),
                'email' => $email,
                'role' => UserRole::STAFF->value,
                'password' => Hash::make($password),
            ];
        }

        DB::transaction(function () use ($usersToCreate): void {
            foreach ($usersToCreate as $user) {
                User::create($user);
            }
        });

        $created = count($usersToCreate);

        return back()->with('status', "Import selesai: {$created} user ditambahkan, {$skipped} email duplikat dilewati.");
    }

    /** @return array<int, string> */
    private function manageableRoleValues(User $actor): array
    {
        $roles = $actor->roleEnum()->canManageAllRoles()
            ? UserRole::cases()
            : [UserRole::STAFF, UserRole::MEMBER];

        return array_map(fn (UserRole $role): string => $role->value, $roles);
    }

    /** @return array<int, array<string, string>> */
    private function readSpreadsheet(UploadedFile $file): array
    {
        if (in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            return $this->readCsv($file->getRealPath());
        }

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('File Excel tidak dapat dibuka.');
        }

        $sharedStrings = [];
        if (($sharedXml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sharedDocument = simplexml_load_string($sharedXml, SimpleXMLElement::class, LIBXML_NONET);
            foreach ($sharedDocument->si as $sharedItem) {
                $sharedStrings[] = (string) ($sharedItem->t ?? implode('', iterator_to_array($sharedItem->r->t ?? [])));
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('Sheet pertama pada file Excel tidak ditemukan.');
        }

        $document = simplexml_load_string($sheetXml, SimpleXMLElement::class, LIBXML_NONET);
        $rows = [];
        foreach ($document->sheetData->row as $sheetRow) {
            $row = [];
            foreach ($sheetRow->c as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference);
                $type = (string) $cell['t'];
                $value = $type === 'inlineStr'
                    ? (string) ($cell->is->t ?? '')
                    : (string) ($cell->v ?? '');
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $row[strtolower($column)] = $value;
            }
            $rows[] = $row;
        }

        return $this->mapSpreadsheetRows($rows);
    }

    /** @return array<int, array<string, string>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('File tidak dapat dibaca.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(static fn (?string $value): string => trim((string) $value), $row);
        }
        fclose($handle);

        if ($rows === []) {
            throw new RuntimeException('File tidak memiliki data.');
        }

        $headers = array_map(fn (string $header): string => strtolower(trim($header, " \t\r\n\xEF\xBB\xBF")), array_shift($rows));
        $requiredHeaders = ['email', 'password'];
        if (array_diff($requiredHeaders, $headers) !== []) {
            throw new RuntimeException('Format file harus memiliki kolom email dan password.');
        }

        return array_values(array_filter(array_map(
            fn (array $row): array => array_combine($headers, array_slice(array_pad($row, count($headers), ''), 0, count($headers))),
            $rows,
        ), fn (array $row): bool => count(array_filter($row, fn (string $value): bool => trim($value) !== '')) > 0));
    }

    /** @param array<int, array<string, string>> $rows */
    private function mapSpreadsheetRows(array $rows): array
    {
        if ($rows === []) {
            throw new RuntimeException('File tidak memiliki data.');
        }

        $headerKeys = array_keys($rows[0]);
        $headers = array_map(fn (string $header): string => strtolower(trim($header, " \t\r\n\xEF\xBB\xBF")), array_values($rows[0]));
        $requiredHeaders = ['email', 'password'];
        if (array_diff($requiredHeaders, $headers) !== []) {
            throw new RuntimeException('Format file harus memiliki kolom email dan password.');
        }

        $columns = [];
        foreach ($requiredHeaders as $header) {
            $columns[$header] = $headerKeys[array_search($header, $headers, true)];
        }

        $mapped = [];
        foreach (array_slice($rows, 1) as $row) {
            $mapped[] = array_map(fn (string $column): string => (string) ($row[$column] ?? ''), $columns);
        }

        return $mapped;
    }
}
