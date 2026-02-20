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
        Schema::create('attach_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->longText('file_path');
            $table->string('file_type');
            $table->integer('file_size');
            $table->unsignedBigInteger('sender_id');
            $table->integer('entity_id')->nullable();
            $table->timestamps();

            $table->foreign('sender_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attach_files');
    }
};
