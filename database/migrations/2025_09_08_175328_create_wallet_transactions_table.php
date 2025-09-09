<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('type', ['credit','debit']);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2)->nullable();
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('related_id')->nullable(); // e.g. withdrawal id
            $table->timestamps();

            // indexes / FK (optional)
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wallet_transactions');
    }
}
