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
            
            $table->foreign('remark_author_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('course_trainees', function (Blueprint $table) {
            $table->dropForeign(['remark_author_id']);
            $table->dropColumn(['remark_author_id', 'remark_updated_at']);
        });
    }
};