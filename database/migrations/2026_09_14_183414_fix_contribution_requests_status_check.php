<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE contribution_requests DROP CONSTRAINT contribution_requests_status_check');
        DB::statement("ALTER TABLE contribution_requests ADD CONSTRAINT contribution_requests_status_check CHECK (status IN ('pending', 'confirmed', 'rejected'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE contribution_requests DROP CONSTRAINT contribution_requests_status_check');
        DB::statement("ALTER TABLE contribution_requests ADD CONSTRAINT contribution_requests_status_check CHECK (status IN ('pending', 'confirmed'))");
    }
};
