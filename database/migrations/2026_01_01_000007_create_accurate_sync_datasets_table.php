<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accurate_sync_datasets')) {
            Schema::create('accurate_sync_datasets', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('dataset_name');
                $table->integer('record_count')->default(0);
                $table->string('status')->default('Synced');
                $table->string('last_sync')->default('Never');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accurate_sync_datasets');
    }
};
