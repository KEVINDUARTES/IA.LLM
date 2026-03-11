<?php

namespace App\Models;

use App\Enums\ProcessingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'criteria_status',
    ];

    protected $casts = [
        'criteria_status' => ProcessingStatus::class,
    ];

    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class);
    }

    public function scoringResults(): HasMany
    {
        return $this->hasMany(ScoringResult::class);
    }

    public function hasCriteriaReady(): bool
    {
        return $this->criteria_status === ProcessingStatus::COMPLETED
            && $this->criteria()->exists();
    }
}
