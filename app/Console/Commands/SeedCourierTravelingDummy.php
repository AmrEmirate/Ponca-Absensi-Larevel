<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\CourierAssignment;
use App\Models\CourierLocation;
use App\Models\StoreVisit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class SeedCourierTravelingDummy extends Command
{
    protected $signature = 'courier:seed-traveling';
    protected $description = 'Membuat data dummy kurir yang sedang aktif melakukan perjalanan pengantaran di peta';

    public function handle()
    {
        $this->info('Membuat data dummy kurir aktif melakukan perjalanan...');

        // 1. Dapatkan atau buat User Kurir
        $courier = User::where('role', 'KURIR')->first();
        if (!$courier) {
            $courier = User::where('email', 'kurir@poncafood.com')->first();
        }
        if (!$courier) {
            $courier = User::create([
                'name' => 'Budi Pratama (Kurir)',
                'email' => 'kurir@poncafood.com',
                'password' => Hash::make('password'),
                'role' => 'KURIR',
                'jabatan' => 'Kurir Pengantaran',
                'is_active' => true,
            ]);
        } else {
            $courier->update(['role' => 'KURIR', 'jabatan' => 'Kurir']);
        }

        $this->info("Kurir: {$courier->name} ({$courier->email})");

        // 2. Dapatkan atau buat Master Rute
        $route = Route::with('stops')->first();
        if (!$route || $route->stops->count() < 2) {
            $route = Route::create([
                'route_code' => 'RUTE-CKR-01',
                'route_name' => 'Jalur Cikarang (Toko Berkah ➔ Toko Sejahtera)',
                'area_name' => 'Cikarang Barat',
                'is_active' => true,
            ]);

            $route->stops()->createMany([
                [
                    'store_name' => 'Toko Berkah Cikarang',
                    'address' => 'Jl. Industri No. 12, Cikarang',
                    'latitude' => -6.2954,
                    'longitude' => 107.1350,
                    'sequence_order' => 1,
                    'radius_tolerance_meters' => 100,
                ],
                [
                    'store_name' => 'Toko Sejahtera Makmur',
                    'address' => 'Jl. Raya Lemahabang No. 45, Cikarang',
                    'latitude' => -6.2750,
                    'longitude' => 107.1650,
                    'sequence_order' => 2,
                    'radius_tolerance_meters' => 100,
                ],
                [
                    'store_name' => 'Toko Sumber Rejeki',
                    'address' => 'Jl. Cibarusah Raya No. 88, Cikarang',
                    'latitude' => -6.2550,
                    'longitude' => 107.1850,
                    'sequence_order' => 3,
                    'radius_tolerance_meters' => 100,
                ],
            ]);
            $route->load('stops');
        }

        $stops = $route->stops()->orderBy('sequence_order')->get();
        $stop1 = $stops[0];
        $stop2 = $stops[1];

        // 3. Buat Penugasan Hari Ini (in_progress)
        $today = Carbon::today()->toDateString();
        $assignment = CourierAssignment::updateOrCreate(
            [
                'user_id' => $courier->id,
                'assignment_date' => $today,
            ],
            [
                'route_id' => $route->id,
                'status' => 'in_progress',
                'started_at' => Carbon::now()->subMinutes(40),
                'completed_at' => null,
            ]
        );

        // 4. Catat Check-in di Toko 1 (Sudah selesai dikunjungi)
        StoreVisit::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'route_stop_id' => $stop1->id,
            ],
            [
                'checkin_time' => Carbon::now()->subMinutes(25),
                'checkout_time' => Carbon::now()->subMinutes(15),
                'notes' => 'Toko sudah menerima pesanan dengan baik',
            ]
        );

        // 5. Hapus koordinat lama untuk penugasan ini dan buat lintasan GPS baru
        CourierLocation::where('assignment_id', $assignment->id)->delete();

        // Buat 15 titik interpolasi dari Toko 1 menuju Toko 2
        $numPoints = 15;
        $startLat = (float) $stop1->latitude;
        $startLng = (float) $stop1->longitude;
        $endLat = (float) $stop2->latitude;
        $endLng = (float) $stop2->longitude;

        // Kurir saat ini berada di 70% perjalanan menuju Toko 2
        $currentProgress = 0.70;

        for ($i = 0; $i < $numPoints; $i++) {
            $t = ($i / ($numPoints - 1)) * $currentProgress;
            // Tambahkan sedikit jitter/variasi jalan alami
            $jitterLat = sin($i * 0.8) * 0.0008;
            $jitterLng = cos($i * 0.8) * 0.0008;

            $lat = $startLat + ($endLat - $startLat) * $t + $jitterLat;
            $lng = $startLng + ($endLng - $startLng) * $t + $jitterLng;

            $minutesAgo = (int) round((1 - ($i / ($numPoints - 1))) * 20); // 20 menit lalu s/d 1 menit lalu
            $recordedTime = Carbon::now()->subMinutes($minutesAgo);

            CourierLocation::create([
                'assignment_id' => $assignment->id,
                'user_id' => $courier->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'speed' => $i === $numPoints - 1 ? 38.5 : rand(25, 45),
                'battery_level' => 85 - (int) ($i * 0.3),
                'recorded_at' => $recordedTime,
                'synced_at' => Carbon::now(),
            ]);
        }

        $this->info("✅ Berhasil membuat data dummy kurir aktif!");
        $this->info("- Kurir: {$courier->name}");
        $this->info("- Rute: {$route->route_name}");
        $this->info("- Status: in_progress (Sedang di jalan)");
        $this->info("- Toko Dikunjungi: 1 / {$stops->count()}");
        $this->info("- Titik GPS Aktif: {$numPoints} titik koordinat");

        return 0;
    }
}
