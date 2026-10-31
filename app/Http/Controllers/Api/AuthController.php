<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate user and issue Sanctum token.
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with([
            'roles.permissions',
            'permissions',
            'employee.department',
            'employee.branch',
            'employee.jobPosition.jobGrade',
            'employee.manager.user',
            'employee.activeContract',
        ])->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak cocok dengan data kami.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.',
            ], 403);
        }

        // Revoke old tokens if single session preferred or generate new
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * Get current authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'roles.permissions',
            'permissions',
            'employee.department',
            'employee.branch',
            'employee.jobPosition.jobGrade',
            'employee.manager.user',
            'employee.activeContract',
        ]);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Log the user out (Revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Update current authenticated user avatar photo.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'max:1024'],
        ]);

        $user = $request->user();
        $avatarUrl = app(\App\Services\CloudStorageService::class)->upload($request->file('avatar'), 'avatars');
        $user->avatar_url = $avatarUrl;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update current authenticated user profile details (name, email, phone, avatar).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'file', 'image', 'max:1024'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email sudah digunakan oleh pengguna lain.',
            'avatar.max' => 'Ukuran foto maksimal 1 MB.',
            'avatar.image' => 'File harus berupa gambar valid.',
        ]);

        if ($request->hasFile('avatar')) {
            $avatarUrl = app(\App\Services\CloudStorageService::class)->upload($request->file('avatar'), 'avatars');
            $user->avatar_url = $avatarUrl;
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->save();

        $user->load([
            'roles.permissions',
            'permissions',
            'employee.department',
            'employee.branch',
            'employee.jobPosition.jobGrade',
            'employee.manager.user',
            'employee.activeContract',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil akun berhasil diperbarui.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update current authenticated user password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:6',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak sesuai.',
            'password.regex' => 'Kata sandi baru harus mengandung huruf besar (A-Z) dan angka (0-9).',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Kata sandi saat ini yang Anda masukkan salah.'],
            ]);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi akun Anda berhasil diubah.',
        ]);
    }
}
