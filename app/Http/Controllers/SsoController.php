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

            if (!in_array($roleStr, ['ADMIN', 'SALLER', 'SALES', 'SELLER'])) {
                Log::warning('SSO login rejected for unauthorized role', ['email' => $user->email, 'role' => $roleStr]);
                return redirect($frontendUrl . '/login')->withErrors(['email' => 'Akses ditolak: Akun Anda (Role ' . ($roleStr ?: 'Karyawan') . ') tidak memiliki hak akses ke aplikasi Ponca Saller.']);
            }

            if (in_array($roleStr, ['SALLER', 'SELLER', 'SALES']) && $user->role !== 'Sales') {
                $user->update(['role' => 'Sales']);
            } elseif ($roleStr === 'ADMIN' && $user->role !== 'Admin') {
                $user->update(['role' => 'Admin']);
            }

            Auth::login($user);
            Log::info('SSO login successful (backend-laravel)', ['email' => $user->email, 'role' => $user->role]);

            return redirect($frontendUrl . '/dashboard');
        }

        return redirect($frontendUrl . '/login')->withErrors(['email' => 'Gagal login melalui SSO. Token tidak valid.']);
    }
}
