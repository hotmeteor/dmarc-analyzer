<?php

namespace App\Dmarc\Mailboxes;

use App\Dmarc\Exceptions\MailboxException;
use App\Dmarc\Exceptions\MailMessageException;
use IMAP\Connection;

class MailMessage
{
    protected MailAttachment|null $attachment = null;
    protected int $attachmentCount = -1;

    /**
     * @param Connection $connection
     * @param string $number
     */
    public function __construct(protected Connection $connection, protected string $number)
    {
    }

    /**
     * @return mixed|null
     */
    public function attachment(): mixed
    {
        return $this->attachment;
    }

    /**
     * @return array|false
     */
    public function overview(): array|false
    {
        $res = @imap_fetch_overview($this->connection, strval($this->number));

        if (!isset($res[0])) {
            if ($error_message = imap_last_error()) {
//                Core::instance()->logger()->error("imap_fetch_overview failed: {$error_message}");
            }

            Mailbox::resetErrorStack();

            return false;
        }

        return $res[0];
    }

    /**
     * @return void
     * @throws MailboxException
     */
    public function setSeen(): void
    {
        if (!@imap_setflag_full($this->connection, strval($this->number), '\\Seen')) {
            if ($error_message = imap_last_error()) {
                $error_message = '?';
            }

            Mailbox::resetErrorStack();
//            Core::instance()->logger()->error("imap_setflag_full failed: {$error_message}");

            throw new MailboxException("Failed to make a message seen: {$error_message}");
        }
    }

    /**
     * @return void
     * @throws MailMessageException
     * @throws MailboxException
     */
    public function validate(): void
    {
        $this->ensureAttachment();

        if ($this->attachmentCount !== 1) {
            throw new MailMessageException("Attachment count is not valid ({$this->attachmentCount})");
        }

        $bytes = $this->attachment->size();

        if ($bytes === -1) {
            throw new MailMessageException("Failed to get attached file size. Wrong message format?");
        }

        if ($bytes < 50 || $bytes > 1 * 1024 * 1024) {
            throw new MailMessageException("Attachment file size is not valid ({$bytes} bytes)");
        }

        $mime_type = $this->attachment->mimeType();

        if (!in_array($mime_type, [ 'application/zip', 'application/gzip', 'application/x-gzip', 'text/xml' ])) {
            throw new MailMessageException("Attachment file type is not valid ({$mime_type})");
        }
    }

    /**
     * @return void
     * @throws MailboxException
     */
    private function ensureAttachment(): void
    {
        if ($this->attachments_cnt === -1) {

            $structure = imap_fetchstructure($this->conn, $this->number);

            if ($structure === false) {
                throw new MailboxException('FetchStructure failed: ' . imap_last_error());
            }

            $this->attachmentCount = 0;

            $parts = $structure->parts ?? [$structure];

            foreach ($parts as $index => &$part) {
                $att_part = $this->scanAttachmentPart($part, $index + 1);
                if ($att_part) {
                    ++$this->attachmentCount;
                    if (!$this->attachment) {
                        $this->attachment = new MailAttachment($this->connection, $att_part);
                    }
                }
            }

            unset($part);
        }
    }

    /**
     * @param $part
     * @param $number
     * @return array|null
     */
    private function scanAttachmentPart(&$part, $number): ?array
    {
        $filename = null;

        if ($part->ifdparameters) {
            $filename = $this->getAttribute($part->dparameters, 'filename');
        }

        if (empty($filename) && $part->ifparameters) {
            $filename = $this->getAttribute($part->parameters, 'name');
        }

        if (empty($filename)) {
            return null;
        }

        return [
            'filename' => imap_utf8($filename),
            'bytes'    => $part->bytes ?? -1,
            'number'   => $number,
            'mnumber'  => $this->number,
            'encoding' => $part->encoding
        ];
    }

    /**
     * @param $params
     * @param $name
     * @return mixed
     */
    private function getAttribute(&$params, $name): mixed
    {
        // need to check all objects as imap_fetchstructure
        // returns multiple objects with the same attribute name,
        // but first entry contains a truncated value
        $value = null;
        foreach ($params as &$obj) {
            if (strcasecmp($obj->attribute, $name) === 0) {
                $value = $obj->value;
            }
        }

        return $value;
    }
}
