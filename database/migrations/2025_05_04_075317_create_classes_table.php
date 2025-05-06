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
        Schema::create('classes', function (Blueprint $table) {
            $table->id('class_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unique_code', 10)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes', 'class_id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamps();
            
            // Each student can only enroll once in a class
            $table->unique(['class_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
        Schema::dropIfExists('classes');
    }
};