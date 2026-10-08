<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One central announcement board; targets decide who sees what.
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('image_path')->nullable();
            $table->string('link_url')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reject_reason')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            // public | org_unit | role
            $table->string('audience', 20);
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->cascadeOnDelete();
            $table->string('role_key', 50)->nullable();
        });

        Schema::create('activity_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->foreignId('org_unit_id')->constrained('org_units');
            $table->foreignId('production_team_id')->nullable()->constrained('production_teams')->nullOnDelete();
            $table->string('activity_type', 40)->nullable();
            $table->string('title')->nullable();
            $table->string('event_name')->nullable();
            $table->date('activity_date')->nullable()->index();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('performers_count')->nullable();
            $table->unsignedInteger('attendance_count')->nullable();
            $table->unsignedInteger('souls_won')->nullable();
            $table->text('outcome')->nullable();
            $table->text('impact')->nullable();
            $table->text('remarks')->nullable();
            $table->string('video_url')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason', 1000)->nullable();
            $table->boolean('is_public_highlight')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('report_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_report_id')->constrained('activity_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('report_participants', function (Blueprint $table) {
            $table->foreignId('activity_report_id')->constrained('activity_reports')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('role', 40)->nullable();
            $table->primary(['activity_report_id', 'member_id']);
        });

        Schema::create('report_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_report_id')->constrained('activity_reports')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_media');
        Schema::dropIfExists('report_participants');
        Schema::dropIfExists('report_events');
        Schema::dropIfExists('activity_reports');
        Schema::dropIfExists('announcement_targets');
        Schema::dropIfExists('announcements');
    }
};
