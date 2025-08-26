<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $table) {
      $table->foreignId('sponsor_id')->nullable()
            ->constrained('users')->nullOnDelete();     // self FK
      $table->string('referral_code', 16)->unique()->nullable(); // unique code
    });
  }

  public function down(): void {
    Schema::table('users', function (Blueprint $table) {
      $table->dropConstrainedForeignId('sponsor_id');
      $table->dropColumn('referral_code');
    });
  }
};
