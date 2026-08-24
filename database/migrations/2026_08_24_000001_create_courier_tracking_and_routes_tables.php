<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update role enum to include 'KURIR'
        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('ADMIN', 'KARYAWAN', 'SCANNER', 'SALLER', 'KURIR') DEFAULT 'KARYAWAN'");
            }
        } catch (\Throwable $e) {
            // Ignore if driver does not support ALTER ENUM or already updated
        }

        // 2. Table: routes
        if (!Schema::hasTable('routes')) {
            Schema::create('routes', function (Blueprint $table) {
                $table->id();
                $table->string('route_code', 50)->unique();
                $table->string('route_name', 100);
                $table->string('area_name', 100)->nullable();
                $table->longText('path_polyline')->nullable()->comment('JSON array of coordinates or polyline');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Table: route_stops
        if (!Schema::hasTable('route_stops')) {
            Schema::create('route_stops', function (Blueprint $table) {
                $table->id();
                $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
                $table->string('store_name', 150);
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);
                $table->integer('sequence_order')->default(1);
                $table->integer('radius_tolerance_meters')->default(50);
                $table->timestamps();
            });
        }

        // 4. Table: courier_assignments
        if (!Schema::hasTable('courier_assignments')) {
            Schema::create('courier_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
                $table->date('assignment_date');
                $table->enum('status', ['assigned', 'in_progress', 'completed', 'canceled'])->default('assigned');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // 5. Table: courier_locations (GPS Logs)
        if (!Schema::hasTable('courier_locations')) {
            Schema::create('courier_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_id')->nullable()->constrained('courier_assignments')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);
                $table->float('speed')->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamp('synced_at')->useCurrent();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 6. Table: route_deviations (Log Salah Arah)
        if (!Schema::hasTable('route_deviations')) {
            Schema::create('route_deviations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_id')->nullable()->constrained('courier_assignments')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);
                $table->float('distance_deviation_meters')->default(0);
                $table->timestamp('recorded_at')->nullable();
                $table->boolean('is_resolved')->default(false);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 7. Table: store_visits (Bukti Kunjungan / Check-in)
        if (!Schema::hasTable('store_visits')) {
            Schema::create('store_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_id')->constrained('courier_assignments')->onDelete('cascade');
                $table->foreignId('route_stop_id')->constrained('route_stops')->onDelete('cascade');
                $table->timestamp('checkin_time')->nullable();
                $table->timestamp('checkout_time')->nullable();
                $table->string('proof_image_url', 255)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_visits');
        Schema::dropIfExists('route_deviations');
        Schema::dropIfExists('courier_locations');
        Schema::dropIfExists('courier_assignments');
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
    }
};
