<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->onDelete('cascade');
            $table->integer('cycle_number');
            $table->foreignId('recipient_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('pot_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'active', 'completed'])->default('pending');
            $table->date('payout_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycles');
    }
};
