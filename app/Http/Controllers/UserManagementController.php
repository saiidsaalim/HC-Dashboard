<?php

namespace App\Http\Controllers;

use App\Actions\Users\SyncUserWorkspaceAccess;
use App\Enums\UserRole;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use App\Services\Authorization\WorkspaceRegistry;
use App\Services\Users\StaffUserProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(
        private StaffUserProvisioningService $staffUserProvisioningService,
        private RolePermissionCache $rolePermissionCache,
        private WorkspaceRegistry $workspaceRegistry,
        private SyncUserWorkspaceAccess $syncUserWorkspaceAccess,
    ) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasPermission('users.view'), 403);
        $canManageUsers = $actor->hasPermission('users.create');
        $canManageAllRoles = $actor->hasPermission('users.manage-roles');
        $canApproveCandidates = $actor->hasPermission('users.approve-candidates');
        $canManageAccess = $actor->hasPermission('users.manage-access');
        $canManageWorkspaceAccess = $actor->isSuperAdmin();
        $canViewAllUsers = $actor->hasAnyPermission([
            'users.create',
            'users.update',
            'users.delete',
            'users.manage-roles',
            'users.manage-access',
        ]);
        $userRelations = ['employee'];

        if ($canManageWorkspaceAccess) {
            $userRelations[] = 'permissionOverrides';
        }

        $users = User::query()
            ->with($userRelations)
            ->when(! $canViewAllUsers, fn ($query) => $query->whereKey($actor->getKey()))
            ->orderBy('name')
            ->get();

        $candidateFilters = [
            'search' => trim((string) $request->input('candidate_search', '')),
            'department_id' => trim((string) $request->input('candidate_department_id', '')),
            'unit_id' => trim((string) $request->input('candidate_unit_id', '')),
            'position_id' => trim((string) $request->input('candidate_position_id', '')),
        ];
        $candidateQuery = $this->staffUserProvisioningService->candidateQuery()
            ->with(['organizationDepartment:id,name', 'unit:id,name', 'organizationPosition:id,name'])
            ->when($candidateFilters['search'] !== '', function ($query) use ($candidateFilters): void {
                $search = $candidateFilters['search'];
                $query->where(function ($query) use ($search): void {
                    foreach (['personal_number', 'sap', 'email'] as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->when($candidateFilters['department_id'] !== '', fn ($query) => $query->where('department_id', $candidateFilters['department_id']))
            ->when($candidateFilters['unit_id'] !== '', fn ($query) => $query->where('unit_id', $candidateFilters['unit_id']))
            ->when($candidateFilters['position_id'] !== '', fn ($query) => $query->where('position_id', $candidateFilters['position_id']))
            ->orderBy('personal_number')
            ->orderBy('sap');
        $candidates = $canApproveCandidates
            ? $candidateQuery->paginate(15, ['*'], 'candidate_page')->withQueryString()
            : null;
        $employees = $canApproveCandidates
            ? Employee::query()->with('user:id,employee_id')->orderBy('personal_number')->get()
            : collect();

        if ($candidates !== null) {
            $candidates->through(fn (Employee $employee): array => [
                'employee' => $employee,
                'issues' => $this->staffUserProvisioningService->candidateIssues($employee),
            ]);
        }

        $permissions = $canManageAccess
            ? Permission::query()->orderBy('sort_order')->get()
            : collect();
        $storedRolePermissions = $canManageAccess
            ? DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->get(['permission_role.role', 'permissions.key'])
                ->groupBy('role')
            : collect();
        $rolePermissionKeys = collect(UserRole::cases())->mapWithKeys(
            fn (UserRole $role): array => [
                $role->name => $role === UserRole::SUPER_ADMIN
                    ? $permissions->pluck('key')
                    : $storedRolePermissions->get($role->value, collect())->pluck('key'),
            ],
        );
        $workspaces = $canManageWorkspaceAccess
            ? collect($this->workspaceRegistry->assignable())->values()
            : collect();
        $userWorkspaceAccessKeys = $canManageWorkspaceAccess
            ? $users->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->workspaceAccessKeys()])
            : collect();

        return view('pages.konfigurasi-user', [
            'users' => $users,
            'employees' => $employees,
            'employeeCandidateIssues' => $canApproveCandidates
                ? $employees->whereNull('user')->mapWithKeys(fn (Employee $employee): array => [
                    $employee->id => $this->staffUserProvisioningService->candidateIssues($employee),
                ])
                : collect(),
            'candidates' => $candidates,
            'candidateBatchToken' => $canApproveCandidates
                ? $this->staffUserProvisioningService->candidateBatchToken()
                : null,
            'candidateFilters' => $candidateFilters,
            'candidateDepartments' => $canApproveCandidates
                ? Department::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'candidateUnits' => $canApproveCandidates
                ? Unit::query()->orderBy('name')->get(['id', 'department_id', 'name'])
                : collect(),
            'candidatePositions' => $canApproveCandidates
                ? Position::query()->orderBy('name')->get(['id', 'unit_id', 'name'])
                : collect(),
            'roles' => UserRole::cases(),
            'permissionGroups' => $permissions->groupBy('group'),
            'rolePermissionKeys' => $rolePermissionKeys,
            'canManageUsers' => $canManageUsers,
            'canManageAllRoles' => $canManageAllRoles,
            'canUpdateUsers' => $actor->hasPermission('users.update'),
            'canDeleteUsers' => $actor->hasPermission('users.delete'),
            'canApproveCandidates' => $canApproveCandidates,
            'canManageAccess' => $canManageAccess,
            'canManageWorkspaceAccess' => $canManageWorkspaceAccess,
            'workspaces' => $workspaces,
            'userWorkspaceAccessKeys' => $userWorkspaceAccessKeys,
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasPermission('users.manage-roles'), 403);
        abort_if($user->roleEnum() === UserRole::SUPER_ADMIN && $actor->roleEnum() !== UserRole::SUPER_ADMIN, 403);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
        ]);

        $newRole = UserRole::from($validated['role']);

        abort_if($actor->is($user) && $newRole !== UserRole::SUPER_ADMIN, 403, 'Anda tidak dapat menurunkan role akun sendiri.');

        $user->update(['role' => $newRole->value]);

        return back()->with('status', 'Role pengguna berhasil diperbarui.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $employeeId = array_key_exists('employee_id', $validated)
            ? $validated['employee_id']
            : $user->employee_id;
        $identity = $employeeId !== null
            ? $this->staffUserProvisioningService->identityFor(Employee::query()->findOrFail($employeeId), $user)
            : ['name' => $validated['name'], 'email' => $validated['email']];

        DB::transaction(function () use ($identity, $employeeId, $request, $user, $validated): void {
            $user->update([
                ...$identity,
                'employee_id' => $employeeId,
                ...($validated['password'] ?? null ? ['password' => Hash::make($validated['password'])] : []),
            ]);

            if ($request->workspaceAccessSubmitted()) {
                $this->syncUserWorkspaceAccess->execute($user, $validated['workspace_access'] ?? []);
            }
        }, 3);

        return back()->with(
            'status',
            $request->workspaceAccessSubmitted()
                ? 'Akses workspace pengguna berhasil diperbarui.'
                : 'Data pengguna berhasil diperbarui.',
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasPermission('users.delete'), 403);
        abort_if($user->roleEnum() === UserRole::SUPER_ADMIN && $actor->roleEnum() !== UserRole::SUPER_ADMIN, 403);
        abort_if($actor->is($user), 403, 'Anda tidak dapat menghapus akun sendiri.');

        $user->delete();

        return back()->with('status', 'Pengguna berhasil dihapus.');
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasPermission('users.create'), 403);

        $usesEmployeeIdentity = $request->filled('employee_id');

        $validated = $request->validate([
            'name' => [Rule::requiredIf(! $usesEmployeeIdentity), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf(! $usesEmployeeIdentity), 'nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => [Rule::requiredIf(! $usesEmployeeIdentity), 'nullable', 'string', 'min:8'],
            'role' => [Rule::requiredIf(! $usesEmployeeIdentity), 'nullable', 'string', Rule::in($this->manageableRoleValues($actor))],
            'employee_id' => [
                Rule::prohibitedIf(! $actor->hasPermission('users.approve-candidates')),
                'nullable',
                'integer',
                Rule::exists('employees', 'id'),
                Rule::unique('users', 'employee_id'),
            ],
        ]);

        $employee = isset($validated['employee_id'])
            ? Employee::query()->findOrFail($validated['employee_id'])
            : null;

        if ($employee !== null) {
            $user = $this->staffUserProvisioningService->provision($employee, $actor);

            return back()->with('status', "User Staff {$user->name} berhasil dibuat.");
        }

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'employee_id' => null,
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'Pengguna baru berhasil ditambahkan.');
    }

    public function approveCandidate(Request $request, Employee $employee): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasPermission('users.approve-candidates'), 403);

        $user = $this->staffUserProvisioningService->provision($employee, $actor);

        return back()->with('status', "User Staff {$user->name} berhasil dibuat.");
    }

    public function approveAllCandidates(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasPermission('users.approve-candidates'), 403);

        $validated = $request->validate([
            'candidate_batch_token' => ['required', 'string', 'size:64'],
        ]);

        $created = $this->staffUserProvisioningService->provisionAll($actor, $validated['candidate_batch_token']);

        return back()->with('status', "{$created} calon User berhasil disetujui.");
    }

    public function updateRolePermissions(UpdateRolePermissionsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $submittedMatrix = $validated['permissions'] ?? [];

        DB::transaction(function () use ($submittedMatrix): void {
            $permissions = Permission::query()->lockForUpdate()->get()->keyBy('key');
            $now = now();

            foreach (UserRole::cases() as $role) {
                $permissionKeys = $role === UserRole::SUPER_ADMIN
                    ? $permissions->keys()->all()
                    : array_values($submittedMatrix[$role->name] ?? []);
                $permissionIds = collect($permissionKeys)
                    ->map(fn (string $key): ?int => $permissions->get($key)?->id)
                    ->filter()
                    ->values();

                if ($permissionIds->count() !== count($permissionKeys)) {
                    throw ValidationException::withMessages([
                        'permissions' => 'Terdapat hak akses yang sudah tidak tersedia.',
                    ]);
                }

                DB::table('permission_role')->where('role', $role->value)->delete();

                DB::table('permission_role')->insert(
                    $permissionIds->map(fn (int $permissionId): array => [
                        'permission_id' => $permissionId,
                        'role' => $role->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            }
        });

        $this->rolePermissionCache->clear();

        return back()->with('status', 'Hak akses role berhasil diperbarui.');
    }

    /** @return array<int, string> */
    private function manageableRoleValues(User $actor): array
    {
        $roles = $actor->roleEnum()->canManageAllRoles()
            ? UserRole::cases()
            : [UserRole::STAFF, UserRole::MEMBER];

        return array_map(fn (UserRole $role): string => $role->value, $roles);
    }
}
