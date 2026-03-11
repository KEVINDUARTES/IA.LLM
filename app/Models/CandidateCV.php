<?php

namespace App\Models;

use App\Enums\ProcessingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidateCV extends Model
{
    use HasFactory;

    protected $table = 'candidate_cvs';

    protected $fillable = [
        'cv_text',
        'cv_hash',
        'structured_data',
        'extraction_status',
    ];

    protected $casts = [
        'structured_data'   => 'array',
        'extraction_status' => ProcessingStatus::class,
    ];

    public function scoringResults(): HasMany
    {
        return $this->hasMany(ScoringResult::class);
    }

    public function isExtractionReady(): bool
    {
        return $this->extraction_status === ProcessingStatus::COMPLETED
            && $this->structured_data !== null;
    }

    /**
     * Compute the SHA-256 hash of CV text for deduplication.
     */
    public static function computeHash(string $cvText): string
    {
        return hash('sha256', trim($cvText));
    }
}
