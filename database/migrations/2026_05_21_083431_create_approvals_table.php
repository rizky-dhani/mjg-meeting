<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->nullableMorphs('approvable');
            $table->string('status');
            $table->string('approval_by');
            $table->morphs('approver');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['approvable_type', 'approvable_id', 'key', 'approval_by'], 'approvals_flow_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
