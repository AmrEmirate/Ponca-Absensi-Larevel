<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'subtotal')) {
                $table->double('subtotal')->default(0)->after('order_date');
            }
            if (!Schema::hasColumn('sales_orders', 'discount_type')) {
                $table->string('discount_type')->nullable()->after('subtotal'); // 'percent' or 'nominal'
            }
            if (!Schema::hasColumn('sales_orders', 'discount_value')) {
                $table->double('discount_value')->default(0)->after('discount_type'); // e.g. 10 (%) or 5000 (Rp)
            }
            if (!Schema::hasColumn('sales_orders', 'discount_amount')) {
                $table->double('discount_amount')->default(0)->after('discount_value'); // actual calculated Rp
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
