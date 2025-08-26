<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_id', 32)->unique()->after('password');
            $table->string('refer_by', 32)->nullable()->after('referral_id');

            $table->unsignedBigInteger('parent_id')->nullable()->after('refer_by');
            $table->enum('position', ['L','R'])->nullable()->after('parent_id');

            $table->unsignedBigInteger('left_user_id')->nullable()->after('position');
            $table->unsignedBigInteger('right_user_id')->nullable()->after('left_user_id');

            // indexes
            $table->index('refer_by');
            $table->index('parent_id');
            $table->unique(['parent_id','position']); // ek parent ke ek side par ek hi user
            $table->string('Password_plain');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['parent_id','position']);
            $table->dropColumn(['referral_id','refer_by','parent_id','position','left_user_id','right_user_id']);
        });
    }
};
