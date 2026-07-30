<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_lokasis', function (Blueprint $table) {
            $table->id();
            $table->string('nama_place')->default('PONCA FOOD');
            $table->enum('tipe', ['PABRIK', 'OUTLET', 'AREA_PEMASARAN'])->default('OUTLET');
            $table->string('alamat')->nullable();
            $table->double('latitude');
            $table->double('longitude');
            $table->double('radius');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_lokasis');
    }
};
