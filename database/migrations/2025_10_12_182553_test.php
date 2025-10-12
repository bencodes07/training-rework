<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_trainees', function (Blueprint $table) {
            $table->integer('remark_author_id')->nullable()->after('remarks');
            $table->timestamp('remark_updated_at')->nullable()->after('remark_author_id');
            $table->integer('claimed_by_mentor_id')->nullable()->after('remark_updated_at');
            $table->timestamp('claimed_at')->nullable()->after('claimed_by_mentor_id');
            
            $table->foreign('remark_author_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            
            $table->foreign('claimed_by_mentor_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('course_trainees', function (Blueprint $table) {
            $table->dropForeign(['remark_author_id']);
            $table->dropForeign(['claimed_by_mentor_id']);
            $table->dropColumn(['remark_author_id', 'remark_updated_at', 'claimed_by_mentor_id', 'claimed_at']);
        });
    }
};