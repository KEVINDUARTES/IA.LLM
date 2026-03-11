<?php

namespace App\Models;

use App\Enums\CriterionPriority;
use App\Enums\CriterionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Criterion extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_offer_id',
        'key',
        'label',
        'type',
        'required',
        'priority',
        'expected_value',
        'weight',
    ];

    protected $casts = [
        'type'           => CriterionType::class,
        'priority'       => CriterionPriority::class,
        'required'       => 'boolean',
        'expected_value' => 'array',
        'weight'         => 'integer',
    ];

    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }
}
