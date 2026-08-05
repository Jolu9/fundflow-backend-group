<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->boolean('chilimba_enabled')->default(false)->after('contribution_amount');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn('chilimba_enabled');
        });
    }
};
