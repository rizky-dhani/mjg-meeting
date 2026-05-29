<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('date')->after('description');
            $table->time('starts_at')->change();
            $table->time('ends_at')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('date');
            $table->dateTime('starts_at')->change();
            $table->dateTime('ends_at')->change();
        });
    }
};
