<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_cv_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->nullable(); // 0–100
            $table->json('breakdown')->nullable();
            $table->json('gaps')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Prevent duplicate scoring of the same CV against the same offer
            $table->unique(['job_offer_id', 'candidate_cv_id']);
            $table->index(['job_offer_id', 'candidate_cv_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_results');
    }
};
