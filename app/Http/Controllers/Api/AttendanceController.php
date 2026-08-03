<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Absensi;
use App\Models\MasterLokasi;
use App\Services\FacePlusPlusService;
use App\Services\GeoService;
use App\Http\Requests\AttendanceCheckInRequest;
use App\Http\Requests\AttendanceCheckOutRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * POST /api/attendance/check-in
     */
    public function checkIn(AttendanceCheckInRequest $request)
    {
        $jwtUser = $request->attributes->get('user');
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $fotoWajah = $request->input('fotoWajah');
        $targetUserId = $request->input('targetUserId');

        $userId = $jwtUser->id;
        $scannerId = null;

        if ($latitude === null || $longitude === null || !is_numeric($latitude) || !is_numeric($longitude)) {
            return response()->json(['error' => 'Koordinat lokasi (latitude & longitude) wajib dikirim dan bernilai angka.'], 400);
        }

        // Validasi Jam Operasional Absensi (Tutup 00:00 - 05:00 WIB)
        $wibTime = Carbon::now('Asia/Jakarta');
        $hour = $wibTime->hour;
        if ($hour >= 0 && $hour < 5) {
            return response()->json([
                'error' => 'Sistem absensi sedang ditutup (00:00 - 05:00 WIB). Anda baru bisa absen kembali pada jam 5 pagi.'
            ], 403);
        }

        // Jika user adalah ADMIN atau SCANNER, maka dia mengabsenkan orang lain
        if (($jwtUser->role === 'ADMIN' || $jwtUser->role === 'SCANNER') && $targetUserId) {
            $parsedTarget = (int) $targetUserId;
            if ($parsedTarget <= 0) {
                return response()->json(['error' => 'Format ID Pengguna target tidak valid.'], 400);
            }
            $userId = $parsedTarget;
            $scannerId = $jwtUser->id;
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'Pengguna tidak ditemukan.'], 404);
        }
        if (!$user->is_active) {
            return response()->json(['error' => 'Akun pengguna ini telah dinonaktifkan.'], 403);
        }
        if (!$user->foto_referensi) {
            return response()->json(['error' => 'User tidak memiliki foto referensi di database.'], 400);
        }

        // 1. Dapatkan master lokasi geofence & Validasi Strict (Option A)
        $scannerLokasi = null;
        if ($jwtUser->role === 'SCANNER' && $jwtUser->master_lokasi_id) {
            $scannerLokasi = MasterLokasi::find($jwtUser->master_lokasi_id);
        }

        $userLokasi = null;
        if ($user->master_lokasi_id) {
            $userLokasi = MasterLokasi::find($user->master_lokasi_id);
        }

        // Strict Check (Option A): Jika absen via Scanner, lokasi Scanner harus sama dengan lokasi penugasan Karyawan
        if ($jwtUser->role === 'SCANNER' && $scannerLokasi && $userLokasi && $scannerLokasi->id !== $userLokasi->id) {
            return response()->json([
                'error' => "Karyawan '{$user->nama}' terdaftar di {$userLokasi->nama_place}, tidak dapat melakukan absen di lokasi {$scannerLokasi->nama_place}."
            ], 403);
        }

        // Tentukan lokasi target geofence (Prioritas: Lokasi Karyawan -> Lokasi Scanner -> MasterLokasi Aktif)
        $masterLokasi = $userLokasi ?? $scannerLokasi ?? MasterLokasi::where('is_active', true)->first() ?? MasterLokasi::first();
        if (!$masterLokasi) {
            return response()->json(['error' => 'Master lokasi belum dikonfigurasi oleh Admin.'], 500);
        }

        // 2. Lapis 1: Geofencing Validation
        $distance = GeoService::getDistanceInMeters((float)$latitude, (float)$longitude, $masterLokasi->latitude, $masterLokasi->longitude);
        if ($distance > $masterLokasi->radius) {
            return response()->json([
                'error' => "Posisi Anda berada di luar radius lokasi ({$masterLokasi->nama_place}).",
                'distance' => round($distance),
                'allowedRadius' => $masterLokasi->radius,
            ], 403);
        }

        // 3. Lapis 3: Facial Recognition Validation
        if (!$fotoWajah) {
            return response()->json(['error' => 'Foto live wajah wajib dikirim.'], 400);
        }

        try {
            $faceService = new FacePlusPlusService();
            $result = $faceService->compareFaces($user->foto_referensi, $fotoWajah);

            if (!$result['isMatch']) {
                return response()->json([
                    'error' => 'Wajah tidak cocok dengan data referensi karyawan.',
                    'similarityScore' => $result['confidence'],
                ], 403);
            }
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Wajah tidak terdeteksi')) {
                return response()->json(['error' => $e->getMessage()], 400);
            }
            return response()->json(['error' => 'Terjadi kesalahan pada server: ' . $e->getMessage()], 500);
        }

        // 4. Catat Absensi Masuk
        $startOfDay = $wibTime->copy()->startOfDay()->toDateString();

        // Cek jika sudah absen hari ini
        $existingAbsen = Absensi::where('user_id', $userId)
            ->where('tanggal', $startOfDay)
            ->first();

        if ($existingAbsen) {
            return response()->json(['error' => 'Anda sudah melakukan absen masuk hari ini.'], 400);
        }

        // Penentuan status telat secara dinamis berdasarkan jadwal user
        $batasMasuk = 8 * 60; // fallback 08:00
        if ($user->jam_masuk_kerja) {
            $parts = explode(':', $user->jam_masuk_kerja);
            if (count($parts) >= 2) {
                $batasMasuk = (int)$parts[0] * 60 + (int)$parts[1];
            }
        }

        $jamMasuk = $wibTime->hour * 60 + $wibTime->minute;
        $status = $jamMasuk > $batasMasuk ? 'TERLAMBAT' : 'TEPAT_WAKTU';

        $absenBaru = Absensi::create([
            'user_id' => $userId,
            'scanner_id' => $scannerId,
            'master_lokasi_id' => $masterLokasi->id,
            'tanggal' => $startOfDay,
            'waktu_masuk' => Carbon::now('Asia/Jakarta'),
            'lat_masuk' => (float)$latitude,
            'lng_masuk' => (float)$longitude,
            'foto_masuk' => null,
            'status' => $status,
        ]);

        return response()->json(['message' => 'Absen Masuk Berhasil', 'data' => $absenBaru]);
    }

    /**
     * POST /api/attendance/check-out
     */
    public function checkOut(AttendanceCheckOutRequest $request)
    {
        $jwtUser = $request->attributes->get('user');
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $fotoWajah = $request->input('fotoWajah');
        $targetUserId = $request->input('targetUserId');

        $userId = $jwtUser->id;

        if ($latitude === null || $longitude === null || !is_numeric($latitude) || !is_numeric($longitude)) {
            return response()->json(['error' => 'Koordinat lokasi (latitude & longitude) wajib dikirim dan bernilai angka.'], 400);
        }

        $wibTime = Carbon::now('Asia/Jakarta');

        if (($jwtUser->role === 'ADMIN' || $jwtUser->role === 'SCANNER') && $targetUserId) {
            $parsedTarget = (int) $targetUserId;
            if ($parsedTarget <= 0) {
                return response()->json(['error' => 'Format ID Pengguna target tidak valid.'], 400);
            }
            $userId = $parsedTarget;
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'Pengguna tidak ditemukan.'], 404);
        }
        if (!$user->is_active) {
            return response()->json(['error' => 'Akun pengguna ini telah dinonaktifkan.'], 403);
        }
        if (!$user->foto_referensi) {
            return response()->json(['error' => 'User tidak memiliki foto referensi di database.'], 400);
        }

        // 1. Dapatkan master lokasi geofence & Validasi Strict (Option A)
        $scannerLokasi = null;
        if ($jwtUser->role === 'SCANNER' && $jwtUser->master_lokasi_id) {
            $scannerLokasi = MasterLokasi::find($jwtUser->master_lokasi_id);
        }

        $userLokasi = null;
        if ($user->master_lokasi_id) {
            $userLokasi = MasterLokasi::find($user->master_lokasi_id);
        }

        // Strict Check (Option A): Jika absen via Scanner, lokasi Scanner harus sama dengan lokasi penugasan Karyawan
        if ($jwtUser->role === 'SCANNER' && $scannerLokasi && $userLokasi && $scannerLokasi->id !== $userLokasi->id) {
            return response()->json([
                'error' => "Karyawan '{$user->nama}' terdaftar di {$userLokasi->nama_place}, tidak dapat melakukan absen di lokasi {$scannerLokasi->nama_place}."
            ], 403);
        }

        $masterLokasi = $userLokasi ?? $scannerLokasi ?? MasterLokasi::where('is_active', true)->first() ?? MasterLokasi::first();
        if (!$masterLokasi) {
            return response()->json(['error' => 'Master lokasi belum dikonfigurasi oleh Admin.'], 500);
        }

        $distance = GeoService::getDistanceInMeters((float)$latitude, (float)$longitude, $masterLokasi->latitude, $masterLokasi->longitude);
        if ($distance > $masterLokasi->radius) {
            return response()->json([
                'error' => "Posisi Anda berada di luar radius lokasi ({$masterLokasi->nama_place}).",
                'distance' => round($distance),
                'allowedRadius' => $masterLokasi->radius,
            ], 403);
        }

        // 2. Face Recognition
        if (!$fotoWajah) {
            return response()->json(['error' => 'Foto live wajah wajib dikirim.'], 400);
        }

        try {
            $faceService = new FacePlusPlusService();
            $result = $faceService->compareFaces($user->foto_referensi, $fotoWajah);

            if (!$result['isMatch']) {
                return response()->json([
                    'error' => 'Wajah tidak cocok dengan data referensi karyawan.',
                    'similarityScore' => $result['confidence'],
                ], 403);
            }
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Wajah tidak terdeteksi')) {
                return response()->json(['error' => $e->getMessage()], 400);
            }
            return response()->json(['error' => 'Terjadi kesalahan pada server: ' . $e->getMessage()], 500);
        }

        // 3. Cek absen masuk (hari ini atau kemarin jika overnight shift)
        $startOfDay = $wibTime->copy()->startOfDay()->toDateString();

        $existingAbsen = Absensi::where('user_id', $userId)
            ->where('tanggal', $startOfDay)
            ->first();

        if (!$existingAbsen) {
            // Cek absen masuk aktif kemarin (overnight shift)
            $yesterday = $wibTime->copy()->subDay()->startOfDay()->toDateString();
            $existingAbsen = Absensi::where('user_id', $userId)
                ->where('tanggal', $yesterday)
                ->whereNull('waktu_keluar')
                ->first();
        }

        if (!$existingAbsen) {
            return response()->json(['error' => 'Data absen masuk aktif tidak ditemukan.'], 400);
        }

        if ($existingAbsen->waktu_keluar) {
            return response()->json(['error' => 'Anda sudah absen keluar hari ini.'], 400);
        }

        $absenKeluar = tap($existingAbsen)->update([
            'waktu_keluar' => Carbon::now('Asia/Jakarta'),
            'foto_keluar' => null,
        ]);

        return response()->json(['message' => 'Absen Keluar Berhasil', 'data' => $existingAbsen->fresh()]);
    }

    /**
     * GET /api/attendance/history
     */
    public function getHistory(Request $request)
    {
        $jwtUser = $request->attributes->get('user');
        $targetUserId = $request->query('userId');
        $month = $request->query('month');
        $year = $request->query('year');

        $query = Absensi::with(['user:id,nama']);

        // Determine which user's history to fetch
        if ($targetUserId && $jwtUser->role === 'ADMIN') {
            $query->where('user_id', (int) $targetUserId);
        } else {
            $query->where('user_id', $jwtUser->id);
        }

        // Filter by month/year
        $limit = 30;
        if ($month && $year) {
            $m = (int) $month;
            $y = (int) $year;
            if ($m >= 1 && $m <= 12 && $y >= 2000 && $y <= 2100) {
                $startDate = Carbon::createFromDate($y, $m, 1)->startOfDay();
                $endDate = $startDate->copy()->addMonth()->startOfDay();

                $query->where('tanggal', '>=', $startDate->toDateString())
                      ->where('tanggal', '<', $endDate->toDateString());
                $limit = null;
            }
        }

        $query->orderBy('tanggal', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        $historyRecords = $query->get();

        $userIds = $historyRecords->pluck('user_id')->unique()->toArray();
        $approvedIzins = \App\Models\Izin::whereIn('user_id', $userIds)
            ->where('status', 'APPROVED')
            ->get()
            ->keyBy(function ($item) {
                $t = $item->tanggal ? (is_string($item->tanggal) ? substr($item->tanggal, 0, 10) : $item->tanggal->format('Y-m-d')) : '';
                return $item->user_id . '_' . $t;
            });

        $history = $historyRecords->map(function ($absen) use ($approvedIzins) {
            $dateStr = $absen->tanggal ? (is_string($absen->tanggal) ? substr($absen->tanggal, 0, 10) : $absen->tanggal->format('Y-m-d')) : '';
            $key = $absen->user_id . '_' . $dateStr;
            $izinInfo = $approvedIzins->get($key);
            $keteranganIzin = $izinInfo ? "{$izinInfo->jenis_izin}: {$izinInfo->deskripsi}" : null;

            return [
                'id' => $absen->id,
                'tanggal' => $dateStr,
                'waktuMasuk' => $absen->waktu_masuk ? Carbon::parse($absen->waktu_masuk)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP') : null,
                'waktuKeluar' => $absen->waktu_keluar ? Carbon::parse($absen->waktu_keluar)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP') : null,
                'status' => $absen->status,
                'keteranganIzin' => $keteranganIzin,
                'user' => $absen->user ? ['nama' => $absen->user->nama] : null,
            ];
        });

        return response()->json($history);
    }

    /**
     * GET /api/attendance/geofence
     */
    public function getGeofence(Request $request)
    {
        $jwtUser = $request->attributes->get('user');
        $locationId = $request->query('locationId');
        
        $master = null;
        if ($locationId) {
            $master = MasterLokasi::find($locationId);
        } elseif ($jwtUser && $jwtUser->master_lokasi_id) {
            $master = MasterLokasi::find($jwtUser->master_lokasi_id);
        }
        if (!$master) {
            $master = MasterLokasi::where('is_active', true)->first() ?? MasterLokasi::first();
        }

        if (!$master) {
            return response()->json(['error' => 'Master lokasi belum diset'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $master->id,
                'namaPlace' => $master->nama_place,
                'tipe' => $master->tipe,
                'latitude' => $master->latitude,
                'longitude' => $master->longitude,
                'radius' => $master->radius,
            ]
        ]);
    }
}
