<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\Izin;
use App\Models\MasterLokasi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

        // 1. Seed Master Lokasi (Pabrik Utama 1)
        $pabrikHq = MasterLokasi::create([
            'id' => 1,
            'nama_place' => 'PONCA FOOD HQ (Pabrik Utama 1)',
            'tipe' => 'PABRIK',
            'alamat' => 'Jl. Industri Food No. 1, Jakarta',
            'latitude' => -6.200000,
            'longitude' => 106.816667,
            'radius' => 500.0,
            'is_active' => true,
        ]);

        // 2. Seed User ADMIN (Admin Pabrik Utama 1)
        User::create([
            'nik' => 'ADM001',
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
    }
}

