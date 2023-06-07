<?php

namespace App\Models;

use App\Models\Concerns\HandlesReportData;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends BaseModel
{
    use HandlesReportData;

    protected $casts = [
        'start_time' => 'timestamp',
        'end_time' => 'timestamp',
        'loaded_time' => 'timestamp',
        'seen' => 'boolean',
    ];

    protected $attributes = [
        'seen' => false,
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(ReportRecord::class, 'report_id');
    }

    public static function failed(int $source, $report, $filename, $message, $db = null)
    {
        $li = new ReportLogItem($source, $filename, $db);
        $li->data['success'] = false;
        if (!is_null($report)) {
            $rdata = $report->get();
            $li->data['domain'] = $rdata['domain'];
            $li->data['external_id'] = $rdata['external_id'];
        } else {
            $li->data['domain'] = null;
            $li->data['external_id'] = null;
        }
        $li->data['message'] = $message;
        return $li;
    }
}
