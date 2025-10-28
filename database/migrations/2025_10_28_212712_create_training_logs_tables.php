<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_logs', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->foreignId('trainee_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('mentor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('course_id')->nullable()->constrained('courses')->onDelete('set null');
            
            // Session metadata
            $table->date('session_date');
            $table->string('position', 25);
            $table->enum('type', ['O', 'S', 'L', 'C']); // Online, Sim, Lesson, Custom
            
            // Additional session details
            $table->enum('traffic_level', ['L', 'M', 'H'])->nullable();
            $table->enum('traffic_complexity', ['L', 'M', 'H'])->nullable();
            $table->string('runway_configuration', 50)->nullable();
            $table->text('surrounding_stations')->nullable();
            $table->unsignedInteger('session_duration')->nullable(); // in minutes
            $table->text('special_procedures')->nullable();
            $table->text('airspace_restrictions')->nullable();
            
            // Evaluation categories (0-4 rating scale)
            // 0 = Not rated, 1 = Requirements not met, 2 = Partially met, 3 = Met, 4 = Exceeded
            
            // Theory
            $table->unsignedTinyInteger('theory')->default(0);
            $table->text('theory_positives')->nullable();
            $table->text('theory_negatives')->nullable();
            
            // Phraseology
            $table->unsignedTinyInteger('phraseology')->default(0);
            $table->text('phraseology_positives')->nullable();
            $table->text('phraseology_negatives')->nullable();
            
            // Coordination
            $table->unsignedTinyInteger('coordination')->default(0);
            $table->text('coordination_positives')->nullable();
            $table->text('coordination_negatives')->nullable();
            
            // Tag Management
            $table->unsignedTinyInteger('tag_management')->default(0);
            $table->text('tag_management_positives')->nullable();
            $table->text('tag_management_negatives')->nullable();
            
            // Situational Awareness
            $table->unsignedTinyInteger('situational_awareness')->default(0);
            $table->text('situational_awareness_positives')->nullable();
            $table->text('situational_awareness_negatives')->nullable();
            
            // Problem Recognition
            $table->unsignedTinyInteger('problem_recognition')->default(0);
            $table->text('problem_recognition_positives')->nullable();
            $table->text('problem_recognition_negatives')->nullable();
            
            // Traffic Planning
            $table->unsignedTinyInteger('traffic_planning')->default(0);
            $table->text('traffic_planning_positives')->nullable();
            $table->text('traffic_planning_negatives')->nullable();
            
            // Reaction
            $table->unsignedTinyInteger('reaction')->default(0);
            $table->text('reaction_positives')->nullable();
            $table->text('reaction_negatives')->nullable();
            
            // Separation
            $table->unsignedTinyInteger('separation')->default(0);
            $table->text('separation_positives')->nullable();
            $table->text('separation_negatives')->nullable();
            
            // Efficiency
            $table->unsignedTinyInteger('efficiency')->default(0);
            $table->text('efficiency_positives')->nullable();
            $table->text('efficiency_negatives')->nullable();
            
            // Ability to Work Under Pressure
            $table->unsignedTinyInteger('ability_to_work_under_pressure')->default(0);
            $table->text('ability_to_work_under_pressure_positives')->nullable();
            $table->text('ability_to_work_under_pressure_negatives')->nullable();
            
            // Motivation
            $table->unsignedTinyInteger('motivation')->default(0);
            $table->text('motivation_positives')->nullable();
            $table->text('motivation_negatives')->nullable();
            
            // Final assessment
            $table->text('internal_remarks')->nullable();
            $table->text('final_comment')->nullable();
            $table->boolean('result'); // Passed or not passed
            $table->text('next_step')->nullable();
            
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['trainee_id', 'session_date']);
            $table->index(['mentor_id', 'session_date']);
            $table->index(['course_id', 'session_date']);
            $table->index('session_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_logs');
    }
};