<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FacePlusPlusService;
use App\Services\CloudinaryService;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        $emailOrNik = $validated['email'];
        $password = $validated['password'];

        $user = User::where('email', $emailOrNik)->orWhere('nik', $emailOrNik)->first();

        if (!$user || !$user->is_active) {
            return response()->json(['error' => 'Kredensial tidak valid atau akun dinonaktifkan'], 401);
        }

        if (!Hash::check($password, $user->password)) {
            return response()->json(['error' => 'Kredensial tidak valid'], 401);
        }

        $secret = config('services.jwt.secret');
        if (empty($secret)) {
            return response()->json(['error' => 'Konfigurasi kunci otorisasi server (JWT_SECRET) tidak valid atau belum diatur.'], 500);
        }
        $payload = [
            'id' => $user->id,
            'nik' => $user->nik,
            'role' => $user->role,
            'iat' => time(),
            'exp' => time() + (7 * 24 * 60 * 60), // 7 days
        ];

        $token = JWT::encode($payload, $secret, 'HS256');

        return response()->json([
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nama' => $user->nama,
                'role' => $user->role,
                'fotoReferensi' => $user->foto_referensi,
                'fotoProfil' => $user->foto_profil,
            ],
        ]);
    }

    /**
     * POST /api/auth/verify-pin
     */
    public function verifyPin()
    {
        return response()->json(['message' => 'Gunakan verifikasi PIN lokal di aplikasi Android.']);
    }

    /**
     * GET /api/auth/me
     */
    public function getProfile(Request $request)
    {
        $jwtUser = $request->attributes->get('user');
        $user = User::find($jwtUser->id);

        if (!$user) {
            return response()->json(['error' => 'User tidak ditemukan'], 404);
        }

        return response()->json([
            'id' => $user->id,
            'nik' => $user->nik,
            'nama' => $user->nama,
            'email' => $user->email,
            'jabatan' => $user->jabatan,
            'role' => $user->role,
            'fotoProfil' => $user->foto_profil,
            'jamMasukKerja' => $user->jam_masuk_kerja,
            'jamKeluarKerja' => $user->jam_keluar_kerja,
            'faceReverificationStatus' => $user->face_reverification_status,
            'createdAt' => $user->created_at ? $user->created_at->toISOString() : null,
        ]);
    }

    /**
     * POST /api/auth/register-face
     * Registrasi wajah menggunakan Face++ Detect + Cloudinary upload
     */
    public function registerFace(Request $request)
    {
        $jwtUser = $request->attributes->get('user');
        $userId = $jwtUser->id;
        $targetUserId = $request->input('targetUserId');

        // Admin atau Scanner bisa mendaftarkan wajah orang lain
        if (($jwtUser->role === 'ADMIN' || $jwtUser->role === 'SCANNER') && $targetUserId) {
            $userId = (int) $targetUserId;
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'Pengguna target tidak ditemukan'], 404);
        }

        $fotoWajah = $request->input('fotoWajah');
        if (!$fotoWajah) {
            return response()->json(['error' => 'Foto wajah tidak boleh kosong'], 400);
        }

        // 1. Validasi ada wajah menggunakan Face++ Detect API
        try {
            $faceService = new FacePlusPlusService();
            $faceDetected = $faceService->detectFace($fotoWajah);
            if (!$faceDetected) {
                return response()->json(['error' => 'Wajah tidak terdeteksi pada foto.'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage() ?: 'Gagal mendeteksi wajah'], 400);
        }

        // 2. Upload foto ke Cloudinary
        try {
            $cloudinary = new CloudinaryService();
            $fileUrl = $cloudinary->uploadBase64($fotoWajah, 'faces');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal menyimpan foto wajah ke cloud'], 500);
        }

        // 3. Simpan URL foto referensi ke database
        $user->update([
            'foto_referensi' => $fileUrl,
            'foto_profil' => $fileUrl,
            'face_reverification_status' => 'NONE',
        ]);

        return response()->json(['message' => 'Wajah berhasil didaftarkan', 'fotoUrl' => $fileUrl]);
    }

    /**
     * POST /api/auth/request-face-reverification
     */
    public function requestFaceReverification(Request $request)
    {
        $jwtUser = $request->attributes->get('user');

        User::where('id', $jwtUser->id)->update([
            'face_reverification_status' => 'PENDING',
        ]);

        return response()->json(['message' => 'Pengajuan verifikasi ulang wajah berhasil dikirim']);
    }

    /**
     * GET /api/auth/karyawan
     * List semua karyawan aktif (untuk Admin / Scanner dengan status absensi hari ini)
     */
    public function getAllKaryawan(Request $request)
    {
        $jwtUser = $request->attributes->get('user');

        if ($jwtUser->role !== 'ADMIN' && $jwtUser->role !== 'SCANNER') {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        $query = User::whereIn('role', ['KARYAWAN', 'ADMIN'])
            ->where('is_active', true);

        // Filter by location if requester is a SCANNER tied to a specific location
        if ($jwtUser->role === 'SCANNER' && !empty($jwtUser->master_lokasi_id)) {
            $query->where(function ($q) use ($jwtUser) {
                $q->where('master_lokasi_id', $jwtUser->master_lokasi_id)
                  ->orWhereNull('master_lokasi_id');
            });
        }

        $users = $query->select('id', 'nik', 'nama', 'jabatan', 'foto_profil', 'foto_referensi', 'master_lokasi_id')
            ->orderBy('nama')
            ->get();

        // Calculate shift dates (WIB)
        $now = \Carbon\Carbon::now('Asia/Jakarta');
        $todayStr = $now->hour < 5 ? $now->copy()->subDay()->toDateString() : $now->toDateString();
        $yesterdayStr = $now->copy()->subDay()->toDateString();
        
        $recentAbsensis = \App\Models\Absensi::whereIn('tanggal', [$todayStr, $yesterdayStr])
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('user_id');

        $result = $users->map(function ($user) use ($recentAbsensis, $todayStr, $yesterdayStr) {
            $userAbsens = $recentAbsensis->get($user->id);
            $latestAbsen = $userAbsens ? $userAbsens->first() : null;

            $hasCheckedIn = false;
            $hasCheckedOut = false;

            if ($latestAbsen) {
                if ($latestAbsen->tanggal === $todayStr) {
                    $hasCheckedIn = !empty($latestAbsen->waktu_masuk);
                    $hasCheckedOut = !empty($latestAbsen->waktu_keluar);
                } else if ($latestAbsen->tanggal === $yesterdayStr && !empty($latestAbsen->waktu_masuk) && empty($latestAbsen->waktu_keluar)) {
                    // Shift malam kemarin belum check-out
                    $hasCheckedIn = true;
                    $hasCheckedOut = false;
                }
            }

            return [
                'id' => $user->id,
                'nik' => $user->nik,
                'nama' => $user->nama,
                'jabatan' => $user->jabatan,
                'fotoProfil' => $user->foto_profil,
                'hasFotoReferensi' => !empty($user->foto_referensi),
                'masterLokasiId' => $user->master_lokasi_id,
                'hasCheckedIn' => $hasCheckedIn,
                'hasCheckedOut' => $hasCheckedOut,
            ];
        });

        return response()->json($result);
    }
}
