<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // silver | gold | distributor
            $table->json('name');
            $table->json('description')->nullable();
            // Flat % discount applied to base/tier price when no group-specific tier exists.
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
