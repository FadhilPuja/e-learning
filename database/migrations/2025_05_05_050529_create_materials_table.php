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
        Schema::create('materials', function (Blueprint $table) {
            $table->id('material_id');
            $table->string('title');
            $table->text('description');
            $table->longText('content');
            $table->unsignedBigInteger('class_id');
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('class_id')
                  ->references('class_id')
                  ->on('classes')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};