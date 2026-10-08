<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Creative Showcase: productions, films, posters, awards and major events.
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('kind', 30);
            $table->unsignedSmallInteger('year')->nullable();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('summary', 300)->nullable();
            $table->text('body')->nullable();
            $table->text('credits')->nullable();
            $table->string('cover_path')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['status', 'kind']);
        });

        Schema::create('production_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption', 200)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('type', 30);
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->text('description');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('stream_url')->nullable();
            $table->string('stream_platform', 20)->nullable();
            $table->string('replay_youtube_id', 20)->nullable();
            $table->string('cover_path')->nullable();
            $table->string('visibility', 10)->default('public');
            $table->boolean('registration_open')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->string('status', 20)->default('published');
            $table->string('cancel_reason')->nullable();
            $table->foreignId('production_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['status', 'starts_at']);
        });

        Schema::create('event_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role', 20);
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('registered');
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status']);
        });

        // GODRAM TV: videos live on YouTube; we keep the catalogue and metadata.
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('youtube_id', 20)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 30);
            $table->date('recorded_on')->nullable();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_featured')->default(false);
            $table->string('status', 20)->default('submitted');
            $table->string('reject_reason')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['status', 'category']);
        });

        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('type', 30);
            $table->string('title');
            $table->string('standfirst', 300)->nullable();
            $table->longText('body')->nullable();
            $table->string('quote', 400)->nullable();
            $table->string('quote_by', 120)->nullable();
            $table->string('cover_path')->nullable();
            $table->string('youtube_id', 20)->nullable();
            $table->string('audio_path')->nullable();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reject_reason')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['status', 'type']);
        });

        // Performance of the Week, Creative Spotlight and similar recurring features.
        Schema::create('spotlights', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->morphs('subject');
            $table->string('note', 300)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spotlights');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('videos');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_people');
        Schema::dropIfExists('events');
        Schema::dropIfExists('production_images');
        Schema::dropIfExists('productions');
    }
};
