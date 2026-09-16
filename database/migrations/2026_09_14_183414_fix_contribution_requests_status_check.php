<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE contribution_requests DROP CONSTRAINT contribution_requests_status_check');
        DB::statement("ALTER TABLE contribution_requests ADD CONSTRAINT contribution_requests_status_check CHECK (status IN ('pending', 'confirmed', 'rejected'))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE contribution_requests DROP CONSTRAINT contribution_requests_status_check');
        DB::statement("ALTER TABLE contribution_requests ADD CONSTRAINT contribution_requests_status_check CHECK (status IN ('pending', 'confirmed'))");
    }
};