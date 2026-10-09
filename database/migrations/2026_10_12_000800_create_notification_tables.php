<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel's own table: every in-app notice a person receives.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Only the choices a person changed; anything missing uses config/notifications.php.
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->boolean('email');
            $table->boolean('push');
            $table->timestamps();
            $table->unique(['user_id', 'category']);
        });

        // Phones and browsers that agreed to receive push notifications.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('device', 120)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // One row per reminder sent, so cron can run often without sending twice.
        Schema::create('sent_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_reminders');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
