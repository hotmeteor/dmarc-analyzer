<?php

namespace App\Dmarc\Mailboxes;

use App\Dmarc\Exceptions\MailAttachmentException;
use IMAP\Connection;

class MailAttachment
{
    protected string $filename;
    protected int $bytes;
    protected int|string $number;
    protected int|string $mnumber;
    protected string $encoding;
    protected mixed $stream = null;
    protected ?string $mime_type = null;

    public function __construct(protected Connection $connection, array $params)
    {
        $this->filename = data_get($params, 'filename');
        $this->bytes = data_get($params, 'bytes');
        $this->number = data_get($params, 'number');
        $this->mnumber = data_get($params, 'mnumber');
        $this->encoding = data_get($params, 'encoding');
    }

    public function __destruct()
    {
        if ($this->stream && get_resource_type($this->stream) == 'stream') {
            fclose($this->stream);
        }
    }

    /**
     * @return string|null
     */
    public function mimeType(): ?string
    {
        if ($this->mime_type) {
//            $this->mime_type = ReportFile::getMimeType($this->filename, $this->datastream());
        }

        return $this->mime_type;
    }

    public function size()
    {
        return $this->bytes;
    }

    public function filename()
    {
        return $this->filename;
    }

    /**
     * @return string
     */
    public function extension(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    /**
     * @return false|mixed|resource|null
     * @throws MailAttachmentException
     */
    public function datastream()
    {
        if (! $this->stream) {
            $this->stream = fopen('php://temp', 'r+');
            fwrite($this->stream, $this->toString());
        }

        rewind($this->stream);

        return $this->stream;
    }

    /**
     * @return bool|string
     */
    private function fetchBody(): bool|string
    {
        return imap_fetchbody($this->connection, $this->mnumber, strval($this->number), FT_PEEK);
    }

    /**
     * @return bool|string
     * @throws MailAttachmentException
     */
    private function toString(): bool|string
    {
        switch ($this->encoding) {
            case ENC7BIT:
            case ENC8BIT:
            case ENCBINARY:
                return $this->fetchBody();
            case ENCBASE64:
                return base64_decode($this->fetchBody());
            case ENCQUOTEDPRINTABLE:
                return imap_qprint($this->fetchBody());
        }

        throw new MailAttachmentException('Encoding failed: Unknown encoding');
    }
}
