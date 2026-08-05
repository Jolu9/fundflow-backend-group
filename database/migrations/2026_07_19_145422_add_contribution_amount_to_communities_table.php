<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->decimal('contribution_amount', 10, 2)->default(0)->after('invite_code');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn('contribution_amount');
        });
    }
};
