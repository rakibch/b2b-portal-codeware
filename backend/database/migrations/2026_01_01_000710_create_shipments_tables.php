<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One shipment = one Delivery Challan. Multiple shipments enable partial dispatch.
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('challan_number', 32)->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('packing'); // packing | dispatched | delivered | returned
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('vehicle_number', 32)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 20)->nullable();
            $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('received_by_name', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['shipment_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
