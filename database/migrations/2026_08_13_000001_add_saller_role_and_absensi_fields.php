<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('ADMIN', 'KARYAWAN', 'SCANNER', 'SALLER') DEFAULT 'KARYAWAN'");
            }
        } catch (\Throwable $e) {
            // Ignore if driver does not support ALTER ENUM or already updated
        }

        Schema::table('absensis', function (Blueprint $table) {
            if (!Schema::hasColumn('absensis', 'lat_keluar')) {
                $table->double('lat_keluar')->nullable();
            }
            if (!Schema::hasColumn('absensis', 'lng_keluar')) {
                $table->double('lng_keluar')->nullable();
            }
            if (!Schema::hasColumn('absensis', 'alamat_masuk')) {
                $table->text('alamat_masuk')->nullable();
            }
            if (!Schema::hasColumn('absensis', 'alamat_keluar')) {
                $table->text('alamat_keluar')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('absensis', function (Blueprint $table) {
            if (Schema::hasColumn('absensis', 'alamat_keluar')) {
                $table->dropColumn('alamat_keluar');
            }
            if (Schema::hasColumn('absensis', 'alamat_masuk')) {
                $table->dropColumn('alamat_masuk');
            }
            if (Schema::hasColumn('absensis', 'lng_keluar')) {
                $table->dropColumn('lng_keluar');
            }
            if (Schema::hasColumn('absensis', 'lat_keluar')) {
                $table->dropColumn('lat_keluar');
            }
        });
    }
};
