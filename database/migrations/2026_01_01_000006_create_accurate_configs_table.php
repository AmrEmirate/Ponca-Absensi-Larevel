<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accurate_configs')) {
            Schema::create('accurate_configs', function (Blueprint $table) {
                $table->id();
                $table->string('client_id')->default('pfj-acc-app-2026');
                $table->text('api_token')->nullable();
                $table->string('db_id')->default('889201');
                $table->boolean('auto_sync')->default(true);
                $table->integer('sync_interval_minutes')->default(5);
                $table->string('last_successful_sync')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accurate_configs');
    }
};
