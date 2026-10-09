<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CBT examinations, the GODRAM question bank, certificates and achievements.
 * Each candidate's paper is a snapshot of the questions as presented, so later
 * edits to the bank never change a past result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_category_id')->constrained();
            $table->string('type', 20); // single, multiple, true_false, short, fill_blank, matching, ordering, open
            $table->text('scenario')->nullable();
            $table->text('stem');
            $table->string('media_kind', 10)->nullable(); // image, audio, video
            $table->string('media_path')->nullable();
            $table->string('youtube_id', 20)->nullable();
            $table->json('options')->nullable();
            $table->json('answer')->nullable();
            $table->decimal('marks', 5, 2)->default(1);
            $table->text('explanation')->nullable();
            $table->string('difficulty', 10)->default('moderate'); // easy, moderate, difficult
            $table->boolean('shuffle_options')->default(true);
            $table->string('status', 12)->default('draft'); // draft, approved, retired
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['status', 'question_category_id', 'difficulty']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('org_unit_id')->constrained('org_units');
            $table->text('instructions')->nullable();
            $table->string('mode', 15)->default('certification'); // practice, certification
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->unsignedSmallInteger('question_count')->default(20);
            $table->unsignedTinyInteger('pass_mark')->default(70); // percent
            $table->unsignedTinyInteger('max_attempts')->nullable(); // null = unlimited
            $table->string('result_policy', 10)->default('best'); // best, latest, average
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->string('release', 15)->default('immediate'); // immediate, after_close, manual
            $table->timestamp('released_at')->nullable();
            $table->boolean('show_review')->default(false); // answers and explanations after the attempt
            $table->boolean('awards_certificate')->default(false);
            $table->boolean('requires_course_completion')->default(false);
            $table->string('status', 12)->default('draft'); // draft, published, archived
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('exam_blueprint_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('difficulty', 10)->nullable();
            $table->unsignedSmallInteger('count');
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('status', 15)->default('in_progress'); // in_progress, submitted, auto_submitted
            $table->dateTime('started_at');
            $table->dateTime('deadline_at');
            $table->dateTime('submitted_at')->nullable();
            $table->decimal('score', 7, 2)->nullable();
            $table->decimal('max_score', 7, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->boolean('needs_marking')->default(false);
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('incorrect')->default(0);
            $table->unsignedSmallInteger('unanswered')->default(0);
            $table->unsignedInteger('time_used_seconds')->nullable();
            $table->unsignedSmallInteger('disconnections')->default(0);
            $table->unsignedSmallInteger('focus_losses')->default(0);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'member_id', 'number']);
            $table->index(['status', 'deadline_at']);
        });

        Schema::create('attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained();
            $table->unsignedSmallInteger('position');
            $table->json('snapshot');
            $table->json('response')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_correct')->nullable();
            $table->decimal('marks_awarded', 5, 2)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->unsignedInteger('seconds_spent')->default(0);
            $table->unique(['exam_attempt_id', 'position']);
            $table->index('question_id');
        });

        Schema::create('attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->json('data')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['exam_attempt_id', 'type']);
        });

        Schema::create('achievement_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->string('kind', 20); // training, excellence, reporting, creative, service, leadership
            $table->string('metric', 40);
            $table->unsignedSmallInteger('threshold');
            $table->boolean('issues_certificate')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('verify_code', 16);
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_name');
            $table->string('kind', 20); // examination, training, achievement, recognition
            $table->string('title');
            $table->string('achievement', 300);
            $table->string('programme')->nullable();
            $table->date('issued_on');
            $table->string('issuing_authority');
            $table->text('eligibility_rule');
            $table->string('verification_method');
            $table->json('signatories');
            $table->foreignId('exam_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('achievement_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('valid'); // valid, revoked
            $table->string('revoked_reason')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['member_id', 'status']);
        });

        Schema::create('member_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->string('description', 300)->nullable();
            $table->string('kind', 20);
            $table->date('awarded_on');
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['member_id', 'achievement_rule_id']);
        });
    }

    public function down(): void
    {
        foreach (['member_achievements', 'certificates', 'achievement_rules', 'attempt_events', 'attempt_questions', 'exam_attempts',
            'exam_blueprint_rows', 'exams', 'questions', 'question_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
