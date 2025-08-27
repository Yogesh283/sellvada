<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('star_rank_awards', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sponsor_id')->index();
            $t->unsignedTinyInteger('rank_no');
            $t->unsignedBigInteger('threshold_volume');   // jis threshold par award mila
            $t->decimal('reward_amount', 12, 2);
            $t->timestamp('awarded_at')->useCurrent();
            $t->timestamps();
            $t->unique(['sponsor_id','rank_no']);         // duplicate award guard
        });
    }
    public function down(): void { Schema::dropIfExists('star_rank_awards'); }
};
