<?php

namespace App\Http\Controllers;

use App\Models\LessonSchedule;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DeviceDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $setting = SchoolSetting::getSetting();

        return view('auth.login', compact('setting'));
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($loginField, $credentials['login'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withInput($request->only('login'))
                ->withErrors(['login' => 'Username atau password yang dimasukkan tidak sesuai.']);
        }

        if (! $user->is_active) {
            return back()->withErrors(['login' => 'Akun Anda dinonaktifkan. Silakan hubungi Administrator.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        ActivityLogger::log('login', "Pengguna {$user->name} ({$user->role?->name}) berhasil login ke dalam sistem.", $user);

        return redirect()->intended(route('dashboard'))
            ->with('success', "Selamat datang, {$user->name}!");
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($user = Auth::user()) {
            ActivityLogger::log('logout', "Pengguna {$user->name} keluar (logout) dari sistem.", $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Anda telah berhasil keluar dari sistem.');
    }

    public function profile(): View
    {
        $user = Auth::user();

        $userSubjects = LessonSchedule::where('teacher_id', $user->id)
            ->with('subject')
            ->get()
            ->pluck('subject.name')
            ->unique()
            ->filter()
            ->values();

        if ($userSubjects->isEmpty() && $user->subject) {
            $userSubjects = collect(explode(',', $user->subject))->map(fn ($s) => trim($s))->filter()->values();
        }

        if (DeviceDetector::isMobile()) {
            return view('mobile.profile', compact('user', 'userSubjects'));
        }

        return view('web.profile', compact('user', 'userSubjects'));
    }

    public function editProfile(): View
    {
        $user = Auth::user();

        if (DeviceDetector::isMobile()) {
            return view('mobile.profile_edit', compact('user'));
        }

        return view('web.profile', compact('user'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'nip' => ['nullable', 'string', 'max:50'],
            'nuptk' => ['nullable', 'string', 'max:50'],
            'telegram_chat_id' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->filled('avatar_cropped_base64')) {
            $base64 = $request->input('avatar_cropped_base64');
            if (preg_match('/^data:image\/(\w+);base64,/', $base64)) {
                $imageData = substr($base64, strpos($base64, ',') + 1);
                $decodedData = base64_decode($imageData);
                if ($decodedData !== false) {
                    if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                        Storage::disk('public')->delete($user->avatar);
                    }
                    $filename = 'avatars/'.Str::uuid().'.jpg';
                    Storage::disk('public')->put($filename, $decodedData);
                    $data['avatar'] = $filename;
                }
            }
        }

        $user->update($data);

        return redirect()->route('profile')->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = Auth::user();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
