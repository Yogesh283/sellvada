<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('binary_payouts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sponsor_id');
            $t->string('plan', 10);            // silver | gold | diamond
            $t->date('closing_date');          // yyyy-mm-dd
            $t->unsignedTinyInteger('closing_no'); // 1 or 2
            $t->decimal('volume_left', 12, 2)->default(0);
            $t->decimal('volume_right', 12, 2)->default(0);
            $t->decimal('matched', 12, 2)->default(0);
            $t->decimal('payout', 12, 2)->default(0); // payable after caps
            $t->timestamps();
            $t->unique(['sponsor_id','plan','closing_date','closing_no']); // idempotency
        });
    }
    public function down(): void {
        Schema::dropIfExists('binary_payouts');
    }
};
