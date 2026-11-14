<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cashes', function (Blueprint $table) {
            $table->string("uuid")->comment("UUID");
        });
        DB::table("cashes")
            ->orderBy("id")
            ->each(function ($cash) {
                DB::table('cashes')
                ->where('id', $cash->id)
                ->update(['uuid' => Str::uuid()]);
            });
        Schema::table('cashes', function (Blueprint $table) {
            $table->unique("uuid");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashes', function (Blueprint $table) {
            $table->dropColumn("uuid");
        });
    }
};
