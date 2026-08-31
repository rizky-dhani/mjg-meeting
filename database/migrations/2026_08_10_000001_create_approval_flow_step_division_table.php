<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flow_step_division', function (Blueprint $table) {
            $table->foreignId('approval_flow_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->primary(['approval_flow_step_id', 'division_id']);
        });

        // Migrate existing single division_id into the pivot.
        DB::table('approval_flow_steps')
            ->whereNotNull('division_id')
            ->select('id', 'division_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $pivot = $rows
                    ->map(fn ($row) => [
                        'approval_flow_step_id' => $row->id,
                        'division_id' => $row->division_id,
                    ])
                    ->all();

                DB::table('approval_flow_step_division')->insert($pivot);
            });

        Schema::table('approval_flow_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_id');
        });
    }

    public function down(): void
    {
        Schema::table('approval_flow_steps', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
        });

        // Restore one division per step (lowest division id) as division_id.
        DB::table('approval_flow_step_division')
            ->orderBy('approval_flow_step_id')
            ->orderBy('division_id')
            ->get()
            ->unique('approval_flow_step_id')
            ->each(function ($row): void {
                DB::table('approval_flow_steps')
                    ->where('id', $row->approval_flow_step_id)
                    ->update(['division_id' => $row->division_id]);
            });

        Schema::dropIfExists('approval_flow_step_division');
    }
};