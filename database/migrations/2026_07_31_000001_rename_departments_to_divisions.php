<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });
        Schema::table('positions', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });
        Schema::table('approval_flow_steps', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::rename('departments', 'divisions');

        Schema::table('divisions', function (Blueprint $table) {
            $table->renameColumn('department_id', 'division_id');
            $table->string('initial')->after('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('department_id', 'division_id');
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->renameColumn('department_id', 'division_id');
            $table->foreign('division_id')->references('id')->on('divisions')->cascadeOnDelete();
        });

        Schema::table('approval_flow_steps', function (Blueprint $table) {
            $table->renameColumn('department_id', 'division_id');
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_flow_steps', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
        });
        Schema::table('positions', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
        });

        Schema::rename('divisions', 'departments');

        Schema::table('departments', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
            $table->dropColumn('initial');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
        });

        Schema::table('approval_flow_steps', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }
};
