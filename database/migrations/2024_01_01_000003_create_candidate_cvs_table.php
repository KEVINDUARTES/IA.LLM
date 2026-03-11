<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_cvs', function (Blueprint $table) {
            $table->id();
            $table->longText('cv_text');
            $table->char('cv_hash', 64)->unique(); // SHA-256 hex digest
            $table->json('structured_data')->nullable();
            $table->enum('extraction_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamps();

            // Hash is queried frequently for deduplication
            $table->index('cv_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_cvs');
    }
};
