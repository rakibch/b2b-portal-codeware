<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // billing | shipping
            $table->string('label', 100); // e.g. "Mirpur Warehouse"
            $table->string('contact_name');
            $table->string('contact_phone', 20);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('area', 100)->nullable();
            $table->string('city', 100);
            $table->string('district', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->char('country_code', 2)->default('BD');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_addresses');
    }
};
