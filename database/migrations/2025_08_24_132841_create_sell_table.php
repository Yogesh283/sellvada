<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sell', function (Blueprint $t) {
            $t->id();

            // kis user ne kharida (buyer)
            $t->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();

            // sponsor/upline jiske context me leg (L/R) dekhna hai
            $t->foreignId('sponsor_id')->nullable()->constrained('users')->nullOnDelete();

            // income actually kisko credit hui (zyadaatar sponsor_id hi hoga)
            $t->foreignId('income_to_user_id')->nullable()->constrained('users')->nullOnDelete();

            // buyer relative to sponsor: 'L' or 'R'
            $t->enum('leg', ['L','R'])->nullable()->comment('Buyer leg relative to sponsor');

            // product & money
            $t->string('product', 100);
            $t->decimal('amount', 16, 2);        // sale amount
            $t->decimal('income', 16, 2)->default(0); // income/commission amount

            // income ka type (business rule ke hisaab se)
            $t->enum('income_type', ['DIRECT','LEVEL','BINARY','MATCHING','OTHER'])
              ->default('DIRECT');

            // optional: level depth for LEVEL income
            $t->unsignedTinyInteger('level')->nullable();

            // order / status / misc
            $t->string('order_no', 64)->nullable();
            $t->enum('status', ['pending','paid','cancelled'])->default('paid');
            $t->json('details')->nullable(); // any extra meta

            $t->timestamps();

            // helpful indexes
            $t->index(['sponsor_id','leg']);
            $t->index(['buyer_id','created_at']);
            $t->index(['income_to_user_id','income_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell');
    }
};
