<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    private const ALLOWED_ROLES = [
        'Superadmin',
        'HR Manager',
        'HR Staff',
        'Manager',
        'Employee',
    ];

    /**
     * List all user accounts with their roles and linked employee profile.
     */
    public function index(): JsonResponse
    {
        $users = User::with(['roles', 'employee.department', 'employee.jobPosition', 'employee.branch'])
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (User $user) => $this->formatUser($user));

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('id', 'asc')
            ->pluck('name')
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Daftar akun pengguna berhasil dimuat.',
            'data' => [
                'users' => $users,
                'available_roles' => $roles->isNotEmpty() ? $roles : collect(self::ALLOWED_ROLES),
            ],
        ]);
    }

    /**
     * Create a new user account and assign a role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', Rule::in(self::ALLOWED_ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => $validated['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);

        Role::findOrCreate($validated['role'], 'web');
        $user->syncRoles([$validated['role']]);

        $user->load(['roles', 'employee.department', 'employee.jobPosition', 'employee.branch']);

        return response()->json([
            'success' => true,
            'message' => 'Akun pengguna berhasil dibuat.',
            'data' => $this->formatUser($user),
        ], 201);
    }

    /**
     * Update user account details, role assignment, active status, or password.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['sometimes', 'required', 'string', Rule::in(self::ALLOWED_ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $currentUser = $request->user();

        if ($currentUser && $currentUser->id === $user->id) {
            if (array_key_exists('is_active', $validated) && ! $validated['is_active']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
                ], 422);
            }

            if (isset($validated['role']) && $validated['role'] !== 'Superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak dapat mencabut role Superadmin dari akun Anda sendiri.',
                ], 422);
            }
        }

        $updates = [];
        foreach (['name', 'email', 'phone', 'is_active'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }

        if (! empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }

        if (! empty($updates)) {
            $user->update($updates);
        }

        if (isset($validated['role'])) {
            Role::findOrCreate($validated['role'], 'web');
            $user->syncRoles([$validated['role']]);
        }

        if (array_key_exists('is_active', $validated) && ! $validated['is_active']) {
            $user->tokens()->delete();
        }

        $user->load(['roles', 'employee.department', 'employee.jobPosition', 'employee.branch']);

        return response()->json([
            'success' => true,
            'message' => 'Data dan hak akses pengguna berhasil diperbarui.',
            'data' => $this->formatUser($user),
        ]);
    }

    /**
     * Format user model for API response.
     *
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        $roleNames = $user->roles->pluck('name')->values()->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'roles' => $roleNames,
            'primary_role' => $roleNames[0] ?? 'Employee',
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'employee_code' => $user->employee->employee_code,
                'full_name' => $user->employee->full_name,
                'department' => $user->employee->department?->name,
                'job_position' => $user->employee->jobPosition?->title,
                'branch' => $user->employee->branch?->name,
            ] : null,
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
