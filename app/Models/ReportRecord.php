<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRecord extends BaseModel
{
    protected $casts = [
        'rcount' => 'integer',
        'disposition' => 'integer',
        'dkim_align' => 'integer',
        'spf_align' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
