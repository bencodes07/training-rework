<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_trainees', function (Blueprint $table) {
            // Add custom order column - stores the position (1, 2, 3, etc.)
            $table->integer('custom_order')->nullable()->after('claimed_at');
            
            // Add custom order mentor ID - tracks which mentor set this order
            $table->integer('custom_order_mentor_id')->nullable()->after('custom_order');
            
            // Add foreign key constraint to users table
            $table->foreign('custom_order_mentor_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            
            // Add index for better query performance when ordering
            $table->index(['course_id', 'custom_order_mentor_id', 'custom_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_trainees', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['custom_order_mentor_id']);
            
            // Drop index
            $table->dropIndex(['course_id', 'custom_order_mentor_id', 'custom_order']);
            
            // Drop columns
            $table->dropColumn(['custom_order', 'custom_order_mentor_id']);
        });
    }
};