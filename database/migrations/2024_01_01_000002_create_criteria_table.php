<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->enum('type', ['boolean', 'years', 'enum', 'score_1_5']);
            $table->boolean('required')->default(false);
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            $table->json('expected_value');
            $table->unsignedSmallInteger('weight')->default(10);
            $table->timestamps();

            // A criterion key must be unique per job offer
            $table->unique(['job_offer_id', 'key']);
            $table->index('job_offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria');
    }
};
