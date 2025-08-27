<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('star_rank_slabs', function (Blueprint $t) {
            $t->id();
            $t->unsignedTinyInteger('rank_no')->unique();   // 1..12
            $t->string('title');                            // "1 STAR" ...
            $t->unsignedBigInteger('threshold_volume');     // in INR (e.g., 100000)
            $t->decimal('reward_amount', 12, 2);            // INR
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('star_rank_slabs'); }
};
