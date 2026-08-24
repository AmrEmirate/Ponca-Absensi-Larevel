<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\Izin;
use App\Models\MasterLokasi;
use App\Models\Product;
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
        // 1. Seed Master Lokasi (Gunakan firstOrCreate agar tidak mereset data lokasi)
        $pabrikHq = MasterLokasi::firstOrCreate(
            ['id' => 1],
            [
                'nama_place' => 'Pabrik Utama',
                'tipe' => 'PABRIK',
                'alamat' => 'Jl. Industri Food No. 1, Jakarta',
                'latitude' => -6.200000,
                'longitude' => 106.816667,
                'radius' => 500.0,
                'is_active' => true,
            ]
        );

        // 2. Generate Prefix NIK (Bulan-Tahun)
        $now = Carbon::now('Asia/Jakarta');
        $prefix = $now->format('my'); // e.g. 0826
        $counter = 1;
        $nikSuffix = str_pad((string)$counter, 3, '0', STR_PAD_LEFT); // '001'
        $nik = $prefix . $nikSuffix;
        $defaultPassword = 'PoncaAbsensi' . $nikSuffix; // 'PoncaAbsensi001'

        // 3. Seed User ADMIN (Gunakan updateOrCreate tanpa mereset foto_referensi yang sudah ada)
        $admin = User::where('email', 'amremirate03@gmail.com')->first();
        if ($admin) {
            $admin->update([
                'nik' => $nik,
                'nama' => 'Amr Emirate',
                'jabatan' => 'Admin Utama',
                'role' => 'ADMIN',
                'is_active' => true,
                'gaji_perhari' => 75000,
                'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
                'jam_masuk_kerja' => '08:00',
                'jam_keluar_kerja' => '17:00',
                'master_lokasi_id' => $pabrikHq->id,
            ]);
        } else {
            User::create([
                'nik' => $nik,
                'email' => 'amremirate03@gmail.com',
                'nama' => 'Amr Emirate',
                'jabatan' => 'Admin Utama',
                'password' => Hash::make($defaultPassword),
                'role' => 'ADMIN',
                'is_active' => true,
                'gaji_perhari' => 75000,
                'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
                'jam_masuk_kerja' => '08:00',
                'jam_keluar_kerja' => '17:00',
                'master_lokasi_id' => $pabrikHq->id,
                'face_reverification_status' => 'NONE',
            ]);
        }

        // 4. Seed User DUMMY SALES (Untuk Web POS Ponca Sales)
        $salesEmail = 'sales@poncafood.com';
        $salesPassword = 'PoncaSales';
        $salesNik = 'SALES001';

        $sales = User::where('email', $salesEmail)
            ->orWhere('email', 'seller@poncafood.com')
            ->orWhere('nik', $salesNik)
            ->first();

        if ($sales) {
            $sales->update([
                'name' => 'Sales Dummy Ponca',
                'nama' => 'Sales Dummy Ponca',
                'nik' => $salesNik,
                'email' => $salesEmail,
                'password' => Hash::make($salesPassword),
                'jabatan' => 'Sales',
                'role' => 'SALLER',
                'location' => 'Jakarta Selatan',
                'status' => 'Active',
                'is_active' => true,
                'must_change_password' => false,
                'gaji_perhari' => 75000,
                'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
                'jam_masuk_kerja' => '08:00',
                'jam_keluar_kerja' => '17:00',
                'master_lokasi_id' => $pabrikHq->id,
            ]);
        } else {
            User::create([
                'name' => 'Sales Dummy Ponca',
                'nama' => 'Sales Dummy Ponca',
                'nik' => $salesNik,
                'email' => $salesEmail,
                'password' => Hash::make($salesPassword),
                'jabatan' => 'Sales',
                'role' => 'SALLER',
                'location' => 'Jakarta Selatan',
                'status' => 'Active',
                'is_active' => true,
                'must_change_password' => false,
                'gaji_perhari' => 75000,
                'hari_kerja' => 'Senin,Selasa,Rabu,Kamis,Jumat',
                'jam_masuk_kerja' => '08:00',
                'jam_keluar_kerja' => '17:00',
                'master_lokasi_id' => $pabrikHq->id,
                'face_reverification_status' => 'NONE',
            ]);
        }

        // 5. Seed Produk Master Ponca Food & Raja Laris (Sesuai Harga Agen Resmi)
        $products = [
            [
                'item_code' => 'PRD-MTZCZG',
                'name' => 'Dimsum Raja Laris',
                'category' => 'Food',
                'unit_price' => 23500,
                'stock' => 999999,
                'weight' => 500,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787191433/ponca_product_images/yodyee1meupft84z2sea.png',
            ],
            [
                'item_code' => 'PRD-586WCE',
                'name' => 'Chicken Cake Roll',
                'category' => 'Food',
                'unit_price' => 26000,
                'stock' => 999999,
                'weight' => 500,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787191466/ponca_product_images/qcwp9kwbmd0aqyo89jh4.png',
            ],
            [
                'item_code' => 'PRD-7874FD',
                'name' => 'Tahu Bakso',
                'category' => 'Food',
                'unit_price' => 22500,
                'stock' => 999999,
                'weight' => 500,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787191475/ponca_product_images/sjttu0ywwqqcnufoljn9.png',
            ],
            [
                'item_code' => 'PRD-OVNGGO',
                'name' => 'Otak Otak',
                'category' => 'Food',
                'unit_price' => 22000,
                'stock' => 999999,
                'weight' => 500,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787191482/ponca_product_images/utpsoy4weq2g2pnognwp.png',
            ],
            [
                'item_code' => 'PRD-YQDEHX',
                'name' => 'Siomay Raja Laris',
                'category' => 'Food',
                'unit_price' => 20500,
                'stock' => 999999,
                'weight' => 500,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787191498/ponca_product_images/wi8bsmftvktffow6b34h.png',
            ],
            [
                'item_code' => 'PRD-M3DKE1',
                'name' => 'Dimsum Goreng Keju',
                'category' => 'Food',
                'unit_price' => 23500,
                'stock' => 999999,
                'weight' => 400,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787191516/ponca_product_images/c9cvr2nit4i5fu0cs1bo.png',
            ],
            [
                'item_code' => 'PRD-A8XJMK',
                'name' => 'Dimsum',
                'category' => 'Food',
                'unit_price' => 26000,
                'stock' => 999999,
                'weight' => 500,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787191524/ponca_product_images/xwyvcyb0oxlh79abwytp.png',
            ],
            [
                'item_code' => 'PRD-JMUFGB',
                'name' => 'Kekian Ikan',
                'category' => 'Food',
                'unit_price' => 26000,
                'stock' => 999999,
                'weight' => 500,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787191531/ponca_product_images/flzmehy0emvvath7gswg.png',
            ],
            [
                'item_code' => 'PRD-27ETXZ',
                'name' => 'Siomay Ikan',
                'category' => 'Food',
                'unit_price' => 23500,
                'stock' => 999999,
                'weight' => 500,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787191538/ponca_product_images/habvc3gszojsjw9umrnx.png',
            ],
            [
                'item_code' => 'PRD-EGGROL',
                'name' => 'Egg Roll',
                'category' => 'Food',
                'unit_price' => 17500,
                'stock' => 999999,
                'weight' => 300,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787277045/ponca_product_images/eqzmq06yxuwll5g0htcd.png',
            ],
            [
                'item_code' => 'PRD-DSP1KG',
                'name' => 'Dimsum Ponca 1kg',
                'category' => 'Food',
                'unit_price' => 51000,
                'stock' => 999999,
                'weight' => 1000,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787536870/ponca_product_images/lfrgrje145equ0n6esf8.png',
            ],
            [
                'item_code' => 'PRD-DRL1KG',
                'name' => 'Dimsum Raja Laris 1kg',
                'category' => 'Food',
                'unit_price' => 45000,
                'stock' => 999999,
                'weight' => 1000,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787536356/ponca_product_images/un2k3ob4zckgdc3qo4us.png',
            ],
            [
                'item_code' => 'PRD-DAP250',
                'name' => 'Dimsum Ayam Ponca 250 gr',
                'category' => 'Food',
                'unit_price' => 16000,
                'stock' => 999999,
                'weight' => 250,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787536361/ponca_product_images/kflsld46wowyusts7lts.png',
            ],
            [
                'item_code' => 'PRD-SIP250',
                'name' => 'Siomay Ikan Ponca 250 gr',
                'category' => 'Food',
                'unit_price' => 14500,
                'stock' => 999999,
                'weight' => 250,
                'unit' => 'gr',
                'image_url' => 'https://res.cloudinary.com/pafffh2m/image/upload/v1787536367/ponca_product_images/mcruz2fd9akqknjht20x.png',
            ],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['item_code' => $p['item_code']],
                $p
            );
        }
    }
}
