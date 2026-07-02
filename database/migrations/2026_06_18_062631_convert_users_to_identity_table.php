<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'name')) {
            // Old schema — convert to minimal identity-linked schema

            Schema::table('users', function (Blueprint $table) {
                $table->char('user_id', 36)->nullable()->after('id');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['name', 'email', 'email_verified_at', 'employee_number', 'position', 'initials', 'phone']);
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        } elseif (Schema::hasColumn('users', 'department_id')) {
            // Migration already ran but old merge added department_id back
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }

        // Fresh install with minimal schema — nothing to do.
        // user_id will be made NOT NULL + UNIQUE after data migration.
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'name') && Schema::hasColumn('users', 'user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('employee_number', 50)->nullable()->unique()->after('email');
                $table->foreignId('department_id')->nullable()->constrained()->after('employee_number');
                $table->string('position')->after('department_id');
                $table->string('initials', 10)->after('position');
                $table->string('phone', 50)->nullable()->after('initials');
            });
        }
    }
};
