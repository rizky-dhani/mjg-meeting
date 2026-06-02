<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('approval_flow_steps', function (Blueprint $table) {
            $table->string('scope', 20)->default('all')->after('step_order');
        });

        // Migrate existing records: if department_id is set, scope = 'department'
        DB::table('approval_flow_steps')
            ->whereNotNull('department_id')
            ->update(['scope' => 'department']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_flow_steps', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
