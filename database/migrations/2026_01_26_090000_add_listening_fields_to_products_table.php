<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('start_date')->nullable()->after('progress');
            $table->json('end_date')->nullable()->after('start_date');
            $table->integer('num_re_listen_times')->nullable()->after('end_date');
            $table->integer('re_listen_value')->nullable()->after('num_re_listen_times');
            $table->integer('priority')->nullable()->after('re_listen_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'start_date',
                'end_date',
                'num_re_listen_times',
                're_listen_value',
                'priority',
            ]);
        });
    }
};
