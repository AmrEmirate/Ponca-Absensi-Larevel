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

        // 1. Seed Master Lokasi (Pabrik Utama saja)
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

        // 2. Generate Prefix NIK (Bulan-Tahun)
        $now = Carbon::now('Asia/Jakarta');
        $prefix = $now->format('my'); // e.g. 0826
        $counter = 1;
        $nikSuffix = str_pad((string)$counter, 3, '0', STR_PAD_LEFT); // '001'
        $nik = $prefix . $nikSuffix;
        $defaultPassword = 'PoncaAbsensi' . $nikSuffix; // 'PoncaAbsensi001'

        // 3. Seed User ADMIN saja (Admin Utama)
        User::create([
            'nik' => $nik,
            'email' => 'amremirate03@gmail.com',
            'nama' => 'Amr Emirate',
            'jabatan' => 'Admin Utama',
            'password' => Hash::make($defaultPassword),
            'role' => 'ADMIN',
            'is_active' => true,
            'gaji_perhari' => 200000,
            'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
            'jam_masuk_kerja' => '08:00',
            'jam_keluar_kerja' => '17:00',
            'master_lokasi_id' => $pabrikHq->id,
            'face_reverification_status' => 'NONE',
        ]);
    }
}
