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
            $table->integer('claimed_by_mentor_id')->nullable()->after('remark_updated_at');
            $table->timestamp('claimed_at')->nullable()->after('claimed_by_mentor_id');
            
            $table->foreign('claimed_by_mentor_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_trainees', function (Blueprint $table) {
            $table->dropForeign(['claimed_by_mentor_id']);
            $table->dropColumn(['claimed_by_mentor_id', 'claimed_at']);
        });
    }
};