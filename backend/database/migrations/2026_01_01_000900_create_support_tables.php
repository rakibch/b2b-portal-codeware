<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('body');
            $table->string('audience', 16)->default('all'); // all | buyers | staff
            $table->string('level', 16)->default('info'); // info | success | warning | danger
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'audience', 'published_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32)->default('general');
            $table->string('key', 64)->unique();
            $table->json('value')->nullable();
            $table->timestamps();

            $table->index('group');
        });

        // Gap-free, race-safe document numbering (incremented under lockForUpdate).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['type', 'year']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 64);
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('announcements');
    }
};
