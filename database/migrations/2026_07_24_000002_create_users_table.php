<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nik')->unique();
            $table->string('nama');
            $table->string('jabatan')->nullable();
            $table->string('email')->unique();
            $table->string('password'); // bcrypt hashed
            $table->text('foto_referensi')->nullable(); // URL foto referensi wajah di Cloudinary
            $table->string('foto_profil')->nullable(); // URL foto profil
            $table->enum('role', ['ADMIN', 'KARYAWAN', 'SCANNER'])->default('KARYAWAN');
            $table->boolean('is_active')->default(true);
            $table->integer('gaji_perhari')->default(0);
            $table->string('hari_kerja')->default('Senin,Selasa,Rabu,Kamis,Jumat');
            $table->string('jam_masuk_kerja')->default('08:00');
            $table->string('jam_keluar_kerja')->default('17:00');
            $table->unsignedBigInteger('master_lokasi_id')->nullable();
            $table->enum('face_reverification_status', ['NONE', 'PENDING', 'APPROVED'])->default('NONE');
            $table->timestamps();

            $table->foreign('master_lokasi_id')->references('id')->on('master_lokasis')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
