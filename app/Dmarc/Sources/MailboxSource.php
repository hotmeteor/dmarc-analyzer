<?php

namespace App\Dmarc\Sources;

use App\Dmarc\Exceptions\MailboxException;
use App\Dmarc\Mailboxes\MailMessage;
use App\Dmarc\Reports\ReportFile;

class MailboxSource extends Source
{
    private array $list = [];
    private array $params = [];
    private int $index = 0;
    private MailMessage|null $message = null;

    /**
     * @param array $params
     * @return void
     */
    public function setParams(array $params): void
    {
        $this->params['when_done'] = SourceAction::fromSetting(
            $params['when_done'] ?? [],
            0,
            'mark_seen'
        );

        $this->params['when_failed'] = SourceAction::fromSetting(
            $params['when_failed'] ?? [],
            0,
            'move_to:failed'
        );
    }


    public function current(): object
    {
        $this->message = $this->data->message($this->list[$this->index]);
//        try {
        $this->message->validate();
//        } catch (SoftException $e) {
//            throw new SoftException('Incorrect message: ' . $e->getMessage(), $e->getCode());
//        } catch (RuntimeException $e) {
//            throw new RuntimeException('Incorrect message', -1, $e);
//        }

        $att = $this->message->attachment();

        return ReportFile::fromStream($att->datastream(), $att->filename(), $att->mimeType());
    }

    /**
     * Returns the index of the current email message.
     *
     * @return int
     */
    public function key(): int
    {
        return $this->index;
    }

    /**
     * Moves forward to the next email message
     *
     * @return void
     */
    public function next(): void
    {
        $this->message = null;
        ++$this->index;
    }

    /**
     * Gets a list of unread messages and rewinds the position to the first email message.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->message = null;
        $this->list = $this->data->sort(SORTDATE, 'UNSEEN', false);
        $this->index = 0;
//        $this->params = [];
    }

    /**
     * Checks if the current position is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->list[$this->index]);
    }

    /**
     * Processes the accepted email messages according to the settings
     *
     * @return void
     */
    public function accepted(): void
    {
        if ($this->message) {
            $this->processMessageActions($this->params['when_done']);
        }
    }

    /**
     * Processes the rejected email messages according to the settings
     *
     * @return void
     */
    public function rejected(): void
    {
        $this->processMessageActions($this->params['when_failed']);
    }

    /**
     * Returns type of the source.
     *
     * @return int
     */
    public function type(): int
    {
        return Source::SOURCE_MAILBOX;
    }

    /**
     * Returns the current email message.
     *
     * @return MailMessage|null
     */
    public function mailMessage(): ?MailMessage
    {
        return $this->message;
    }

    /**
     * Processes the current report message according to settings
     *
     * @param array $actions List of actions to apply to the message
     *
     * @return void
     */
    private function processMessageActions(array &$actions): void
    {
        foreach ($actions as $sa) {
            switch ($sa->type) {
                case SourceAction::ACTION_SEEN:
                    $this->markMessageSeen();
                    break;
                case SourceAction::ACTION_MOVE:
                    $this->moveMessage($sa->param);
                    break;
                case SourceAction::ACTION_DELETE:
                    $this->deleteMessage();
                    break;
            }
        }
    }

    /**
     * Marks the current report message as seen
     *
     * @return void
     * @throws MailboxException
     */
    public function markMessageSeen(): void
    {
        $this->message->setSeen();
    }

    /**
     * Moves the current report message
     *
     * @param string $mbox_name Child mailbox name where to move the current message to.
     *                          If the target mailbox does not exists, it will be created.
     *
     * @return void
     */
    private function moveMessage(string $mbox_name): void
    {
        $this->data->ensureMailbox($mbox_name);
        $this->data->moveMessage($this->list[$this->index], $mbox_name);
    }

    /**
     * Deletes the current report message
     *
     * @return void
     */
    private function deleteMessage(): void
    {
        $this->data->deleteMessage($this->list[$this->index]);
    }
}
