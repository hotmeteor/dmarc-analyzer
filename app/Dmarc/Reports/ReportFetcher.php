<?php

namespace App\Dmarc\Reports;

use App\Dmarc\Sources\Source;
use App\Models\Report;
use App\Models\ReportLog;
use RuntimeException;

class ReportFetcher
{
    /**
     * @param Source $source
     */
    public function __construct(protected Source $source)
    {
    }

    /**
     * Retrieves report files from the source and stores them in the database
     * taking into account the limits from the configuration file.
     *
     * @return array Array of results.
     * @throws \Exception
     */
    public function fetch(): array
    {
        $this->source->rewind();

        $limit = 0;

        $type = $this->source->type();

        switch ($type) {
            case Source::SOURCE_MAILBOX:
                $onDone = config('dmarc.fetcher.mailboxes.done');
                $onFail = config('dmarc.fetcher.mailboxes.fail');
                $limit = config('dmarc.fetcher.mailboxes.max_messages');
                break;
            case Source::SOURCE_DIRECTORY:
                $onDone = config('dmarc.fetcher.directories.done');
                $onFail = config('dmarc.fetcher.directories.fail');
                $limit = config('dmarc.fetcher.directories.max_messages');
                break;
        }

        if ($type === Source::SOURCE_MAILBOX || $type === Source::SOURCE_DIRECTORY) {
            $this->source->setParams([
                'when_done'   => $onDone,
                'when_failed' => $onFail
            ]);
        }

        $results = [];

        while ($this->source->valid()) {
            $result  = null;
            $fname   = null;
            $report  = null;
            $success = false;
            $err_msg = null;

            // Extracting and saving reports
            try {
                $rfile   = $this->source->current();
                $fname   = $rfile->filename();
                $report  = Report::fromXmlFile($rfile->datastream());
                $result  = $report->save($fname);
                $success = true;
            } catch (RuntimeException $e) {
                $err_msg = $e->getMessage();
//                $result  = ErrorHandler::exceptionResult($e);
            }

            unset($rfile);

            // Post processing
            try {
                if ($success) {
                    $this->source->accepted();
                } else {
                    $this->source->rejected();
                }
            } catch (RuntimeException $e) {
                $err_msg = $e->getMessage();
                $result['post_processing_message'] = $err_msg;
            }

            // Adding a record to the log.
            if (!$err_msg) {
                $log = ReportLog::success($type, $fname, $report);
            } else {
                $log = ReportLog::fail($type, $fname, $report, $err_msg);

                if ($this->source->type() === Source::SOURCE_MAILBOX) {
                    $msg = $this->source->mailMessage();
                    $ov = $msg->overview();
                    if ($ov) {
                        if (array_key_exists('from', $ov)) {
                            $result['emailed_from'] = $ov['from'];
                        }
                        if (array_key_exists('date', $ov)) {
                            $result['emailed_date'] = $ov['date'];
                        }
                    }
                }
                if ($report) {
                    $rd = $report->get();
                    if (isset($rd['external_id'])) {
                        $result['report_id'] = $rd['external_id'];
                    }
                }
            }
            unset($report);

            // Adding result to the results array.
            $results[] = $result;

            // Checking the fetcher limits
            if ($limit > 0) {
                if (--$limit === 0) {
                    break;
                }
            }

            $this->source->next();
        }
        return $results;
    }

    /**
     * Generates the final result based on the results of loading individual report files.
     *
     * @param array $results Array with results of loading report files.
     *
     * @return array Array of the final result to be sent to the client.
     */
    public static function makeSummaryResult(array $results): array
    {
        $reps    = [];
        $others  = [];
        $r_count = 0;
        $loaded  = 0;
        foreach ($results as &$r) {
            if (isset($r['source_error'])) {
                $others[] = $r['source_error'];
            } else {
                $reps[] = $r;
                ++$r_count;
                if (!isset($r['error_code']) || $r['error_code'] === 0) {
                    ++$loaded;
                }
                if (isset($r['post_processing_message'])) {
                    $others[] = $r['post_processing_message'];
                }
            }
        }
        unset($r);

        $result  = null;
        $o_count = count($others);
        if ($r_count + $o_count === 1) {
            if ($r_count === 1) {
                $result = $reps[0];
            } else {
                $result = [
                    'error_code' => -1,
                    'message'    => $others[0]
                ];
            }
        } else {
            $err_code = null;
            $message  = null;
            if ($loaded === $r_count) {
                $err_code = 0;
                if ($r_count > 0) {
                    $message = strval($r_count) . ' report files have been loaded successfully';
                } elseif ($o_count === 0) {
                    $message = 'There are no report files to load';
                } else {
                    $err_code = -1;
                }
            } else {
                $err_code = -1;
                if ($loaded > 0) {
                    $message = "Only {$loaded} of the {$r_count} report files have been loaded";
                } else {
                    $message = "None of the {$r_count} report files has been loaded";
                }
            }
            $result['error_code'] = $err_code;
            $result['message'] = $message;
            if ($r_count > 0) {
                $result['results'] = $reps;
            }
            if ($o_count > 0) {
                $result['other_errors'] = $others;
            }
        }
        return $result;
    }
}
