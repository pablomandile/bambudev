<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Apps que solo ven y abren los admins. Las que ya existen quedan para todos.
     */
    public function up(): void
    {
        Schema::table('micro_apps', function (Blueprint $table) {
            $table->boolean('admin_only')->default(false)->after('visible');
        });
    }

    public function down(): void
    {
        Schema::table('micro_apps', function (Blueprint $table) {
            $table->dropColumn('admin_only');
        });
    }
};
