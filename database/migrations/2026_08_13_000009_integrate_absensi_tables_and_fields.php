<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create master_lokasis table
        if (!Schema::hasTable('master_lokasis')) {
            Schema::create('master_lokasis', function (Blueprint $table) {
                $table->id();
                $table->string('nama_place')->default('PONCA FOOD');
                $table->enum('tipe', ['PABRIK', 'OUTLET', 'AREA_PEMASARAN'])->default('OUTLET');
                $table->string('alamat')->nullable();
                $table->double('latitude');
                $table->double('longitude');
                $table->double('radius');
                $table->string('timezone')->default('Asia/Jakarta');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Add absensi and POS fields to users table dynamically
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'nik')) {
                $table->string('nik')->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'nama')) {
                $table->string('nama')->nullable()->after('nik');
            }
            if (!Schema::hasColumn('users', 'jabatan')) {
                $table->string('jabatan')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('Active')->after('jabatan');
            }
            if (!Schema::hasColumn('users', 'location')) {
                $table->string('location')->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('location');
            }
            if (!Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('avatar');
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('must_change_password');
            }
            if (!Schema::hasColumn('users', 'gaji_perhari')) {
                $table->integer('gaji_perhari')->default(0)->after('last_login_at');
            }
            if (!Schema::hasColumn('users', 'hari_kerja')) {
                $table->string('hari_kerja')->default('Senin,Selasa,Rabu,Kamis,Jumat')->after('gaji_perhari');
            }
            if (!Schema::hasColumn('users', 'jam_masuk_kerja')) {
                $table->string('jam_masuk_kerja')->default('08:00')->after('hari_kerja');
            }
            if (!Schema::hasColumn('users', 'jam_keluar_kerja')) {
                $table->string('jam_keluar_kerja')->default('17:00')->after('jam_masuk_kerja');
            }
            if (!Schema::hasColumn('users', 'master_lokasi_id')) {
                $table->unsignedBigInteger('master_lokasi_id')->nullable()->after('jam_keluar_kerja');
            }
            if (!Schema::hasColumn('users', 'foto_profil')) {
                $table->string('foto_profil')->nullable()->after('master_lokasi_id');
            }
            if (!Schema::hasColumn('users', 'foto_referensi')) {
                $table->string('foto_referensi')->nullable()->after('foto_profil');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
            }
            if (!Schema::hasColumn('users', 'face_reverification_status')) {
                $table->string('face_reverification_status')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // 3. Create absensis table
        if (!Schema::hasTable('absensis')) {
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
                $table->double('lat_keluar')->nullable();
                $table->double('lng_keluar')->nullable();
                $table->text('alamat_masuk')->nullable();
                $table->text('alamat_keluar')->nullable();
                $table->unsignedBigInteger('master_lokasi_id')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('scanner_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('master_lokasi_id')->references('id')->on('master_lokasis')->onDelete('set null');
            });
        }

        // 4. Create izins table
        if (!Schema::hasTable('izins')) {
            Schema::create('izins', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('jenis_izin');
                $table->text('deskripsi');
                $table->string('foto_url')->nullable();
                $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
                $table->date('tanggal');
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // 5. Create notifications table
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('title');
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('izins');
        Schema::dropIfExists('absensis');
        Schema::dropIfExists('master_lokasis');
    }
};
