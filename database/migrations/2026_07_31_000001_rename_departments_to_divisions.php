<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only drop FKs that actually exist
        foreach (['users', 'positions', 'approval_flow_steps'] as $table) {
            $fks = Schema::getForeignKeys($table);
            foreach ($fks as $fk) {
                if (in_array('department_id', $fk['columns'])) {
                    Schema::table($table, fn (Blueprint $b) => $b->dropForeign(['department_id']));
                }
            }
        }

        Schema::rename('departments', 'divisions');

        Schema::table('divisions', function (Blueprint $table) {
            $table->renameColumn('department_id', 'division_id');
            $table->string('initial')->after('name');
        });

        // users.department_id is char(36) UUID but divisions.id is bigint — must migrate type
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('division_id_temp')->nullable()->after('department_id');
        });

        DB::table('users')
            ->join('divisions', 'users.department_id', '=', 'divisions.department_id')
            ->update(['users.division_id_temp' => DB::raw('divisions.id')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('department_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('division_id_temp', 'division_id');
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
        foreach (['approval_flow_steps', 'positions', 'users'] as $table) {
            $fks = Schema::getForeignKeys($table);
            foreach ($fks as $fk) {
                if (in_array('division_id', $fk['columns'])) {
                    Schema::table($table, fn (Blueprint $b) => $b->dropForeign(['division_id']));
                }
            }
        }

        Schema::rename('divisions', 'departments');

        Schema::table('departments', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
            $table->dropColumn('initial');
        });

        // Reverse users: bigint division_id → char(36) UUID department_id
        Schema::table('users', function (Blueprint $table) {
            $table->char('department_id_temp', 36)->nullable()->after('division_id');
        });

        DB::table('users')
            ->join('departments', 'users.division_id', '=', 'departments.id')
            ->update(['users.department_id_temp' => DB::raw('departments.department_id')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('division_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('department_id_temp', 'department_id');
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
