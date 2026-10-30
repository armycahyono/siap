<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MobileAuthController extends Controller
{
    /**
     * Handle user authentication for SIAP Mobile.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $loginInput = trim($credentials['login']);
        $password = $credentials['password'];

        $user = User::where('email', $loginInput)
            ->orWhere('username', $loginInput)
            ->orWhere('nip', $loginInput)
            ->orWhere('nuptk', $loginInput)
            ->orWhere('nigs', $loginInput)
            ->orWhere('nik', $loginInput)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identitas akun atau kata sandi tidak sesuai.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi Administrator.',
            ], 403);
        }

        Auth::login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        ActivityLogger::log('login', "Pengguna {$user->name} login melalui SIAP Mobile.", $user);

        return response()->json([
            'success' => true,
            'message' => "Selamat datang, {$user->name}!",
            'data' => [
                'user' => $this->formatUserData($user),
            ],
        ]);
    }

    /**
     * Get profile of currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'authenticated' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'authenticated' => true,
            'data' => [
                'user' => $this->formatUserData($user),
            ],
        ]);
    }

    /**
     * Log out current user session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user) {
            ActivityLogger::log('logout', "Pengguna {$user->name} keluar dari SIAP Mobile.", $user);
        }

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Anda telah berhasil keluar dari sistem.',
        ]);
    }

    /**
     * Update user profile via API.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'nip' => ['nullable', 'string', 'max:50'],
            'nuptk' => ['nullable', 'string', 'max:50'],
            'telegram_chat_id' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'avatar_cropped_base64' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } elseif (! empty($validated['avatar_cropped_base64'])) {
            $base64 = $validated['avatar_cropped_base64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64)) {
                $imageData = substr($base64, strpos($base64, ',') + 1);
                $decodedData = base64_decode($imageData);
                if ($decodedData !== false) {
                    if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                        Storage::disk('public')->delete($user->avatar);
                    }
                    $filename = 'avatars/'.Str::uuid().'.jpg';
                    Storage::disk('public')->put($filename, $decodedData);
                    $validated['avatar'] = $filename;
                }
            }
            unset($validated['avatar_cropped_base64']);
        }

        $user->update($validated);

        try {
            ActivityLogger::log(
                action: 'update_profile',
                description: "Memperbarui data profil pengguna ({$user->name}) melalui SIAP Mobile.",
                subject: $user,
                properties: ['source' => 'Mobile', 'updated_fields' => array_keys($validated)],
                user: $user
            );
        } catch (\Throwable) {
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => [
                'user' => $this->formatUserData($user->fresh()),
            ],
        ]);
    }

    /**
     * Update user password via API.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        try {
            ActivityLogger::log(
                action: 'update_password',
                description: 'Memperbarui kata sandi akun melalui SIAP Mobile.',
                subject: $user,
                properties: ['source' => 'Mobile'],
                user: $user
            );
        } catch (\Throwable) {
        }

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diperbarui.',
        ]);
    }

    /**
     * Format user payload for consistent mobile consumption.
     */
    protected function formatUserData(User $user): array
    {
        $avatarUrl = $user->avatar_url;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'short_name' => $user->short_name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'nip' => $user->nip,
            'nuptk' => $user->nuptk,
            'nigs' => $user->nigs,
            'position' => $user->position,
            'role' => $user->role?->name ?? 'Pengguna',
            'role_key' => $user->role?->name ? Str::slug($user->role->name) : 'guru',
            'avatar_url' => $avatarUrl,
            'is_teacher' => $user->isTeacher(),
            'is_principal' => $user->isPrincipal(),
            'is_waka_kurikulum' => $user->isWakaKurikulum(),
            'is_waka_kesiswaan' => $user->isWakaKesiswaan(),
            'is_admin' => $user->isSuperAdmin() || $user->isSystemAdmin() || $user->hasRole(['admin', 'superadmin', 'administrator']),
            'is_piket_today' => $user->isPiketOfficerToday(),
            'has_homeroom' => $user->isHomeroomTeacher(),
            'can_manage_discipline' => $user->canManageDiscipline(),
            'can_operate_bk' => $user->canOperateBk(),
            'can_access_visitations' => $user->canAccessVisitations(),
        ];
    }
}
