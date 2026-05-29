<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number', 50)->unique()->after('email');
            $table->foreignId('department_id')->constrained()->after('employee_number');
            $table->string('position')->after('department_id');
            $table->string('initials', 10)->after('position');
            $table->string('phone', 50)->nullable()->after('initials');
        });

        // WARNING: If this environment has existing employee data, run a
        // separate data migration first to copy data from employees to users.
        Schema::dropIfExists('employees');
    }

    public function down(): void
    {
        // Re-create employees table
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_number', 50)->unique();
            $table->foreignId('department_id')->constrained();
            $table->string('position');
            $table->string('initials', 10);
            $table->string('phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['employee_number', 'position', 'initials', 'phone']);
        });
    }
};
