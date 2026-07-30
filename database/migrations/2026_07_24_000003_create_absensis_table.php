<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('scanner_id')->nullable();
            $table->date('tanggal');
            $table->timestamp('waktu_masuk')->nullable();
            $table->timestamp('waktu_keluar')->nullable();
            $table->string('foto_masuk')->nullable();
            $table->string('foto_keluar')->nullable();
            $table->enum('status', ['TEPAT_WAKTU', 'TERLAMBAT', 'ALPA'])->default('TEPAT_WAKTU');
            $table->double('lat_masuk')->nullable();
            $table->double('lng_masuk')->nullable();
            $table->unsignedBigInteger('master_lokasi_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('scanner_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('master_lokasi_id')->references('id')->on('master_lokasis')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensis');
    }
};
