<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;

class JwtAuth
{
    /**
     * Handle an incoming request.
     * Validates Bearer JWT token and attaches user to request.
     */
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Token otorisasi tidak ditemukan'], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $secret = config('services.jwt.secret');
            if (empty($secret)) {
                return response()->json(['error' => 'Konfigurasi kunci otorisasi server tidak valid'], 500);
            }
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            if (!isset($decoded->id) || !isset($decoded->role)) {
                return response()->json(['error' => 'Payload token otorisasi tidak valid'], 401);
            }

            // Fetch fresh user data from DB for fields that can change (e.g. master_lokasi_id)
            $dbUser = User::select('id', 'master_lokasi_id', 'role', 'is_active')->find($decoded->id);

            if (!$dbUser || !$dbUser->is_active) {
                return response()->json(['error' => 'Akun pengguna tidak aktif atau tidak ditemukan'], 401);
            }

            // Also set as a property for easy access
            $request->attributes->set('user', (object) [
                'id' => $decoded->id,
                'nik' => $decoded->nik ?? null,
                'role' => $dbUser->role,
                'master_lokasi_id' => $dbUser->master_lokasi_id,
            ]);

            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token otorisasi tidak valid atau kedaluwarsa'], 401);
        }
    }
}
