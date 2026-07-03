<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('user_id');
            $table->string('email')->unique()->after('name');
            $table->string('employee_code', 50)->nullable()->unique()->after('email');
            $table->string('initial', 10)->nullable()->after('employee_code');
            $table->char('company_id', 36)->nullable()->after('initial');
            $table->char('department_id', 36)->nullable()->after('company_id');
            $table->char('designation_id', 36)->nullable()->after('department_id');
            $table->boolean('is_active')->default(true)->after('designation_id');
            $table->timestamp('email_verified_at')->nullable()->after('is_active');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'name',
                'email',
                'employee_code',
                'initial',
                'company_id',
                'department_id',
                'designation_id',
                'is_active',
                'email_verified_at',
            ]);
        });
    }
};
