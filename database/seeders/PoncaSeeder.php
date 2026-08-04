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

        // 1. Seed Master Lokasi
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

        $outletKebayoran = MasterLokasi::create([
            'id' => 2,
            'nama_place' => 'Outlet Kebayoran',
            'tipe' => 'OUTLET',
            'alamat' => 'Jl. Kebayoran Baru No. 12, Jakarta Selatan',
            'latitude' => -6.240000,
            'longitude' => 106.780000,
            'radius' => 150.0,
            'is_active' => true,
        ]);

        // 2. Generate Prefix NIK (Bulan-Tahun)
        $now = Carbon::now('Asia/Jakarta');
        $prefix = $now->format('my'); // e.g. 0826

        // 3. Seed User ADMIN (Admin Utama)
        $nikAdmin = $prefix . '001';
        User::create([
            'nik' => $nikAdmin,
            'email' => 'amremirate03@gmail.com',
            'nama' => 'Amr Emirate',
            'jabatan' => 'Admin Utama',
            'password' => Hash::make('PoncaAbsensi001'),
            'role' => 'ADMIN',
            'is_active' => true,
            'gaji_perhari' => 200000,
            'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
            'jam_masuk_kerja' => '08:00',
            'jam_keluar_kerja' => '17:00',
            'master_lokasi_id' => $pabrikHq->id,
            'face_reverification_status' => 'NONE',
        ]);

        // 4. Seed User SCANNER (Perangkat Pemindai Pabrik Utama)
        $nikScanner = $prefix . '002';
        User::create([
            'nik' => $nikScanner,
            'email' => 'scanner_pabrik1',
            'nama' => 'Scanner Pabrik Utama',
            'jabatan' => 'Scanner',
            'password' => Hash::make('PoncaAbsensi002'),
            'role' => 'SCANNER',
            'is_active' => true,
            'gaji_perhari' => 0,
            'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'jam_masuk_kerja' => '08:00',
            'jam_keluar_kerja' => '17:00',
            'master_lokasi_id' => $pabrikHq->id,
            'face_reverification_status' => 'NONE',
        ]);

        // 5. Seed User KARYAWAN 1 (Budi Santoso)
        $nikBudi = $prefix . '003';
        User::create([
            'nik' => $nikBudi,
            'email' => 'budi@gmail.com',
            'nama' => 'Budi Santoso',
            'jabatan' => 'Operator Produksi',
            'password' => Hash::make('PoncaAbsensi003'),
            'role' => 'KARYAWAN',
            'is_active' => true,
            'gaji_perhari' => 150000,
            'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
            'jam_masuk_kerja' => '08:00',
            'jam_keluar_kerja' => '17:00',
            'master_lokasi_id' => $pabrikHq->id,
            'face_reverification_status' => 'NONE',
        ]);

        // 6. Seed User KARYAWAN 2 (Siti Rahma)
        $nikSiti = $prefix . '004';
        User::create([
            'nik' => $nikSiti,
            'email' => 'siti@gmail.com',
            'nama' => 'Siti Rahma',
            'jabatan' => 'Staff Packhouse',
            'password' => Hash::make('PoncaAbsensi004'),
            'role' => 'KARYAWAN',
            'is_active' => true,
            'gaji_perhari' => 150000,
            'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
            'jam_masuk_kerja' => '08:00',
            'jam_keluar_kerja' => '17:00',
            'master_lokasi_id' => $outletKebayoran->id,
            'face_reverification_status' => 'NONE',
        ]);
    }
}
