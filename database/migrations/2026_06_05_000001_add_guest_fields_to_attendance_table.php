<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['booking_id']);
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropUnique(['booking_id', 'user_id']);
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();

            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_from')->nullable()->after('guest_name');
            $table->string('guest_designation')->nullable()->after('guest_from');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->unique(['booking_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['booking_id']);
            $table->dropUnique(['booking_id', 'user_id']);
            $table->dropColumn(['guest_name', 'guest_from', 'guest_designation']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->unique(['booking_id', 'user_id']);
        });
    }
};
