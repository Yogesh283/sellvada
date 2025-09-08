<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('p2p_transfers', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('from_user_id');
    $table->unsignedBigInteger('to_user_id');
    $table->decimal('amount', 16, 2);
    $table->text('remark')->nullable();
    $table->unsignedBigInteger('wallet_debit_id')->nullable();
    $table->unsignedBigInteger('wallet_credit_id')->nullable();
    $table->timestamps();

    $table->foreign('from_user_id')->references('id')->on('users')->onDelete('cascade');
    $table->foreign('to_user_id')->references('id')->on('users')->onDelete('cascade');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('p2p_transfers');
    }
};
