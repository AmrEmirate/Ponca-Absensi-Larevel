<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MasterLokasi;
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

        if (!$user) {
            return response()->json(['error' => 'Email/NIK atau password salah'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'Akun Anda telah dinonaktifkan. Silakan hubungi Administrator.'], 403);
        }

        if (!Hash::check($password, $user->password)) {
            return response()->json(['error' => 'Email/NIK atau password salah'], 401);
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
            return response()->json(['error' => 'Pengguna tidak ditemukan'], 404);
        }

        try {
            $masterLokasi = $user->master_lokasi_id ? MasterLokasi::find($user->master_lokasi_id) : MasterLokasi::first();
        } catch (\Exception $e) {
            $masterLokasi = null;
        }

        $tz = $masterLokasi ? $masterLokasi->timezone : 'Asia/Jakarta';
        $tzAbbr = $masterLokasi ? $masterLokasi->timezone_abbr : 'WIB';
        $lokasiNama = $masterLokasi ? $masterLokasi->nama_place : 'Pabrik Utama';

        return response()->json([
            'id' => $user->id,
            'nik' => $user->nik,
            'nama' => $user->nama,
            'email' => $user->email,
            'jabatan' => $user->jabatan,
            'role' => $user->role,
            'fotoProfil' => $user->foto_profil,
            'foto_profil' => $user->foto_profil,
            'jamMasukKerja' => $user->jam_masuk_kerja,
            'jam_masuk_kerja' => $user->jam_masuk_kerja,
            'jamKeluarKerja' => $user->jam_keluar_kerja,
            'jam_keluar_kerja' => $user->jam_keluar_kerja,
            'faceReverificationStatus' => $user->face_reverification_status,
            'face_reverification_status' => $user->face_reverification_status,
            'createdAt' => $user->created_at ? $user->created_at->toISOString() : null,
            'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
            'serverTime' => \Carbon\Carbon::now($tz)->toIso8601String(),
            'server_time' => \Carbon\Carbon::now($tz)->toIso8601String(),
            'timezone' => $tz,
            'timezoneAbbr' => $tzAbbr,
            'timezone_abbr' => $tzAbbr,
            'masterLokasiNama' => $lokasiNama,
            'master_lokasi_nama' => $lokasiNama,
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
        $targetUserId = $request->input('targetUserId');

        $targetId = ($jwtUser->role === 'ADMIN' && $targetUserId) ? (int)$targetUserId : $jwtUser->id;
        $user = User::find($targetId);

        if (!$user) {
            return response()->json(['error' => 'Pengguna tidak ditemukan.'], 404);
        }

        $status = ($jwtUser->role === 'ADMIN') ? 'APPROVED' : 'PENDING';

        $user->update([
            'foto_referensi' => null, // Hapus data foto sampel wajah lama agar bersih total
            'face_reverification_status' => $status,
        ]);

        if ($status === 'PENDING') {
            try {
                \App\Models\Notification::create([
                    'user_id' => null, // Notifikasi untuk Admin
                    'title' => "👤 Permintaan Verifikasi Wajah",
                    'message' => "{$user->nama} mengajukan verifikasi ulang sampel foto wajah.",
                    'type' => 'FACE_REVERIFY',
                    'is_read' => false,
                ]);
            } catch (\Exception $e) {
                // Ignore notification error
            }
        }

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

        $query = User::where('role', '!=', 'SCANNER')
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

        // Calculate shift dates based on timezone
        $masterLokasi = null;
        if ($jwtUser->role === 'SCANNER' && !empty($jwtUser->master_lokasi_id)) {
            $masterLokasi = MasterLokasi::find($jwtUser->master_lokasi_id);
        }
        if (!$masterLokasi) {
            $masterLokasi = MasterLokasi::where('is_active', true)->first() ?? MasterLokasi::first();
        }
        $tz = $masterLokasi ? $masterLokasi->timezone : 'Asia/Jakarta';

        $now = \Carbon\Carbon::now($tz);
        $todayStr = $now->hour < 5 ? $now->copy()->subDay()->toDateString() : $now->toDateString();
        $yesterdayStr = $now->copy()->subDay()->toDateString();
        
        $recentAbsensis = \App\Models\Absensi::whereIn('tanggal', [$todayStr, $yesterdayStr])
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('user_id');

        $result = $users->map(function ($user) use ($recentAbsensis, $todayStr, $yesterdayStr) {
            $userAbsens = $recentAbsensis->get($user->id);

            $hasCheckedIn = false;
            $hasCheckedOut = false;

            if ($userAbsens && $userAbsens->isNotEmpty()) {
                // Cari record hari ini terlebih dahulu
                $todayAbsen = $userAbsens->first(function ($absen) use ($todayStr) {
                    $tgl = $absen->tanggal ? (is_string($absen->tanggal) ? substr($absen->tanggal, 0, 10) : $absen->tanggal->format('Y-m-d')) : '';
                    return $tgl === $todayStr;
                });

                if ($todayAbsen) {
                    $hasCheckedIn = !empty($todayAbsen->waktu_masuk);
                    $hasCheckedOut = !empty($todayAbsen->waktu_keluar);
                } else {
                    // Cek jika ada shift malam kemarin yang belum check-out
                    $yesterdayAbsen = $userAbsens->first(function ($absen) use ($yesterdayStr) {
                        $tgl = $absen->tanggal ? (is_string($absen->tanggal) ? substr($absen->tanggal, 0, 10) : $absen->tanggal->format('Y-m-d')) : '';
                        return $tgl === $yesterdayStr;
                    });
                    if ($yesterdayAbsen && !empty($yesterdayAbsen->waktu_masuk) && empty($yesterdayAbsen->waktu_keluar)) {
                        $hasCheckedIn = true;
                        $hasCheckedOut = false;
                    }
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

    /**
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request)
    {
        $jwtUser = $request->attributes->get('user');
        $user = User::find($jwtUser->id);

        if (!$user) {
            return response()->json(['error' => 'Pengguna tidak ditemukan'], 404);
        }

        $passwordLama = $request->input('password_lama') ?? $request->input('currentPassword');
        $passwordBaru = $request->input('password_baru') ?? $request->input('newPassword');

        if (!$passwordLama || !$passwordBaru) {
            return response()->json(['error' => 'Password lama dan password baru wajib diisi'], 400);
        }

        if (!Hash::check($passwordLama, $user->password)) {
            return response()->json(['error' => 'Password saat ini tidak sesuai'], 400);
        }

        if (strlen($passwordBaru) < 6) {
            return response()->json(['error' => 'Password baru minimal 6 karakter'], 400);
        }

        $user->password = Hash::make($passwordBaru);
        $user->save();

        return response()->json(['message' => 'Password berhasil diperbarui']);
    }
}
