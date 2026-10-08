<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GODRAM Virtual Academy: Training Programme → Sessions → Lessons → Resources → Activities.
 * Examinations, results and certificates are added in Phase 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('kind', 20)->default('recorded'); // recorded, live, blended
            $table->foreignId('org_unit_id')->constrained('org_units'); // who runs it: National, a Region or a District
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->text('outcomes')->nullable(); // one per line
            $table->string('cover_path')->nullable();
            $table->boolean('is_public')->default(true); // listed on the public Academy page
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->date('enrol_by')->nullable();
            $table->string('status', 20)->default('draft'); // draft, published, archived
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['status', 'org_unit_id']);
        });

        // Who the training is for. No rows means every member under the organising unit.
        Schema::create('course_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 20); // org_unit, role
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->cascadeOnDelete();
            $table->string('role_key', 50)->nullable();
        });

        Schema::create('course_facilitators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unique(['course_id', 'member_id']);
        });

        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('summary', 300)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->dateTime('live_at')->nullable();
            $table->dateTime('live_ends_at')->nullable();
            $table->string('live_url')->nullable();
            $table->string('live_platform', 20)->nullable();
            $table->string('replay_youtube_id', 20)->nullable();
            $table->boolean('room_open')->default(false); // facilitator has opened questions and responses
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_session_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('kind', 20)->default('text'); // video, audio, text, document
            $table->string('summary', 300)->nullable();
            $table->longText('body')->nullable();
            $table->text('key_points')->nullable(); // one per line
            $table->string('scripture', 300)->nullable();
            $table->string('youtube_id', 20)->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_preview')->default(false);
            $table->timestamps();
        });

        Schema::create('course_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('course_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('path')->nullable();
            $table->string('url')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Call-and-response, quick checks and polls, inside a lesson or a live session.
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('course_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20); // choice, true_false, short, poll, complete, open
            $table->string('question', 500);
            $table->json('options')->nullable();
            $table->string('answer', 300)->nullable();
            $table->string('explanation', 500)->nullable();
            $table->boolean('is_live')->default(false); // shown in the live room now
            $table->boolean('show_results')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('prompt_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('response', 1000);
            $table->boolean('is_correct')->nullable();
            $table->timestamps();
            $table->unique(['prompt_id', 'user_id']);
        });

        Schema::create('enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active'); // active, completed, withdrawn
            $table->foreignId('last_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'member_id']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrolment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->unique(['enrolment_id', 'lesson_id']);
        });

        // Attendance is honest about its source: the member said they joined, or a facilitator confirmed it.
        Schema::create('session_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20); // joined, attended, absent
            $table->timestamp('joined_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['course_session_id', 'member_id']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('brief');
            $table->json('accepts'); // text, file, link
            $table->dateTime('due_at')->nullable();
            $table->unsignedSmallInteger('max_score')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('link')->nullable();
            $table->string('status', 20)->default('submitted'); // submitted, returned, accepted
            $table->text('feedback')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['assignment_id', 'member_id']);
        });

        Schema::create('course_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->text('answer')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
            $table->index(['course_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['course_questions', 'assignment_submissions', 'assignments', 'session_attendance', 'lesson_progress', 'enrolments',
            'prompt_responses', 'prompts', 'course_resources', 'lessons', 'course_sessions', 'course_facilitators', 'course_targets', 'courses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
