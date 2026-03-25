<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedSmallInteger('copies_count')->default(1)->after('device_id');
            $table->decimal('unit_price', 10, 2)->default(0)->after('amount');
        });

        DB::table('licenses')->update([
            'copies_count' => 1,
            'unit_price' => DB::raw('amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['copies_count', 'unit_price']);
        });
    }
};
