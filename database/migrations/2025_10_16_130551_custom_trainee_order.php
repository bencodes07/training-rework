<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_trainees', function (Blueprint $table) {
            $table->integer('custom_order')->nullable()->after('claimed_at');
            $table->integer('custom_order_mentor_id')->nullable()->after('custom_order');
            
            $table->foreign('custom_order_mentor_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
                
            $table->index(['course_id', 'custom_order_mentor_id', 'custom_order']);
        });
    }

    public function down(): void
    {
        Schema::table('course_trainees', function (Blueprint $table) {
            $table->dropForeign(['custom_order_mentor_id']);
            $table->dropIndex(['course_id', 'custom_order_mentor_id', 'custom_order']);
            $table->dropColumn(['custom_order', 'custom_order_mentor_id']);
        });
    }
};