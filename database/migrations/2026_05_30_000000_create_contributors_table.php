<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_key')->collation('utf8mb4_bin')->unique();
            $table->string('maker_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('contributor_product', function (Blueprint $table): void {
            $table->id();
            $table->string('product_id');
            $table->foreignId('contributor_id')->constrained('contributors')->cascadeOnDelete();
            $table->string('role', 32)->index();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['product_id', 'contributor_id', 'role'], 'contributor_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributor_product');
        Schema::dropIfExists('contributors');
    }
};
