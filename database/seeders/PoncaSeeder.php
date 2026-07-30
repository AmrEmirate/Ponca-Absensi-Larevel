<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\Izin;
use App\Models\MasterLokasi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PoncaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable Foreign Key checks before truncation
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Absensi::truncate();
        Izin::truncate();
        MasterLokasi::truncate();
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. Seed Master Lokasi (Pabrik, Outlets & Area Pemasaran)
        $pabrikHq = MasterLokasi::create([
            'id' => 1,
            'nama_place' => 'PONCA FOOD HQ (Pabrik Utama)',
            'tipe' => 'PABRIK',
            'alamat' => 'Jl. Industri Food No. 1, Jakarta',
            'latitude' => -6.200000,
            'longitude' => 106.816667,
            'radius' => 500.0,
            'is_active' => true,
        ]);

        $outletDepok = MasterLokasi::create([
            'id' => 2,
            'nama_place' => 'Outlet Depok',
            'tipe' => 'OUTLET',
            'alamat' => 'Jl. Margonda Raya No. 45, Depok',
            'latitude' => -6.402484,
            'longitude' => 106.794241,
            'radius' => 100.0,
            'is_active' => true,
        ]);

        $outletKarawang = MasterLokasi::create([
            'id' => 3,
            'nama_place' => 'Outlet Karawang',
            'tipe' => 'OUTLET',
            'alamat' => 'Jl. Galuh Mas Raya No. 12, Karawang',
            'latitude' => -6.306111,
            'longitude' => 107.300556,
            'radius' => 100.0,
            'is_active' => true,
        ]);

        $areaPemasaran = MasterLokasi::create([
            'id' => 4,
            'nama_place' => 'Area Pemasaran Jabodetabek',
            'tipe' => 'AREA_PEMASARAN',
            'alamat' => 'Wilayah Pemasaran Field Sales Jabodetabek',
            'latitude' => -6.200000,
            'longitude' => 106.816667,
            'radius' => 5000.0, // 5 KM
            'is_active' => true,
        ]);

        // 2. Helper NIK Generator
        $generateNik = function (string $prefix, int $counter) {
            return $prefix . str_pad((string)$counter, 3, '0', STR_PAD_LEFT);
        };

        // 3. Seed User ADMIN
        $admin1 = User::create([
            'nik' => $generateNik('ADM', 1), // ADM001
            'email' => 'admin@poncafood.com',
            'nama' => 'Admin Ponca Food',
            'jabatan' => 'Admin Sistem',
            'password' => Hash::make('admin123'),
            'role' => 'ADMIN',
            'is_active' => true,
            'gaji_perhari' => 200000,
            'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
            'jam_masuk_kerja' => '08:00',
            'jam_keluar_kerja' => '17:00',
            'master_lokasi_id' => $pabrikHq->id,
            'face_reverification_status' => 'NONE',
        ]);

        $admin2 = User::create([
            'nik' => $generateNik('ADM', 2), // ADM002
            'email' => 'amremirate03@gmail.com',
            'nama' => 'Amr Emirate',
            'jabatan' => 'Administrator Utama',
            'password' => Hash::make('admin123'),
            'role' => 'ADMIN',
            'is_active' => true,
            'gaji_perhari' => 200000,
            'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
            'jam_masuk_kerja' => '08:00',
            'jam_keluar_kerja' => '17:00',
            'master_lokasi_id' => $pabrikHq->id,
            'face_reverification_status' => 'NONE',
        ]);

        // 4. Seed User SCANNER (Tablet Kiosk per Lokasi)
        $scannerHQ = User::create([
            'nik' => $generateNik('SCN', 1), // SCN001
            'email' => 'scanner.hq@poncafood.com',
            'nama' => 'Scanner Pabrik HQ',
            'jabatan' => 'Scanner Kiosk Pabrik',
            'password' => Hash::make('scanner123'),
            'role' => 'SCANNER',
            'is_active' => true,
            'gaji_perhari' => 0,
            'hari_kerja' => '-',
            'jam_masuk_kerja' => '-',
            'jam_keluar_kerja' => '-',
            'master_lokasi_id' => $pabrikHq->id,
            'face_reverification_status' => 'NONE',
        ]);

        $scannerDepok = User::create([
            'nik' => $generateNik('SCN', 2), // SCN002
            'email' => 'scanner.depok@poncafood.com',
            'nama' => 'Scanner Outlet Depok',
            'jabatan' => 'Scanner Kiosk Depok',
            'password' => Hash::make('scanner123'),
            'role' => 'SCANNER',
            'is_active' => true,
            'gaji_perhari' => 0,
            'hari_kerja' => '-',
            'jam_masuk_kerja' => '-',
            'jam_keluar_kerja' => '-',
            'master_lokasi_id' => $outletDepok->id,
            'face_reverification_status' => 'NONE',
        ]);

        $scannerKarawang = User::create([
            'nik' => $generateNik('SCN', 3), // SCN003
            'email' => 'scanner.karawang@poncafood.com',
            'nama' => 'Scanner Outlet Karawang',
            'jabatan' => 'Scanner Kiosk Karawang',
            'password' => Hash::make('scanner123'),
            'role' => 'SCANNER',
            'is_active' => true,
            'gaji_perhari' => 0,
            'hari_kerja' => '-',
            'jam_masuk_kerja' => '-',
            'jam_keluar_kerja' => '-',
            'master_lokasi_id' => $outletKarawang->id,
            'face_reverification_status' => 'NONE',
        ]);

        // 5. Seed User KARYAWAN
        $dummyKaryawanList = [
            ['nama' => 'Budi Santoso', 'jabatan' => 'Staff Produksi', 'email' => 'budi@poncafood.com', 'lokasi_id' => $pabrikHq->id, 'gaji' => 120000],
            ['nama' => 'Siti Aminah', 'jabatan' => 'Quality Control', 'email' => 'siti@poncafood.com', 'lokasi_id' => $pabrikHq->id, 'gaji' => 125000],
            ['nama' => 'Andi Wijaya', 'jabatan' => 'Staf Gudang', 'email' => 'andi@poncafood.com', 'lokasi_id' => $outletDepok->id, 'gaji' => 110000],
            ['nama' => 'Dewi Lestari', 'jabatan' => 'Kasir Outlet', 'email' => 'dewi@poncafood.com', 'lokasi_id' => $outletDepok->id, 'gaji' => 115000],
            ['nama' => 'Rian Hidayat', 'jabatan' => 'Staff Packing', 'email' => 'rian@poncafood.com', 'lokasi_id' => $outletKarawang->id, 'gaji' => 110000],
            ['nama' => 'Eko Prasetyo', 'jabatan' => 'Tim Pemasaran', 'email' => 'eko@poncafood.com', 'lokasi_id' => $areaPemasaran->id, 'gaji' => 130000],
        ];

        $createdUsers = [];
        foreach ($dummyKaryawanList as $index => $karyawan) {
            $u = User::create([
                'nik' => $generateNik('KRY', $index + 1), // KRY001, KRY002, dst.
                'email' => $karyawan['email'],
                'nama' => $karyawan['nama'],
                'jabatan' => $karyawan['jabatan'],
                'password' => Hash::make('karyawan123'),
                'role' => 'KARYAWAN',
                'is_active' => true,
                'gaji_perhari' => $karyawan['gaji'],
                'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
                'jam_masuk_kerja' => '08:00',
                'jam_keluar_kerja' => '17:00',
                'master_lokasi_id' => $karyawan['lokasi_id'],
                'face_reverification_status' => 'NONE',
            ]);
            $createdUsers[] = $u;
        }

        // 6. Seed 7 Hari Riwayat Absensi & Izin (7 hari ke belakang)
        $nowWib = Carbon::now('Asia/Jakarta');
        
        for ($i = 6; $i >= 0; $i--) {
            $date = $nowWib->copy()->subDays($i);
            $dateStr = $date->toDateString();

            foreach ($createdUsers as $userIdx => $user) {
                // Lokasi koordinat dummy dekat lokasi master
                $lokasi = MasterLokasi::find($user->master_lokasi_id);
                $lat = $lokasi ? $lokasi->latitude : -6.200000;
                $lng = $lokasi ? $lokasi->longitude : 106.816667;

                // Hari ke-2 lalu: Andi izin sakit
                if ($i === 2 && $user->email === 'andi@poncafood.com') {
                    Izin::create([
                        'user_id' => $user->id,
                        'jenis_izin' => 'Sakit',
                        'deskripsi' => 'Demam tinggi dan flu butuh istirahat dokter',
                        'foto_url' => null,
                        'status' => 'APPROVED',
                        'tanggal' => $dateStr,
                    ]);
                    continue;
                }

                // Hari ke-4 lalu: Eko izin dinas pemasaran luar
                if ($i === 4 && $user->email === 'eko@poncafood.com') {
                    Izin::create([
                        'user_id' => $user->id,
                        'jenis_izin' => 'Izin',
                        'deskripsi' => 'Kunjungan prospecting klien di luar area Jabodetabek',
                        'foto_url' => null,
                        'status' => 'APPROVED',
                        'tanggal' => $dateStr,
                    ]);
                    continue;
                }

                // Hari ini ($i === 0): Sebagian karyawan sudah absen masuk, sebagian belum absen keluar
                if ($i === 0) {
                    if ($userIdx % 2 === 0) {
                        $waktuMasuk = Carbon::parse("{$dateStr} 07:50:00", 'Asia/Jakarta');
                        Absensi::create([
                            'user_id' => $user->id,
                            'scanner_id' => $user->master_lokasi_id === 2 ? $scannerDepok->id : $scannerHQ->id,
                            'tanggal' => $dateStr,
                            'waktu_masuk' => $waktuMasuk,
                            'waktu_keluar' => null,
                            'foto_masuk' => null,
                            'foto_keluar' => null,
                            'status' => 'TEPAT_WAKTU',
                            'lat_masuk' => $lat,
                            'lng_masuk' => $lng,
                            'master_lokasi_id' => $user->master_lokasi_id,
                        ]);
                    }
                    continue;
                }

                // Hari biasa (1 s/d 6 hari yang lalu): Absen masuk & keluar lengkap
                $minuteOffset = rand(-10, 20); // antara 07:50 sampai 08:20
                $waktuMasuk = Carbon::parse("{$dateStr} 08:00:00", 'Asia/Jakarta')->addMinutes($minuteOffset);
                $waktuKeluar = Carbon::parse("{$dateStr} 17:00:00", 'Asia/Jakarta')->addMinutes(rand(0, 30));
                $status = $minuteOffset > 0 ? 'TERLAMBAT' : 'TEPAT_WAKTU';

                Absensi::create([
                    'user_id' => $user->id,
                    'scanner_id' => $user->master_lokasi_id === 2 ? $scannerDepok->id : ($user->master_lokasi_id === 3 ? $scannerKarawang->id : $scannerHQ->id),
                    'tanggal' => $dateStr,
                    'waktu_masuk' => $waktuMasuk,
                    'waktu_keluar' => $waktuKeluar,
                    'foto_masuk' => null,
                    'foto_keluar' => null,
                    'status' => $status,
                    'lat_masuk' => $lat,
                    'lng_masuk' => $lng,
                    'master_lokasi_id' => $user->master_lokasi_id,
                ]);
            }
        }

        // 7. Tambahan 1 Izin PENDING untuk pengetesan Admin approval
        Izin::create([
            'user_id' => $createdUsers[1]->id, // Siti Aminah
            'jenis_izin' => 'Izin',
            'deskripsi' => 'Keperluan keluarga mendesak',
            'foto_url' => null,
            'status' => 'PENDING',
            'tanggal' => $nowWib->toDateString(),
        ]);
    }
}
