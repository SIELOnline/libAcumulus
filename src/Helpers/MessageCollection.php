<?php
namespace Siel\Acumulus\Helpers;

use Throwable;

/**
 * Class MessageCollection contains a set of Messages.
 *
 * A MessageCollection is a set of {@see Message}s that allow to group,
 * retrieve, and display multiple messages.
 *
 * {@see Message} and MessageCollection contain a peculiarity: As a
 * {@see Message} is one of the few objects that does not get created via the
 * {@see Container|, it is not possible to inject the {@see Translator} to a
 * single {@see Message} upon construction. However, as messages will only be
 * created as part of a MessageCollection, the MessageCollection will inject the
 * {@see Translator} before a {@see Message} gets displayed or logged.
 */
class MessageCollection
{
    protected /*Translator*/ $translator;
    /**
     * @var Message[]
     */
    protected $messages = [];

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     *
    */
    protected function t(string $key): string
    {
        return $this->translator->get($key);
    }

    /**
     * Adds a {@see Message} to the collection.
     *
     * @return $this
     */
    public function addMessage(Message $message): MessageCollection
    {
        $this->messages[] = $message->setTranslator($this->translator);
        return $this;
    }

    /**
     * Adds a simple message based on its text, severity, and optional code.
     *
     * @param string $message
     *   The text of the message.
     * @param int $severity
     *   One of the Severity::... constants.
     * @param int|string $code
     *   A code to better identify the source of the message.
     *
     * @return $this
     */
    public function createAndAddMessage(string $message, int $severity, $code = 0): MessageCollection
    {
        return $this->addMessage(Message::create($message, $severity, $code));
    }

    public function addException(Throwable $e): MessageCollection
    {
        return $this->addMessage(Message::createFromException($e));
    }

    /**
     * Merges a set of {@see Message}s into this MessageCollection.
     *
     * @param Message[] $messages
     * @param int $severity
     *   If passed, it indicates the maximum severity with which to add the
     *   messages. This can be used, e.g., to merge errors as mere warnings
     *   because the main result is not really influenced by these errors.
     *
     * @return $this
     */
    public function addMessages(array $messages, int $severity = Severity::Unknown): MessageCollection
    {
        foreach ($messages as $message) {
            if ($severity !== Severity::Unknown && $message->getSeverity() > $severity) {
                $message->setSeverity($severity);
            }
            $this->messages[] = $message;
        }
        return $this;
    }

    /**
     * @return int
     *   1 of the Severity::... constants.
     */
    public function getSeverity(): int
    {
        $result = Severity::Unknown;
        foreach ($this->getMessages() as $message) {
            $result = max($result, $message->getSeverity());
        }
        return $result;
    }

    /**
     * Returns whether the result contains a warning, error or exception.
     *
     * @return bool
     *   True if the result contains at least 1 notice, warning, error or
     *   exception, false otherwise.
     */
    public function hasRealMessages(): bool
    {
        return $this->getSeverity() >= Severity::Info;
    }

    /**
     * Returns whether the result contains errors or an exception.
     *
     * @return bool
     *   True if the result status indicates if there were errors or an
     *   exception, false otherwise.
     */
    public function hasError(): bool
    {
        return $this->getSeverity() >= Severity::Error;
    }

    /**
     * Returns whether the Message collection contains a given code.
     *
     * Though it is expected that codes and code tags are unique, this is not
     * imposed. If multiple messages with the same code or code tag exists, the
     * 1st found will be returned.
     *
     * @param int|string $code
     *   The code to search for, note that due to the PHP comparison rules 403
     *   will match '403 Forbidden', but '403' won't.
     *
     * @return Message|null
     *   The message with the given code if the result contains such a message,
     *   null otherwise.
     */
    public function getByCode($code): ?Message
    {
        foreach ($this->getMessages() as $message) {
            if ($message->getCode() == $code) {
                return $message;
            }
        }
        return null;
    }

    /**
     * Returns whether the Message collection contains a given codeTag.
     *
     * Though it is expected that codes and code tags are unique, this is not
     * imposed. If multiple messages with the same code or code tag exists, the
     * 1st found will be returned.
     *
     * @param string $codeTag
     *
     * @return Message|null
     *   The message with the given code tag if the result contains such a
     *   message, null otherwise.
     */
    public function getByCodeTag(string $codeTag): ?Message
    {
        foreach ($this->getMessages() as $message) {
            if ($message->getCodeTag() === $codeTag) {
                return $message;
            }
        }
        return null;
    }

    /**
     * Returns the Messages in the collection for the given field.
     *
     * @param string $field
     *
     * @return Message[]
     *   The messages for the given field, may be empty.
     */
    public function getByField(string $field): array
    {
        $result = [];
        foreach ($this->getMessages() as $message) {
            if ($message->getField() === $field) {
                $result[] = $message;
            }
        }
        return $result;
    }

    /**
     * @param int $severity
     *   A severity level to get the messages for. This may be a bitwise
     *   combination of multiple severities.
     *
     * @return \Siel\Acumulus\Helpers\Message[]
     */
    public function getMessages(int $severity = Severity::All): array
    {
        if ($severity === Severity::All) {
            $result = $this->messages;
        } else {
            $result = [];
            foreach ($this->messages as $message) {
                if (($message->getSeverity() & $severity) !== 0) {
                    $result[] = $message;
                }
            }
        }
        return $result;
    }

    /**
     * Formats a set of messages.
     *
     * @param int $format
     *   The format in which to return the messages, one of the
     *   Message::Format_... constants.
     * @param int $severity
     *   A bitwise combination of 1 or more severities to restrict returning
     *   formatted messaged to those of the given severities.
     *
     * @return string|string[]
     *   Depending on $format, either:
     *   - An array of formatted messages, keyed by field or numeric indices
     *     for messages without field.
     *   - A string containing an HTML or plain text list of formatted messages.
     *
     * @see Message::format()
     */
    public function formatMessages(int $format, int $severity = Severity::All)
    {
        $result = [];
        foreach ($this->getMessages($severity) as $message) {
            if (($message->getSeverity() & $severity) !== 0) {
                $result[] = $message->format($format);
            }
        }
        if (($format & Message::Format_ListItem) !== 0) {
            // We are making 1 sting of it.
            $result = implode("\n", $result);
            // Add additional markup/newline, but only if there actually are
            // messages.
            if (!empty($result)) {
                if (($format & Message::Format_Html) !== 0) {
                    $result = "<ul>\n" . $result . "</ul>\n";
                } else {
                    $result = $result . "\n";
                }
            }
        }
        return $result;
    }
}
