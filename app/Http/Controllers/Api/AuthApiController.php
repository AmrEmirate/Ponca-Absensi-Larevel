<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && ($user->status !== 'Active' || $user->is_active === false)) {
            Log::warning('Blocked login on inactive account', ['email' => $credentials['email'], 'ip' => $request->ip()]);
            return response()->json(['error' => 'Akun Anda tidak aktif. Hubungi Administrator.'], 403);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            Log::warning('Failed login attempt', ['email' => $credentials['email'], 'ip' => $request->ip()]);
            return response()->json(['error' => 'Email atau kata sandi tidak sesuai.'], 401);
        }

        $request->session()->regenerate();
        $user = Auth::user();
        $user->update(['last_login_at' => now()]);

        Log::info('Login berhasil', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip' => $request->ip(),
        ]);

        $secret = config('services.jwt.secret') ?? env('JWT_SECRET', 'ce1ca04cea4e29159d5e1696054e18546892aec1669eb7f791f9b172f72aa3ab500bb61ac6ea26845c553b5acfc1c2417dd2191a3c9a7957d91f07931eee7359');
        $payload = [
            'id' => $user->id,
            'nik' => $user->nik,
            'role' => $user->role,
            'iat' => time(),
            'exp' => time() + (7 * 24 * 60 * 60), // 7 days
        ];
        $token = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

        return response()->json([
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        Log::info('User logout', ['user_id' => $user?->id, 'ip' => $request->ip()]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['error' => 'Kata sandi saat ini tidak sesuai.'], 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
            'must_change_password' => false,
        ]);

        Log::info('User changed password', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui!',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name ?? $user->nama,
            'email' => $user->email,
            'role' => $user->role,
            'location' => $user->location ?? 'Jakarta Selatan',
            'avatar' => $user->avatar ?? strtoupper(substr($user->name ?? $user->nama, 0, 2)),
            'status' => $user->status ?? ($user->is_active ? 'Active' : 'Inactive'),
            'mustChangePassword' => (bool) $user->must_change_password,
            'lastLogin' => $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never',
        ];
    }
}
