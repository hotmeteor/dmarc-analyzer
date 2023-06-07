<?php

namespace App\Console\Commands;

use App\Dmarc\Mailboxes\Mailbox;
use App\Dmarc\Reports\ReportFetcher;
use App\Dmarc\Sources\MailboxSource;
use App\Dmarc\Sources\Source;
use Illuminate\Console\Command;

class FetchReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-reports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * @var array
     */
    protected array $sources = [];

    /**
     * @var array
     */
    protected array $errors = [];

    /**
     * Execute the console command.
     * @throws \Exception
     */
    public function handle(): int
    {
        $this->prepareMailboxes();

        $this->fetchSources();

        return Command::SUCCESS;
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function fetchSources(): void
    {
        $problems = [];

        /* @var Source $source */
        foreach ($this->sources as $source) {
            $fetcher = new ReportFetcher($source);

            foreach ($fetcher->fetch() as $result) {
                $messages = [];

                if (isset($res['source_error'])) {
                    $messages[] = $res['source_error'];
                }

                if (isset($res['post_processing_message'])) {
                    $messages[] = $res['post_processing_message'];
                }

                if (isset($res['error_code']) && $res['error_code'] !== 0 && isset($res['message'])) {
                    $messages[] = $res['message'];
                }

                if (count($messages) > 0) {
                    $pr = ['messages' => $messages];

                    foreach (['report_id', 'emailed_from', 'emailed_date'] as $it) {
                        if (isset($res[$it])) {
                            $pr[$it] = $res[$it];
                        }
                    }

                    if ($source->type() === Source::SOURCE_MAILBOX) {
                        $cont = $source->container();
                        $pr['mailbox'] = $cont->mailbox() . ' (' . $cont->name() . ')';
                    }

                    $problems[] = $pr;
                }
            }
        }

        if (count($problems) > 0) {
            $debug_info = null;

            foreach ($problems as $i => $pr) {
//                if ($i > 0) {
//                    echo PHP_EOL;
//                }
//                switch ($pr['state']) {
//                    case MAILBOX_LIST:
//                        echo 'Failed to get mailbox list:';
//                        break;
//                    case DIRECTORY_LIST:
//                        echo 'Failed to get directory list:';
//                        break;
//                    case FETCHER:
//                        echo 'Failed to get incoming report:';
//                        break;
//                }
//                echo PHP_EOL;
//                echo '  Error message:', PHP_EOL;
//                $messages = array_map(function ($msg) {
//                    return "    - {$msg}";
//                }, $pr['messages']);
//                echo implode(PHP_EOL, $messages), PHP_EOL;

                if (isset($pr['report_id'])) {
                    echo "  Report ID: {$pr['report_id']}", PHP_EOL;
                }

                if (isset($pr['emailed_from']) || isset($pr['emailed_date']) || isset($pr['mailbox'])) {
                    echo '  Email message metadata:', PHP_EOL;
                    echo '    - From:    ' . ($pr['emailed_from'] ?? '-'), PHP_EOL;
                    echo '    - Date:    ' . ($pr['emailed_date'] ?? '-'), PHP_EOL;
                    echo '    - Mailbox: ' . ($pr['mailbox'] ?? '-'), PHP_EOL;
                }

                if (!$debug_info && !empty($pr['debug_info'])) {
                    $debug_info = $pr['debug_info'];
                }
            }

            if ($debug_info) {
                echo PHP_EOL;
                echo 'Debug information:', PHP_EOL, $debug_info, PHP_EOL;
            }
        }
    }

    /**
     * @return void
     */
    protected function prepareMailboxes(): void
    {
        foreach (config('dmarc.mailboxes') as $key => $config) {
            $mailbox = new Mailbox($config);

            try {
                $this->sources[] = new MailboxSource($mailbox);
            } catch (\Exception $exception) {
                $this->addError($exception);
            }
        }
    }

    /**
     * @param \Throwable $e
     * @return void
     */
    protected function addError(\Throwable $e): void
    {
        $this->errors[] = $e->getMessage();
    }
}
