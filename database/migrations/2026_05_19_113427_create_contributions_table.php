<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            if (!Schema::hasColumn('contributions', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('contributions', 'recorded_by')) {
                $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('contributions', 'amount')) {
                $table->decimal('amount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('contributions', 'contribution_date')) {
                $table->date('contribution_date')->nullable();
            }
            if (!Schema::hasColumn('contributions', 'notes')) {
                $table->string('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'recorded_by', 'amount', 'contribution_date', 'notes']);
        });
    }
};