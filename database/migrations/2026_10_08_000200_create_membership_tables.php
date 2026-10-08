<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The one authoritative member record. Every other module points here.
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_no', 30)->nullable()->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('other_names')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->string('photo_path')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->date('joined_on')->nullable();
            $table->text('bio')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['last_name', 'first_name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->unique()->after('id')->constrained('members')->nullOnDelete();
        });

        // Where a member belongs, with dates. A transfer closes one row and
        // opens another; the member record itself never changes identity.
        Schema::create('member_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('org_unit_id')->constrained('org_units');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['member_id', 'ends_on']);
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('member_skill', function (Blueprint $table) {
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->primary(['member_id', 'skill_id']);
        });

        Schema::create('member_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('from_unit_id')->constrained('org_units');
            $table->foreignId('to_unit_id')->constrained('org_units');
            $table->string('status', 20)->default('pending')->index();
            $table->string('reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
        });

        Schema::create('production_team_members', function (Blueprint $table) {
            $table->foreignId('production_team_id')->constrained('production_teams')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->primary(['production_team_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_team_members');
        Schema::dropIfExists('transfer_requests');
        Schema::dropIfExists('member_status_changes');
        Schema::dropIfExists('member_skill');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('member_placements');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
        });
        Schema::dropIfExists('members');
    }
};
