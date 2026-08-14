<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class JwtAuth
{
    /**
     * Handle an incoming request.
     * Validates Bearer JWT token or session and attaches user to request.
     */
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);

            try {
                $secret = config('services.jwt.secret') ?? env('JWT_SECRET', 'ce1ca04cea4e29159d5e1696054e18546892aec1669eb7f791f9b172f72aa3ab500bb61ac6ea26845c553b5acfc1c2417dd2191a3c9a7957d91f07931eee7359');
                if (empty($secret)) {
                    return response()->json(['error' => 'Konfigurasi kunci otorisasi server tidak valid'], 500);
                }
                $decoded = JWT::decode($token, new Key($secret, 'HS256'));

                if (!isset($decoded->id)) {
                    return response()->json(['error' => 'Payload token otorisasi tidak valid'], 401);
                }

                $dbUser = User::find($decoded->id);

                if (!$dbUser || !$dbUser->is_active) {
                    return response()->json(['error' => 'Akun pengguna tidak aktif atau tidak ditemukan'], 401);
                }

                $request->setUserResolver(fn () => $dbUser);
                Auth::setUser($dbUser);

                $request->attributes->set('user', (object) [
                    'id' => $decoded->id,
                    'nik' => $decoded->nik ?? $dbUser->nik ?? null,
                    'role' => $dbUser->role,
                    'master_lokasi_id' => $dbUser->master_lokasi_id,
                ]);

                return $next($request);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Token otorisasi tidak valid atau kedaluwarsa'], 401);
            }
        }

        // Fallback to session web guard if session cookie is present
        if (Auth::guard('web')->check()) {
            $webUser = Auth::guard('web')->user();
            if ($webUser && $webUser->is_active) {
                $request->setUserResolver(fn () => $webUser);
                Auth::setUser($webUser);
                $request->attributes->set('user', (object) [
                    'id' => $webUser->id,
                    'nik' => $webUser->nik,
                    'role' => $webUser->role,
                    'master_lokasi_id' => $webUser->master_lokasi_id,
                ]);
                return $next($request);
            }
        }

        return response()->json(['error' => 'Token otorisasi tidak ditemukan'], 401);
    }
}
