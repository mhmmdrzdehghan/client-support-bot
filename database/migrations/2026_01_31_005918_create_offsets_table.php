<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_offsets', function (Blueprint $table) {
            $table->id();
            $table->string('bot_name')->unique();
            $table->unsignedBigInteger('last_update_id')->default(0);
            $table->timestamps();
        });

        DB::table('bot_offsets')->insert([
            'bot_name' => 'bale_bot',
            'last_update_id' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }












    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offsets');
    }
};
