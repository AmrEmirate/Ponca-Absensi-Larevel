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

        // 1. Seed Master Lokasi (Pabrik Utama)
        $pabrikHq = MasterLokasi::create([
            'id' => 1,
            'nama_place' => 'Pabrik Utama',
            'tipe' => 'PABRIK',
            'alamat' => 'Jl. Industri Food No. 1, Jakarta',
            'latitude' => -6.200000,
            'longitude' => 106.816667,
            'radius' => 500.0,
            'is_active' => true,
        ]);

        // 2. Generate NIK otomatis format Bulan-Tahun-Urutan (misal: 0826001 / 1126001) dan password default
        $now = Carbon::now('Asia/Jakarta');
        $prefix = $now->format('my'); // Format bulan & tahun (misal: 0826)
        $counter = 1;
        $nikSuffix = str_pad((string)$counter, 3, '0', STR_PAD_LEFT); // '001'
        $nik = $prefix . $nikSuffix; // '0826001' (atau '1126001')
        $defaultPassword = 'PoncaAbsensi' . $nikSuffix; // 'PoncaAbsensi001'

        // 3. Seed User ADMIN
        User::create([
            'nik' => $nik,
            'email' => 'amremirate03@gmail.com',
            'nama' => 'Amr Emirate',
            'jabatan' => 'Admin Utama',
            'password' => Hash::make($defaultPassword),
            'role' => 'ADMIN',
            'is_active' => true,
            'gaji_perhari' => 200000,
            'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'jam_masuk_kerja' => '08:00',
            'jam_keluar_kerja' => '17:00',
            'master_lokasi_id' => $pabrikHq->id,
            'face_reverification_status' => 'NONE',
        ]);

        // 4. Seed 1 Dummy Karyawan (Budi Santoso) dengan Data Absensi & Gaji 1 Bulan
        $karyawanNikSuffix = '002';
        $karyawanNik = $prefix . $karyawanNikSuffix;
        $karyawan = User::create([
            'nik' => $karyawanNik,
            'email' => 'budi.santoso@poncafood.com',
            'nama' => 'Budi Santoso',
            'jabatan' => 'Staff Produksi',
            'password' => Hash::make('PoncaAbsensi002'),
            'role' => 'KARYAWAN',
            'is_active' => true,
            'gaji_perhari' => 150000,
            'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'jam_masuk_kerja' => '08:00',
            'jam_keluar_kerja' => '17:00',
            'master_lokasi_id' => $pabrikHq->id,
            'face_reverification_status' => 'APPROVED',
        ]);

        // Seed Absensi 1 Bulan Terakhir (30 Hari ke Belakang)
        $today = Carbon::now('Asia/Jakarta');
        for ($i = 30; $i >= 1; $i--) {
            $date = $today->copy()->subDays($i);
            
            // Skip hari Minggu
            if ($date->isSunday()) {
                continue;
            }

            $dateStr = $date->format('Y-m-d');
            
            // Hari ke-5 dan 15: Izin
            if ($i == 5 || $i == 15) {
                Izin::create([
                    'user_id' => $karyawan->id,
                    'tanggal' => $dateStr,
                    'kategori' => 'SAKIT',
                    'alasan' => 'Demam dan flu ringan',
                    'status' => 'APPROVED',
                ]);
                Absensi::create([
                    'user_id' => $karyawan->id,
                    'master_lokasi_id' => $pabrikHq->id,
                    'tanggal' => $dateStr,
                    'waktu_masuk' => null,
                    'waktu_keluar' => null,
                    'status' => 'IZIN',
                    'keterangan_izin' => 'Demam dan flu ringan',
                ]);
            } 
            // Hari ke-8 dan 22: Terlambat
            elseif ($i == 8 || $i == 22) {
                Absensi::create([
                    'user_id' => $karyawan->id,
                    'master_lokasi_id' => $pabrikHq->id,
                    'tanggal' => $dateStr,
                    'waktu_masuk' => Carbon::parse("$dateStr 08:25:00"),
                    'waktu_keluar' => Carbon::parse("$dateStr 17:05:00"),
                    'lat_masuk' => -6.200000,
                    'lng_masuk' => 106.816667,
                    'status' => 'TERLAMBAT',
                ]);
            }
            // Hari ke-12: Alpa (Tidak Ada Presensi)
            elseif ($i == 12) {
                // Jangan buat record absensi untuk mensimulasikan Alpa
            }
            // Hari lainnya: Tepat Waktu
            else {
                Absensi::create([
                    'user_id' => $karyawan->id,
                    'master_lokasi_id' => $pabrikHq->id,
                    'tanggal' => $dateStr,
                    'waktu_masuk' => Carbon::parse("$dateStr 07:50:00"),
                    'waktu_keluar' => Carbon::parse("$dateStr 17:00:00"),
                    'lat_masuk' => -6.200000,
                    'lng_masuk' => 106.816667,
                    'status' => 'TEPAT_WAKTU',
                ]);
            }
        }
    }
}

