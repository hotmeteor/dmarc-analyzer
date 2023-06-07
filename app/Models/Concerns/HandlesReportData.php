<?php

namespace App\Models\Concerns;

use App\Dmarc\Reports\ReportData;
use App\Models\Report;

trait HandlesReportData
{
    /**
     * @param $fd
     * @return Report|HandlesReportData
     * @throws \Exception
     */
    public static function fromXmlFile($fd): self
    {
        $data = ReportData::fromXmlFile($fd);

        if (!self::checkData($data)) {
            throw new \Exception('Incorrect or incomplete report data');
        }

        return new Report($data);
    }

    /**
     * @param array $data
     * @return bool
     */
    private static function checkData(array $data): bool
    {
        static $fields = [
            'domain' => ['required' => true, 'type' => 'string'],
            'begin_time' => ['required' => true, 'type' => 'object'],
            'end_time' => ['required' => true, 'type' => 'object'],
            'org' => ['required' => true, 'type' => 'string'],
            'external_id' => ['required' => true, 'type' => 'string'],
            'email' => ['required' => false, 'type' => 'string'],
            'extra_contact_info' => ['required' => false, 'type' => 'string'],
            'error_string' => ['required' => false, 'type' => 'array'],
            'policy_adkim' => ['required' => false, 'type' => 'string'],
            'policy_aspf' => ['required' => false, 'type' => 'string'],
            'policy_p' => ['required' => false, 'type' => 'string'],
            'policy_sp' => ['required' => false, 'type' => 'string'],
            'policy_pct' => ['required' => false, 'type' => 'string'],
            'policy_fo' => ['required' => false, 'type' => 'string'],
            'records' => ['required' => true, 'type' => 'array']
        ];
        if (!self::checkRow($data, $fields) || count($data['records']) === 0) {
            return false;
        }

        static $rfields = [
            'ip' => ['required' => true, 'type' => 'string'],
            'rcount' => ['required' => true, 'type' => 'integer'],
            'disposition' => ['required' => true, 'type' => 'string'],
            'reason' => ['required' => false, 'type' => 'array'],
            'dkim_auth' => ['required' => false, 'type' => 'array'],
            'spf_auth' => ['required' => false, 'type' => 'array'],
            'dkim_align' => ['required' => true, 'type' => 'string'],
            'spf_align' => ['required' => true, 'type' => 'string'],
            'envelope_to' => ['required' => false, 'type' => 'string'],
            'envelope_from' => ['required' => false, 'type' => 'string'],
            'header_from' => ['required' => false, 'type' => 'string']
        ];

        foreach ($data['records'] as &$rec) {
            if (gettype($rec) !== 'array' || !self::checkRow($rec, $rfields)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks one row of report data
     *
     * @param array $row Data row
     * @param array $def Row definition
     *
     * @return bool
     */
    private static function checkRow(array &$row, array &$def): bool
    {
        foreach ($def as $key => &$dd) {
            if (isset($row[$key])) {
                if (gettype($row[$key]) !== $dd['type']) {
                    return false;
                }
            } elseif ($dd['required']) {
                return false;
            }
        }

        return true;
    }
}
