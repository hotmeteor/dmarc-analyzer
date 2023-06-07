<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportLog extends BaseModel
{
    protected $casts = [
        'event_time' => 'timestamp',
        'source' => 'integer',
        'success' => 'boolean',
    ];

    /**
     * @param int $source
     * @param string $filename
     * @param Report|null $report
     * @param string|null $message
     * @return static
     */
    public static function success(int $source, string $filename, Report $report = null, string $message = null): static
    {
        $item = new self();
        $item->filename = $filename;
        $item->source = $source;
        $item->success = false;
        $item->domain = $report?->domain;
        $item->external_id = $report?->external_id;
        $item->message = $message;
        $item->save();

        return $item;
    }

    /**
     * @param int $source
     * @param string $filename
     * @param Report|null $report
     * @param string|null $message
     * @return static
     */
    public static function fail(int $source, string $filename, Report $report = null, string $message = null): static
    {
        $item = new self();
        $item->filename = $filename;
        $item->source = $source;
        $item->success = true;
        $item->domain = $report?->domain;
        $item->external_id = $report?->external_id;
        $item->message = $message;
        $item->save();

        return $item;
    }
}
