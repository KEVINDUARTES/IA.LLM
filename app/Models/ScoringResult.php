<?php

namespace App\Models;

use App\Enums\ProcessingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringResult extends Model
{
    use HasFactory;

    protected $table = 'scoring_results';

    protected $fillable = [
        'job_offer_id',
        'candidate_cv_id',
        'score',
        'breakdown',
        'gaps',
        'status',
        'error_message',
    ];

    protected $casts = [
        'score'     => 'integer',
        'breakdown' => 'array',
        'gaps'      => 'array',
        'status'    => ProcessingStatus::class,
    ];

    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }

    public function candidateCv(): BelongsTo
    {
        return $this->belongsTo(CandidateCV::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === ProcessingStatus::COMPLETED;
    }
}
