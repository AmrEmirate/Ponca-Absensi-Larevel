<?php

namespace App\Http\Controllers;

use App\Helpers\JwtHelper;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SsoController extends Controller
{
    public function login(Request $request)
    {
        $token = $request->query('token');
        $frontendUrl = env('FRONTEND_URL', 'https://pos.poncafood.com');

        if (!$token) {
            return redirect($frontendUrl . '/login')->withErrors(['email' => 'Token SSO tidak ditemukan.']);
        }

        $user = null;
        $secret = env('JWT_SECRET', 'ce1ca04cea4e29159d5e1696054e18546892aec1669eb7f791f9b172f72aa3ab500bb61ac6ea26845c553b5acfc1c2417dd2191a3c9a7957d91f07931eee7359');

        try {
            $decoded = JwtHelper::decode($token, $secret);
            if ($decoded && isset($decoded->id)) {
                $user = User::find($decoded->id);
            }
        } catch (\Exception $e) {
            Log::warning('SSO JWT decode exception', ['error' => $e->getMessage()]);
        }

        if (!$user && str_contains($token, '.')) {
            try {
                $parts = explode('.', $token);
                if (count($parts) === 3) {
                    $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
                    $payload = json_decode($payloadJson, true);
                    $userId = $payload['id'] ?? null;
                    if ($userId) {
                        $user = User::find($userId);
                    }
                }
            } catch (\Exception $e) {
                Log::error('SSO fallback exception', ['error' => $e->getMessage()]);
            }
        }

        if ($user) {
            $roleStr = strtoupper(trim((string)$user->role));
            $jabatanLower = strtolower(trim((string)($user->jabatan ?? '')));
            $isSallerOrAdmin = in_array($roleStr, ['ADMIN', 'SALLER', 'SALES', 'SELLER'])
                || str_contains($jabatanLower, 'admin')
                || str_contains($jabatanLower, 'saller')
                || str_contains($jabatanLower, 'seller')
                || str_contains($jabatanLower, 'sales')
                || str_contains($jabatanLower, 'kasir');

            if (!$isSallerOrAdmin) {
                Log::warning('SSO login rejected for non-saller role', [
                    'email' => $user->email,
                    'role' => $roleStr,
                    'jabatan' => $user->jabatan,
                ]);
                $jabatanLabel = $user->jabatan ?: ($roleStr === 'KARYAWAN' ? 'Karyawan Tetap' : 'Karyawan');
                return redirect($frontendUrl . '/login')->withErrors([
                    'email' => "Akses ditolak: Akun Anda ({$jabatanLabel}) tidak memiliki izin akses ke POS Ponca Saller. Hanya Admin dan Karyawan Saller (Sales/Kasir) yang dapat login."
                ]);
            }

            Auth::login($user);
            Log::info('SSO login successful (backend-laravel)', ['email' => $user->email, 'role' => $user->role]);

            return redirect($frontendUrl . '/dashboard');
        }

        return redirect($frontendUrl . '/login')->withErrors(['email' => 'Gagal login melalui SSO. Token tidak valid.']);
    }
}
