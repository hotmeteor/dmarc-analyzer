<?php

namespace App\Dmarc\Sources;

use Iterator;

abstract class Source implements Iterator
{
    public const SOURCE_UPLOADED_FILE = 1;
    public const SOURCE_MAILBOX = 2;
    public const SOURCE_DIRECTORY = 3;

    public function __construct(protected $data)
    {
    }

    public function setParams(array $params): void
    {
    }

    /**
     * @return object
     */
    abstract public function current(): object;

    /**
     * @return int
     */
    abstract public function key(): int;

    /**
     * @return void
     */
    abstract public function next(): void;

    /**
     * @return void
     */
    abstract public function rewind(): void;

    /**
     * @return bool
     */
    abstract public function valid(): bool;

    /**
     * Called when the current report has been successfully processed.
     *
     * @return void
     */
    public function accepted(): void
    {
    }

    /**
     *  Called when the current report has been rejected.
     *
     * @return void
     */
    public function rejected(): void
    {
    }

    /**
     * Returns type of source, i.e. one of Source::SOURCE_* values
     *
     * @return int
     */
    abstract public function type(): int;

    /**
     * @return mixed
     */
    public function container(): mixed
    {
        return $this->data;
    }
}
