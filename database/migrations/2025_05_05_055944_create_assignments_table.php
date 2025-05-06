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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->unsignedBigInteger('class_id');
            $table->string('title');
            $table->text('description');
            $table->string('file_url')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamps();

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
        Schema::dropIfExists('assignments');
    }
};