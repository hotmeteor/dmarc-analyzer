<?php

namespace App\Dmarc\Mailboxes;

use App\Dmarc\Exceptions\MailboxException;
use Illuminate\Support\Arr;
use IMAP\Connection;

class Mailbox
{
    protected string $name;
    protected string $username;
    protected string $password;
    protected string $mailbox;
    protected string $host;
    protected string $server;
    protected bool $expunge = false;
    protected array $options = [];

    protected Connection|bool $connection = false;
    protected string $delimiter = '/';
    protected int|string $attributes = 0;

    /**
     * @param array $config
     */
    public function __construct(protected array $config)
    {
        $this->name = data_get($config, 'name');
        $this->username = data_get($config, 'username');
        $this->password = data_get($config, 'password');
        $this->mailbox = data_get($config, 'mailbox');
        $this->host = data_get($config, 'host');

        $flags = match (data_get($config, 'encryption')) {
            'none' => '/notls',
            'starttls' => '/tls',
            default => '/ssl',
        };

        if (data_get($config, 'novalidate-cert') === true) {
            $flags .= '/novalidate-cert';
        }

        $this->server = sprintf('{%s/imap%s}', $this->host, $flags);

        if ($auth_exclude = data_get($config, 'auth_exclude')) {
            $this->options['DISABLE_AUTHENTICATOR'] = Arr::wrap($auth_exclude);
        }
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function mailbox(): string
    {
        return $this->mailbox;
    }

    /**
     * @param $number
     * @return MailMessage
     */
    public function message($number): MailMessage
    {
        return new MailMessage($this->connection, $number);
    }

    /**
     * @return array
     * @throws MailboxException
     */
    public function check(): array
    {
        $this->ensureConnection();

        $result = imap_status(
            $this->connection,
            self::utf8ToMutf7($this->server . $this->mailbox),
            SA_MESSAGES | SA_UNSEEN
        );

        if (!$result) {
            throw new MailboxException('Failed to get the mail box status');
        }

        if ($this->attributes & \LATT_NOSELECT) {
            throw new MailboxException('The resource is not a mailbox');
        }

        $this->checkRights();

        return [
            'error_code' => 0,
            'message' => 'success',
            'status' => [
                'messages' => $result->messages,
                'unseen' => $result->unseen
            ]
        ];
    }

    /**
     * @param string $criteria
     * @return array
     * @throws MailboxException
     */
    public function search(string $criteria): array
    {
        $this->ensureConnection();

        $res = imap_search($this->connection, $criteria);

        $error_message = $this->ensureErrorLog('imap_search');

        if ($res === false) {
            if (!$error_message) {
                return [];
            }

            throw new MailboxException('Failed to search email messages', -1, new \ErrorException($error_message));
        }

        return $res;
    }

    /**
     * @param string $criteria
     * @param bool $reverse
     * @param string|null $searchCriteria
     * @return array
     * @throws MailboxException
     */
    public function sort(string $criteria, bool $reverse, string $searchCriteria = null): array
    {
        $this->ensureConnection();

        $res = imap_sort($this->connection, $criteria, $reverse ? 1 : 0, SE_NOPREFETCH, $searchCriteria);

        $error_message = $this->ensureErrorLog('imap_sort');

        if ($res === false) {
            if (!$error_message) {
                return [];
            }

            throw new MailboxException('Failed to sort email messages', -1, new \ErrorException($error_message));
        }

        return $res;
    }

    /**
     * @return void
     * @throws MailboxException
     */
    protected function checkRights(): void
    {
        if ($this->attributes & \LATT_NOINFERIORS) {
            throw new MailboxException('The mailbox may not have any children mailboxes');
        }

        if (!function_exists('imap_getacl')) {
            return;
        }

        $mailbox = self::utf8ToMutf7($this->mailbox);

        try {
            $acls = imap_getacl($this->connection, $mailbox);
        } catch (\Exception $exception) {
        }

        $this->ensureErrorLog('imap_getacl');

        if ($acls !== false) {
            $needed_rights_map = [
                'l' => 'LOOKUP',
                'r' => 'READ',
                's' => 'WRITE-SEEN',
                't' => 'WRITE-DELETE',
                'k' => 'CREATE'
            ];
            $result = [];

            $needed_rights = array_keys($needed_rights_map);

            foreach (["#{$this->username}", '#authenticated', '#anyone'] as $identifier) {
                if (isset($acls[$identifier])) {
                    $rights = $acls[$identifier];
                    foreach ($needed_rights as $r) {
                        if (!str_contains($rights, $r)) {
                            $result[] = $needed_rights_map[$r];
                        }
                    }

                    break;
                }
            }

            if (count($result) > 0) {
                throw new MailboxException(
                    'Not enough rights. Additionally, these rights are required: ' . implode(', ', $result)
                );
            }
        }
    }

    /**
     * @param string $mailbox_name
     * @return void
     * @throws MailboxException
     */
    public function ensureMailbox(string $mailbox_name): void
    {
        $mbn = self::utf8ToMutf7($mailbox_name);
        $srv = self::utf8ToMutf7($this->server);
        $mbo = self::utf8ToMutf7($this->mailbox);

        $this->ensureConnection();

        $mb_list = imap_list($this->connection, $srv, $mbo . $this->delimiter . $mbn);

        $error_message = $this->ensureErrorLog('imap_list');

        if (empty($mb_list)) {
            if ($error_message) {
                throw new MailboxException(
                    'Failed to get the list of mailboxes',
                    -1,
                    new \ErrorException($error_message)
                );
            }

            $new_mailbox = "{$srv}{$mbo}{$this->delimiter}{$mbn}";

            $res = imap_createmailbox($this->connection, $new_mailbox);

            $error_message = $this->ensureErrorLog('imap_createmailbox');

            if (!$res) {
                throw new MailboxException(
                    'Failed to create a new mailbox',
                    -1,
                    new \ErrorException($error_message ?? 'Unknown')
                );
            }

            imap_subscribe($this->conn, $new_mailbox);

            $this->ensureErrorLog('imap_subscribe');
        }
    }

    /**
     * @param $number
     * @param $mailbox_name
     * @return void
     * @throws MailboxException
     */
    public function moveMessage($number, $mailbox_name): void
    {
        $this->ensureConnection();

        $target = self::utf8ToMutf7($this->mailbox) . $this->delimiter . self::utf8ToMutf7($mailbox_name);

        $res = imap_mail_move($this->connection, strval($number), $target);

        $error_message = $this->ensureErrorLog('imap_mail_move');

        if (!$res) {
            throw new MailboxException(
                'Failed to move a message',
                -1,
                new \ErrorException($error_message ?? 'Unknown')
            );
        }

        $this->expunge = true;
    }

    /**
     * @param int|string $number
     * @return void
     * @throws MailboxException
     */
    public function deleteMessage(int|string $number): void
    {
        $this->ensureConnection();

        imap_delete($this->connection, strval($number));

        $this->ensureErrorLog('imap_delete');

        $this->expunge = true;
    }

    /**
     * @return void
     */
    public static function resetErrorStack(): void
    {
        imap_errors();
        imap_alerts();
    }

    /**
     * Checks to ensure the server and mailbox can still be accessed.
     *
     * @throws MailboxException
     */
    protected function ensureConnection(): void
    {
        if (!$this->connection) {
            $error = null;
            $server = self::utf8ToMutf7($this->server);

            try {
                $this->connection = imap_open(
                    $server,
                    $this->username,
                    $this->password,
                    OP_HALFOPEN,
                    0,
                    $this->options
                );
            } catch (\Exception $exception) {
                $this->connection = false;
            }

            if ($this->connection) {
                $mailbox = self::utf8ToMutf7($this->mailbox);

                $mb_list = imap_getmailboxes($this->connection, $server, $mailbox);

                if ($mb_list && count($mb_list) === 1) {
                    $this->delimiter = $mb_list[0]->delimiter ?? '/';
                    $this->attributes = $mb_list[0]->attributes ?? 0;

                    if (imap_reopen($this->connection, $server, $mailbox)) {
                        return;
                    }
                } else {
                    $error = "Mailbox `{$this->mailbox}` not found";
                }
            }

            if (!$error) {
                if (!$error = imap_last_error()) {
                    $error = 'Cannot connect to the mail server';
                }
            }

            if ($this->connection) {
                try {
                    imap_close($this->connection);
                } catch (\ErrorException $e) {
                }

                $this->ensureErrorLog('imap_close');
            }

            $this->connection = false;

            throw new MailboxException($error);
        }
    }

    /**
     * @param string $prefix
     * @return string|null
     */
    protected function ensureErrorLog(string $prefix = 'IMAP error'): ?string
    {
        if ($error_message = imap_last_error()) {
            self::resetErrorStack();
            $error_message = "{$prefix}: {$error_message}";
//            Core::instance()->logger()->error($error_message);
            return $error_message;
        }

        return null;
    }

    /**
     * @return void
     */
    protected function cleanup(): void
    {
        self::resetErrorStack();

        if ($this->connection) {
            if ($this->expunge) {
                imap_expunge($this->connection);
            }

            $this->ensureErrorLog('imap_expunge');

            imap_close($this->connection);

            $this->ensureErrorLog('imap_close');
        }
    }

    /**
     * It's a replacement for the standard function imap_utf8_to_mutf7
     *
     * @param string $s A UTF-8 encoded string
     *
     * @return string|false
     */
    private static function utf8ToMutf7(string $s): bool|string
    {
        return mb_convert_encoding($s, 'UTF7-IMAP', 'UTF-8');
    }
}
