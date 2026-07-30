<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Absensi;
use App\Models\MasterLokasi;
use App\Services\FacePlusPlusService;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Requests\CreateEmployeeRequest;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * GET /api/admin/report
     */
    public function getReport(Request $request)
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $locationId = $request->query('locationId') ?? $request->query('master_lokasi_id');

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $start = $today;
        $end = $today;

        if ($startDate && $endDate) {
            try {
                $start = Carbon::parse($startDate)->startOfDay()->toDateString();
                $end = Carbon::parse($endDate)->endOfDay()->toDateString();
            } catch (\Exception $e) {
                return response()->json(['error' => 'Format startDate atau endDate tidak valid.'], 400);
            }
        }

        $query = Absensi::with(['user:id,nama,nik,jabatan,foto_profil'])
            ->where('tanggal', '>=', $start)
            ->where('tanggal', '<=', $end);

        if ($locationId) {
            $locId = (int) $locationId;
            $query->where(function ($q) use ($locId) {
                $q->where('master_lokasi_id', $locId)
                  ->orWhereHas('user', function ($uQ) use ($locId) {
                      $uQ->where('master_lokasi_id', $locId);
                  });
            });
        }

        $absensiRecords = $query->orderBy('tanggal', 'desc')->get();
        $results = collect();

        $presentUserDates = [];
        foreach ($absensiRecords as $absen) {
            $dateStr = $absen->tanggal ? $absen->tanggal->format('Y-m-d') : '';
            $presentUserDates[$absen->user_id . '_' . $dateStr] = true;
            $results->push([
                'id' => $absen->id,
                'tanggal' => $absen->tanggal ? $absen->tanggal->toISOString() : null,
                'waktuMasuk' => $absen->waktu_masuk ? $absen->waktu_masuk->toISOString() : null,
                'waktuKeluar' => $absen->waktu_keluar ? $absen->waktu_keluar->toISOString() : null,
                'status' => $absen->status,
                'user' => $absen->user ? [
                    'nik' => $absen->user->nik,
                    'nama' => $absen->user->nama,
                    'jabatan' => $absen->user->jabatan,
                    'fotoProfil' => $absen->user->foto_profil,
                ] : null,
            ]);
        }

        // If single-day report query, include active employees who haven't checked in as ALPA
        if ($start === $end) {
            $empQuery = User::where('role', 'KARYAWAN')->where('is_active', true);
            if ($locationId) {
                $empQuery->where('master_lokasi_id', (int) $locationId);
            }
            $activeEmployees = $empQuery->get();

            foreach ($activeEmployees as $emp) {
                $key = $emp->id . '_' . $start;
                if (!isset($presentUserDates[$key])) {
                    $results->push([
                        'id' => -$emp->id,
                        'tanggal' => Carbon::parse($start)->toISOString(),
                        'waktuMasuk' => null,
                        'waktuKeluar' => null,
                        'status' => 'ALPA',
                        'user' => [
                            'nik' => $emp->nik,
                            'nama' => $emp->nama,
                            'jabatan' => $emp->jabatan,
                            'fotoProfil' => $emp->foto_profil,
                        ],
                    ]);
                }
            }
        }

        return response()->json(['data' => $results]);
    }

    /**
     * GET /api/admin/lokasi
     */
    public function getLokasis()
    {
        $lokasis = MasterLokasi::orderBy('tipe')->orderBy('nama_place')->get();
        return response()->json(['data' => $lokasis]);
    }

    /**
     * POST /api/admin/lokasi
     */
    public function createLokasi(Request $request)
    {
        $namaPlace = $request->input('nama_place');
        $tipe = strtoupper($request->input('tipe', 'OUTLET'));
        $alamat = $request->input('alamat');
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $radius = $request->input('radius', 100);

        if (!$namaPlace || $latitude === null || $longitude === null) {
            return response()->json(['error' => 'Nama lokasi, latitude, dan longitude wajib diisi'], 400);
        }

        if (!in_array($tipe, ['PABRIK', 'OUTLET', 'AREA_PEMASARAN'])) {
            $tipe = 'OUTLET';
        }

        $lokasi = MasterLokasi::create([
            'nama_place' => $namaPlace,
            'tipe' => $tipe,
            'alamat' => $alamat,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'radius' => (float) $radius,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Master lokasi berhasil dibuat',
            'data' => $lokasi,
        ]);
    }

    /**
     * PUT /api/admin/lokasi/{id}
     */
    public function updateLokasi(Request $request, int $id)
    {
        $lokasi = MasterLokasi::findOrFail($id);

        $namaPlace = $request->input('nama_place', $lokasi->nama_place);
        $tipe = strtoupper($request->input('tipe', $lokasi->tipe));
        $alamat = $request->input('alamat', $lokasi->alamat);
        $latitude = $request->input('latitude', $lokasi->latitude);
        $longitude = $request->input('longitude', $lokasi->longitude);
        $radius = $request->input('radius', $lokasi->radius);
        $isActive = $request->input('is_active');

        if (!in_array($tipe, ['PABRIK', 'OUTLET', 'AREA_PEMASARAN'])) {
            $tipe = $lokasi->tipe;
        }

        $lokasi->update([
            'nama_place' => $namaPlace,
            'tipe' => $tipe,
            'alamat' => $alamat,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'radius' => (float) $radius,
            'is_active' => $isActive !== null ? (bool) $isActive : $lokasi->is_active,
        ]);

        return response()->json([
            'message' => 'Master lokasi berhasil diperbarui',
            'data' => $lokasi,
        ]);
    }

    /**
     * DELETE /api/admin/lokasi/{id}
     */
    public function deleteLokasi(int $id)
    {
        $lokasi = MasterLokasi::findOrFail($id);
        
        // Count users using this location
        $userCount = User::where('master_lokasi_id', $id)->count();
        if ($userCount > 0) {
            $lokasi->update(['is_active' => false]);
            return response()->json(['message' => 'Lokasi sedang digunakan oleh karyawan, status diubah menjadi Nonaktif.']);
        }

        $lokasi->delete();
        return response()->json(['message' => 'Master lokasi berhasil dihapus permanen']);
    }

    /**
     * GET /api/admin/geofence
     */
    public function getGeofence(Request $request)
    {
        $id = $request->query('id');
        if ($id) {
            $masterLokasi = MasterLokasi::find($id);
        } else {
            $masterLokasi = MasterLokasi::first();
        }

        if (!$masterLokasi) {
            return response()->json([
                'data' => [
                    'id' => 1,
                    'nama_place' => 'PONCA FOOD HQ',
                    'tipe' => 'PABRIK',
                    'latitude' => -6.3351622,
                    'longitude' => 106.7692376,
                    'radius' => 500,
                ]
            ]);
        }
        return response()->json(['data' => $masterLokasi]);
    }

    /**
     * POST /api/admin/geofence
     */
    public function updateGeofence(Request $request)
    {
        $id = $request->input('id');
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $radius = $request->input('radius');
        $namaPlace = $request->input('nama_place');
        $tipe = $request->input('tipe');

        if ($latitude === null || $longitude === null || $radius === null) {
            return response()->json(['error' => 'Data lintang, bujur, dan radius wajib diisi.'], 400);
        }

        $latFloat = (float) $latitude;
        $lngFloat = (float) $longitude;
        $radFloat = (float) $radius;

        if (is_nan($latFloat) || is_nan($lngFloat) || is_nan($radFloat)) {
            return response()->json(['error' => 'Data lintang, bujur, dan radius harus berupa angka yang valid.'], 400);
        }

        if ($id) {
            $masterLokasi = MasterLokasi::find($id);
        } else {
            $masterLokasi = MasterLokasi::first();
        }

        $data = [
            'latitude' => $latFloat,
            'longitude' => $lngFloat,
            'radius' => $radFloat,
        ];
        if ($namaPlace) $data['nama_place'] = $namaPlace;
        if ($tipe && in_array(strtoupper($tipe), ['PABRIK', 'OUTLET', 'AREA_PEMASARAN'])) $data['tipe'] = strtoupper($tipe);

        if ($masterLokasi) {
            $masterLokasi->update($data);
        } else {
            $masterLokasi = MasterLokasi::create(array_merge([
                'nama_place' => $namaPlace ?? 'PONCA FOOD HQ',
                'tipe' => $tipe ?? 'PABRIK',
            ], $data));
        }

        return response()->json([
            'message' => 'Konfigurasi Geofencing berhasil diperbarui',
            'data' => $masterLokasi,
        ]);
    }

    /**
     * GET /api/admin/export
     */
    public function exportExcel(Request $request)
    {
        $locationId = $request->query('locationId') ?? $request->query('master_lokasi_id');
        $query = Absensi::with('user');

        if ($locationId) {
            $locId = (int) $locationId;
            $query->where(function ($q) use ($locId) {
                $q->where('master_lokasi_id', $locId)
                  ->orWhereHas('user', function ($uQ) use ($locId) {
                      $uQ->where('master_lokasi_id', $locId);
                  });
            });
        }

        $absensi = $query->orderBy('tanggal', 'desc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Absensi');

        // Header
        $sheet->setCellValue('A1', 'Tanggal');
        $sheet->setCellValue('B1', 'NIK');
        $sheet->setCellValue('C1', 'Nama');
        $sheet->setCellValue('D1', 'Jam Masuk');
        $sheet->setCellValue('E1', 'Jam Keluar');
        $sheet->setCellValue('F1', 'Status');

        $row = 2;
        foreach ($absensi as $absen) {
            $formatWib = function ($date) {
                if (!$date) return '-';
                $wib = Carbon::parse($date)->setTimezone('Asia/Jakarta');
                return $wib->format('H:i:s');
            };

            $sheet->setCellValue("A{$row}", $absen->tanggal ? $absen->tanggal->format('Y-m-d') : '');
            $sheet->setCellValue("B{$row}", $absen->user?->nik ?? '');
            $sheet->setCellValue("C{$row}", $absen->user?->nama ?? '');
            $sheet->setCellValue("D{$row}", $formatWib($absen->waktu_masuk));
            $sheet->setCellValue("E{$row}", $formatWib($absen->waktu_keluar));
            $sheet->setCellValue("F{$row}", $absen->status);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'laporan_absensi.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * GET /api/admin/employees
     */
    public function getEmployees(Request $request)
    {
        $locationId = $request->query('locationId') ?? $request->query('master_lokasi_id');

        $query = User::whereIn('role', ['ADMIN', 'KARYAWAN', 'SCANNER'])
            ->with('lokasi');

        if ($locationId) {
            $query->where('master_lokasi_id', (int) $locationId);
        }

        $employees = $query->orderBy('nama')
            ->get()
            ->map(function ($emp) {
                return $this->formatEmployee($emp);
            });

        return response()->json(['data' => $employees]);
    }

    /**
     * POST /api/admin/employees
     */
    public function createEmployee(CreateEmployeeRequest $request)
    {
        $nik = $request->input('nik');
        if (!$nik) {
            $now = Carbon::now('Asia/Jakarta');
            $prefix = $now->format('my');
            $latestUser = User::withTrashed()->where('nik', 'like', $prefix . '%')->whereRaw('LENGTH(nik) = 7')->orderBy('nik', 'desc')->first();
            $nextCounter = $latestUser ? ((int) substr($latestUser->nik, 4)) + 1 : 1;
            $nik = $prefix . str_pad($nextCounter, 3, '0', STR_PAD_LEFT);
        }
        $nama = $request->input('nama');
        $email = $request->input('email');
        $jabatan = $request->input('jabatan');
        $password = $request->input('password');
        $gajiPerhari = $request->input('gajiPerhari', 0);
        $hariKerja = $request->input('hariKerja', 'Senin,Selasa,Rabu,Kamis,Jumat');
        $jamMasukKerja = $request->input('jamMasukKerja', '08:00');
        $jamKeluarKerja = $request->input('jamKeluarKerja', '17:00');
        $masterLokasiId = $request->input('masterLokasiId') ?? $request->input('master_lokasi_id');

        if (!$nama || !$email || !$password) {
            return response()->json(['error' => 'Semua field wajib diisi'], 400);
        }

        $existing = User::withTrashed()->where(function ($q) use ($email, $nik) {
            $q->where('email', $email)->orWhere('nik', $nik);
        })->first();
        if ($existing) {
            return response()->json(['error' => 'Email atau NIK sudah terdaftar (termasuk pengguna yang dinonaktifkan)'], 400);
        }

        $jabatanLower = strtolower($jabatan ?? '');
        $role = $jabatanLower === 'admin' ? 'ADMIN' : ($jabatanLower === 'scanner' ? 'SCANNER' : 'KARYAWAN');

        $newEmployee = User::create([
            'nik' => $nik,
            'nama' => $nama,
            'email' => $email,
            'jabatan' => $jabatan,
            'password' => Hash::make($password),
            'role' => $role,
            'gaji_perhari' => (int) $gajiPerhari,
            'hari_kerja' => $hariKerja,
            'jam_masuk_kerja' => $jamMasukKerja,
            'jam_keluar_kerja' => $jamKeluarKerja,
            'master_lokasi_id' => $masterLokasiId ? (int) $masterLokasiId : null,
        ]);

        return response()->json([
            'message' => 'Karyawan berhasil ditambahkan',
            'data' => $this->formatEmployee($newEmployee->load('lokasi')),
        ]);
    }

    /**
     * PUT /api/admin/employees/{id}
     */
    public function updateEmployee(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $nik = $request->input('nik', $user->nik);
        $nama = $request->input('nama', $user->nama);
        $email = $request->input('email', $user->email);
        $jabatan = $request->input('jabatan', $user->jabatan);
        $password = $request->input('password');
        $isActive = $request->input('isActive');
        $gajiPerhari = $request->input('gajiPerhari');
        $hariKerja = $request->input('hariKerja');
        $jamMasukKerja = $request->input('jamMasukKerja');
        $jamKeluarKerja = $request->input('jamKeluarKerja');
        $masterLokasiId = $request->input('masterLokasiId') ?? $request->input('master_lokasi_id');

        $existing = User::withTrashed()->where(function ($q) use ($email, $nik) {
            if ($email) $q->where('email', $email);
            if ($nik) $q->orWhere('nik', $nik);
        })->where('id', '!=', $id)->first();

        if ($existing) {
            return response()->json(['error' => 'Email atau NIK sudah terdaftar pada pengguna lain'], 400);
        }

        $jabatanLower = strtolower($jabatan ?? '');
        $role = $jabatanLower === 'admin' ? 'ADMIN' : ($jabatanLower === 'scanner' ? 'SCANNER' : 'KARYAWAN');

        $data = [
            'nik' => $nik,
            'nama' => $nama,
            'email' => $email,
            'jabatan' => $jabatan,
            'role' => $role,
        ];

        if ($password) {
            $data['password'] = Hash::make($password);
        }
        if ($isActive !== null) {
            $data['is_active'] = (bool) $isActive;
        }
        if ($gajiPerhari !== null) {
            $data['gaji_perhari'] = (int) $gajiPerhari;
        }
        if ($hariKerja !== null) {
            $data['hari_kerja'] = $hariKerja;
        }
        if ($jamMasukKerja !== null) {
            $data['jam_masuk_kerja'] = $jamMasukKerja;
        }
        if ($jamKeluarKerja !== null) {
            $data['jam_keluar_kerja'] = $jamKeluarKerja;
        }
        if ($masterLokasiId !== null) {
            $data['master_lokasi_id'] = $masterLokasiId ? (int) $masterLokasiId : null;
        }

        if ($user->email === 'amremirate03@gmail.com' || $user->email === 'admin@poncafood.com') {
            if ($role !== 'ADMIN') {
                return response()->json(['error' => 'Role akun Admin Utama tidak boleh diubah'], 403);
            }
            if (isset($data['is_active']) && $data['is_active'] == false) {
                return response()->json(['error' => 'Akun Admin Utama tidak boleh dinonaktifkan'], 403);
            }
        }

        $user->update($data);

        return response()->json([
            'message' => 'Karyawan berhasil diperbarui',
            'data' => $this->formatEmployee($user->fresh()),
        ]);
    }

    /**
     * DELETE /api/admin/employees/{id}
     */
    public function deleteEmployee(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->email === 'amremirate03@gmail.com') {
            return response()->json(['error' => 'Akun Admin Utama tidak dapat dihapus'], 403);
        }

        $absensiCount = Absensi::where('user_id', $id)->count();
        $izinCount = \App\Models\Izin::where('user_id', $id)->count();

        if ($absensiCount > 0 || $izinCount > 0) {
            $user->update(['is_active' => false]);
            return response()->json(['message' => 'Karyawan memiliki riwayat data, sehingga hanya dinonaktifkan (Soft Delete).']);
        }

        $user->delete();
        return response()->json(['message' => 'Karyawan berhasil dihapus permanen']);
    }

    /**
     * POST /api/admin/employees/import
     */
    public function importExcelEmployees(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'File Excel tidak ditemukan'], 400);
        }

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        $successCount = 0;
        foreach ($sheet->getRowIterator(2) as $row) { // Skip header
            $cells = [];
            foreach ($row->getCellIterator('A', 'J') as $cell) {
                $cells[] = $cell->getValue();
            }

            $nik = (string) ($cells[0] ?? '');
            $nama = (string) ($cells[1] ?? '');
            $email = (string) ($cells[2] ?? '');
            $jabatan = (string) ($cells[3] ?? '');
            $rawPassword = (string) ($cells[4] ?? '123456');
            $gajiPerhari = (int) ($cells[5] ?? 0);
            $hariKerja = (string) ($cells[6] ?? 'Senin,Selasa,Rabu,Kamis,Jumat');
            $jamMasukKerja = (string) ($cells[7] ?? '08:00');
            $jamKeluarKerja = (string) ($cells[8] ?? '17:00');
            $roleInput = strtoupper(trim((string) ($cells[9] ?? '')));
            $role = in_array($roleInput, ['ADMIN', 'KARYAWAN']) ? $roleInput : 'KARYAWAN';

            if (!$nik || !$nama || !$email) continue;

            try {
                $existing = User::withTrashed()
                    ->where('email', $email)
                    ->orWhere('nik', $nik)
                    ->first();

                $employeeData = [
                    'nik' => $nik,
                    'nama' => $nama,
                    'email' => $email,
                    'jabatan' => $jabatan,
                    'password' => Hash::make($rawPassword),
                    'gaji_perhari' => $gajiPerhari,
                    'hari_kerja' => $hariKerja,
                    'jam_masuk_kerja' => $jamMasukKerja,
                    'jam_keluar_kerja' => $jamKeluarKerja,
                    'role' => $role,
                    'is_active' => true,
                ];

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->update($employeeData);
                } else {
                    User::create($employeeData);
                }
                $successCount++;
            } catch (\Exception $e) {
                \Log::error("Gagal mengimpor karyawan {$email}: " . $e->getMessage());
            }
        }

        return response()->json(['message' => "Berhasil mengimpor {$successCount} data karyawan"]);
    }

    /**
     * POST /api/admin/employees/{id}/face
     */
    public function uploadFaceReference(Request $request, int $id)
    {
        $fotoWajah = $request->input('fotoWajah');

        if (!$fotoWajah) {
            return response()->json(['error' => 'Data foto wajah (base64) wajib dikirim.'], 400);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Karyawan tidak ditemukan'], 404);
        }

        // 1. Validasi wajah dengan Face++ Detect
        try {
            $faceService = new FacePlusPlusService();
            $faceDetected = $faceService->detectFace($fotoWajah);
            if (!$faceDetected) {
                return response()->json(['error' => 'Wajah tidak terdeteksi pada foto.'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage() ?: 'Gagal mendeteksi wajah'], 400);
        }

        // 2. Upload ke Cloudinary
        try {
            $cloudinary = new CloudinaryService();
            $fileUrl = $cloudinary->uploadBase64($fotoWajah, 'faces');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal menyimpan foto wajah ke cloud'], 500);
        }

        // 3. Simpan URL sebagai foto referensi
        $user->update([
            'foto_referensi' => $fileUrl,
            'foto_profil' => $fileUrl,
        ]);

        return response()->json(['message' => 'Foto referensi berhasil diperbarui', 'fileUrl' => $fileUrl]);
    }

    /**
     * GET /api/admin/salary-report
     */
    public function getSalaryReport(Request $request)
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $locationId = $request->query('locationId') ?? $request->query('master_lokasi_id');

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'startDate dan endDate wajib diisi'], 400);
        }

        try {
            $start = Carbon::parse($startDate)->startOfDay()->toDateString();
            $end = Carbon::parse($endDate)->endOfDay()->toDateString();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Format startDate atau endDate tidak valid.'], 400);
        }

        $query = User::whereIn('role', ['ADMIN', 'KARYAWAN']);
        if ($locationId) {
            $query->where('master_lokasi_id', (int) $locationId);
        }

        $users = $query->with(['absensi' => function ($q) use ($start, $end) {
                $q->where('tanggal', '>=', $start)
                  ->where('tanggal', '<=', $end);
            }])
            ->orderBy('nama')
            ->get();

        $reportData = $users->map(function ($user) {
            $totalHadir = 0;
            $totalTelat = 0;
            $totalIzin = 0;

            foreach ($user->absensi as $absen) {
                if (in_array($absen->status, ['TEPAT_WAKTU', 'TERLAMBAT'])) {
                    $totalHadir++;
                }
                if ($absen->status === 'TERLAMBAT') {
                    $totalTelat++;
                }
                if (in_array($absen->status, ['IZIN', 'SAKIT', 'CUTI'])) {
                    $totalIzin++;
                }
            }

            return [
                'id' => $user->id,
                'nik' => $user->nik,
                'nama' => $user->nama,
                'fotoProfil' => $user->foto_profil,
                'jabatan' => $user->jabatan,
                'gajiPerhari' => $user->gaji_perhari,
                'totalHadir' => $totalHadir,
                'totalTelat' => $totalTelat,
                'totalIzin' => $totalIzin,
                'totalGaji' => $totalHadir * $user->gaji_perhari,
            ];
        });

        return response()->json(['data' => $reportData]);
    }

    /**
     * GET /api/admin/export-salary
     */
    public function exportSalaryExcel(Request $request)
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $locationId = $request->query('locationId') ?? $request->query('master_lokasi_id');

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'startDate dan endDate wajib diisi'], 400);
        }

        try {
            $start = Carbon::parse($startDate)->startOfDay()->toDateString();
            $end = Carbon::parse($endDate)->endOfDay()->toDateString();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Format startDate atau endDate tidak valid.'], 400);
        }

        $query = User::whereIn('role', ['ADMIN', 'KARYAWAN']);
        if ($locationId) {
            $query->where('master_lokasi_id', (int) $locationId);
        }

        $users = $query->with(['absensi' => function ($q) use ($start, $end) {
                $q->where('tanggal', '>=', $start)
                  ->where('tanggal', '<=', $end);
            }])
            ->orderBy('nama')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Gaji');

        $sheet->setCellValue('A1', 'NIK');
        $sheet->setCellValue('B1', 'Nama');
        $sheet->setCellValue('C1', 'Gaji Perhari');
        $sheet->setCellValue('D1', 'Total Hadir');
        $sheet->setCellValue('E1', 'Total Telat');
        $sheet->setCellValue('F1', 'Total Izin');
        $sheet->setCellValue('G1', 'Total Gaji');

        $row = 2;
        foreach ($users as $user) {
            $totalHadir = 0;
            $totalTelat = 0;
            $totalIzin = 0;
            foreach ($user->absensi as $absen) {
                if (in_array($absen->status, ['TEPAT_WAKTU', 'TERLAMBAT'])) {
                    $totalHadir++;
                }
                if ($absen->status === 'TERLAMBAT') {
                    $totalTelat++;
                }
                if (in_array($absen->status, ['IZIN', 'SAKIT', 'CUTI'])) {
                    $totalIzin++;
                }
            }

            $sheet->setCellValue("A{$row}", $user->nik);
            $sheet->setCellValue("B{$row}", $user->nama);
            $sheet->setCellValue("C{$row}", $user->gaji_perhari);
            $sheet->setCellValue("D{$row}", $totalHadir);
            $sheet->setCellValue("E{$row}", $totalTelat);
            $sheet->setCellValue("F{$row}", $totalIzin);
            $sheet->setCellValue("G{$row}", $totalHadir * $user->gaji_perhari);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "laporan_gaji_{$start}_{$end}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * GET /api/admin/face-reverifications
     */
    public function getPendingFaceReverifications()
    {
        $users = User::where('face_reverification_status', 'PENDING')
            ->select('id', 'nik', 'nama', 'jabatan', 'foto_profil', 'face_reverification_status')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'nik' => $user->nik,
                    'nama' => $user->nama,
                    'jabatan' => $user->jabatan,
                    'fotoProfil' => $user->foto_profil,
                    'faceReverificationStatus' => $user->face_reverification_status,
                ];
            });

        return response()->json(['data' => $users]);
    }

    /**
     * POST /api/admin/face-reverifications/{id}/approve
     */
    public function approveFaceReverification(int $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Pengguna tidak ditemukan.'], 404);
        }
        $user->update(['face_reverification_status' => 'APPROVED']);
        return response()->json(['message' => 'Permintaan verifikasi wajah disetujui']);
    }

    /**
     * POST /api/admin/face-reverifications/{id}/reject
     */
    public function rejectFaceReverification(int $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Pengguna tidak ditemukan.'], 404);
        }
        $user->update(['face_reverification_status' => 'NONE']);
        return response()->json(['message' => 'Permintaan verifikasi wajah ditolak']);
    }

    /**
     * GET /api/admin/employees/next-nik
     */
    public function getNextNik()
    {
        $now = Carbon::now('Asia/Jakarta');
        $prefix = $now->format('my');
        $latestUser = User::withTrashed()->where('nik', 'like', $prefix . '%')->whereRaw('LENGTH(nik) = 7')->orderBy('nik', 'desc')->first();
        $nextCounter = $latestUser ? ((int) substr($latestUser->nik, 4)) + 1 : 1;
        $nextNik = $prefix . str_pad($nextCounter, 3, '0', STR_PAD_LEFT);
        return response()->json(['nik' => $nextNik]);
    }

    /**
     * GET /api/admin/employees/{id}
     */
    public function getEmployeeById(int $id)
    {
        $user = User::whereIn('role', ['ADMIN', 'KARYAWAN'])->find($id);
        if (!$user) {
            return response()->json(['error' => 'Karyawan tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Detail karyawan berhasil diambil',
            'data' => $this->formatEmployee($user),
        ]);
    }

    /**
     * Format employee for JSON response (camelCase keys for Android compatibility)
     */
    private function formatEmployee(User $emp): array
    {
        return [
            'id' => $emp->id,
            'nik' => $emp->nik,
            'nama' => $emp->nama,
            'email' => $emp->email,
            'jabatan' => $emp->jabatan ?? '',
            'role' => $emp->role,
            'isActive' => $emp->is_active,
            'gajiPerhari' => $emp->gaji_perhari,
            'hariKerja' => $emp->hari_kerja,
            'jamMasukKerja' => $emp->jam_masuk_kerja,
            'jamKeluarKerja' => $emp->jam_keluar_kerja,
            'fotoProfil' => $emp->foto_profil,
            'masterLokasiId' => $emp->master_lokasi_id,
            'masterLokasi' => $emp->lokasi ? [
                'id' => $emp->lokasi->id,
                'namaPlace' => $emp->lokasi->nama_place,
                'tipe' => $emp->lokasi->tipe,
                'latitude' => $emp->lokasi->latitude,
                'longitude' => $emp->lokasi->longitude,
                'radius' => $emp->lokasi->radius,
            ] : null,
        ];
    }
}
