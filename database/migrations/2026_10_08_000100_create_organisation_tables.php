<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // National, Regions, Districts and Assemblies live in one tree.
        // `path` holds the ancestor ids (e.g. /1/4/17/203/) so a whole
        // branch can be selected with one indexed LIKE query.
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('name');
            $table->string('code', 30)->nullable()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $table->string('path')->default('/')->index();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A Region has no permanent drama team; it forms temporary
        // production teams that draw members from its Districts.
        Schema::create('production_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_unit_id')->constrained('org_units');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_teams');
        Schema::dropIfExists('org_units');
    }
};
