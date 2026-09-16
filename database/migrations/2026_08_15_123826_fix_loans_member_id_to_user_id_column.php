<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('loans', 'member_id') && !Schema::hasColumn('loans', 'user_id')) {
            Schema::table('loans', function ($table) {
                $table->renameColumn('member_id', 'user_id');
            });
        }
    }

    public function down(): void
    {
        //
    }
};
