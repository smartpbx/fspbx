<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users_adv_fields', function (Blueprint $table) {
            $table->boolean('is_on_premise')->default(false)->after('last_name');
            $table->string('on_premise_pbx_ip')->nullable()->after('is_on_premise');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_adv_fields', function (Blueprint $table) {
            $table->dropColumn('is_on_premise');
            $table->dropColumn('on_premise_pbx_ip');
        });
    }
}; 