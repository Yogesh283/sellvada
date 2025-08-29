<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('address', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name', 100);           // Recipient name
            $table->string('phone', 20);
            $table->string('line1', 255);
            $table->string('line2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('pincode', 20);
            $table->string('country', 100)->default('India');

            $table->boolean('is_default')->default(false);

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('address');
    }
};
