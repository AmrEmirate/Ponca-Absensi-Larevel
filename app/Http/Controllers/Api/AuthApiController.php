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
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:4', 'max:255'],
        ]);

        $loginInput = trim($validated['email']);
        $password = $validated['password'];

        // Cari user berdasarkan email, NIK, atau nama
        $user = User::where('email', $loginInput)
            ->orWhere('nik', $loginInput)
            ->orWhere('nama', $loginInput)
            ->first();

        if (!$user) {
            Log::warning('Login failed: user not found', ['input' => $loginInput, 'ip' => $request->ip()]);
            return response()->json(['error' => 'Email/NIK/Username atau kata sandi tidak sesuai.'], 401);
        }

        if (!$user->is_active || ($user->status && $user->status !== 'Active')) {
            Log::warning('Blocked login on inactive account', ['input' => $loginInput, 'ip' => $request->ip()]);
            return response()->json(['error' => 'Akun Anda tidak aktif. Silakan hubungi Administrator.'], 403);
        }

        if (!Hash::check($password, $user->password)) {
            Log::warning('Failed login attempt: invalid password', ['input' => $loginInput, 'ip' => $request->ip()]);
            return response()->json(['error' => 'Email/NIK/Username atau kata sandi tidak sesuai.'], 401);
        }

        // Cek Hak Akses POS Ponca Saller (Hanya Admin dan Karyawan Saller/Sales/Kasir)
        $roleStr = strtoupper(trim((string)$user->role));
        $jabatanLower = strtolower(trim((string)($user->jabatan ?? '')));
        $isSallerOrAdmin = in_array($roleStr, ['ADMIN', 'SALLER', 'SALES', 'SELLER'])
            || str_contains($jabatanLower, 'admin')
            || str_contains($jabatanLower, 'saller')
            || str_contains($jabatanLower, 'seller')
            || str_contains($jabatanLower, 'sales')
            || str_contains($jabatanLower, 'kasir');

        if (!$isSallerOrAdmin) {
            Log::warning('Login rejected for non-saller employee', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'jabatan' => $user->jabatan,
                'ip' => $request->ip(),
            ]);
            $jabatanLabel = $user->jabatan ?: ($roleStr === 'KARYAWAN' ? 'Karyawan Tetap' : 'Karyawan');
            return response()->json([
                'error' => "Akses ditolak: Akun Anda ({$jabatanLabel}) tidak memiliki izin akses ke POS Ponca Saller. Hanya Admin dan Karyawan Saller (Sales/Kasir) yang dapat login."
            ], 403);
        }

        Auth::login($user, $request->boolean('remember'));
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $user->update(['last_login_at' => now()]);

        Log::info('Login POS berhasil', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'jabatan' => $user->jabatan,
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
        $displayName = $user->nama ?: ($user->name ?: 'User');
        return [
            'id' => (string) $user->id,
            'name' => $displayName,
            'email' => $user->email,
            'role' => $user->role,
            'location' => $user->location ?? 'Jakarta Selatan',
            'avatar' => $user->foto_profil ?: ($user->avatar ?: strtoupper(substr($displayName, 0, 2))),
            'status' => $user->status ?? ($user->is_active ? 'Active' : 'Inactive'),
            'mustChangePassword' => (bool) $user->must_change_password,
            'lastLogin' => $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never',
        ];
    }
}
