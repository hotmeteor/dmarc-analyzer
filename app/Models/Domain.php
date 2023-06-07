<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends BaseModel
{
    protected $casts = [
        'active' => 'boolean',
    ];

    protected $attributes = [
        'active' => false,
    ];

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
