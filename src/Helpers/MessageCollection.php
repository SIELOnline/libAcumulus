<?php
namespace Siel\Acumulus\Helpers;

use Exception;

/**
 * Class MessageCollection wraps an Acumulus web service result into an object.
 *
 * A Result object will contain the:
 * - result status (internal code: one of the self::Status_... constants).
 * - exception, if one was thrown.
 * - any error messages, local and/or remote.
 * - any warnings, local and/or remote.
 * - any notices, local.
 * - message sent, for logging purposes.
 * - (raw) message received, for logging purposes.
 * - result array.
 */
class MessageCollection
{
    /**
     * @var Message[]
     */
    protected $messages;

    /**
     * Result constructor.
     */
    public function __construct()
    {
        $this->messages = [];
    }

    /**
     * Constructs and adds a Message to the collection.
     *
     * NOTE: This is an "overloaded" method. Parameter naming is based on the
     * case where all parameters are supplied.
     *
     * @param int|Exception|\Siel\Acumulus\Helpers\Message $severity
     *   Either:
     *   - One of the Severity constants (except Severity::Exception)
     *   - An \Exception object, in which case other parameters are ignored.
     *   - An \Siel\Acumulus\Web\Message object, in which case other parameters
     *     are ignored
     * @param int|string|array $code
     *   Either:
     *   - The code, typically an int, but a string is allowed as well.
     *   - An Acumulus API message array,
     *     see {@see https://www.siel.nl/acumulus/API/Basic_Response/}.
     *     In which case further parameters are ignored.
     * @param $codeTag
     *   The code tag part of an Acumulus API message.
     * @param $text
     *   A human readable, thus possibly translated, text.
     *
     * @return $this
     */
    public function addMessage($severity, $code = 0, $codeTag = '', $text = '')
    {
        if ($severity instanceof Message) {
            $message = $severity;
        } else {
            $message = new Message($severity, $code, $codeTag, $text);
        }
        $this->messages[] = $message;
        return $this;
    }

    /**
     * Adds messages to this collection.
     *
     * NOTE: This is an "overloaded" method. Parameter naming is based on the
     * first case described.
     *
     * The message(s) can be passed in 3 formats:
     * 1) Adding a single Acumulus API message(s), see
     *    {@see https://www.siel.nl/acumulus/API/Basic_Response/}.
     * 2) Adding an array of Acumulus API message(s).
     * 3) Merging 2 MessageCollection objects. This allows to inform the user
     *    about errors and warnings that occurred during additional API calls,
     *    e.g. querying VAT rates or deleting old entries.
     *
     * @param array|array[]|\Siel\Acumulus\Helpers\Message[] $messages
     *   The message(s) to add. These are either:
     *   - 1 Acumulus API message array (with keys code, codetag and message).
     *   - A numerically indexed array of Acumulus API messages.
     *   - An array of \Siel\Acumulus\Web\Message objects.
     * @param int|bool $severity
     *   - If the messages are Acumulus API message arrays: the severity of the
     *     messages, either Severity::Error or Severity::Warning.
     *   - If the messages are Message objects, whether errors should be merged
     *     as errors or as mere warnings because the main result is not really
     *     influenced by these errors.
     *
     * @return $this
     */
    public function addMessages(array $messages, $severity = false)
    {
        if (is_int($severity) && is_string(key($messages))) {
            // 1 Acumulus API message array.
            $this->addMessage($severity, $messages);
        } else {
            foreach ($messages as $message) {
                if (is_array($message)) {
                    $this->addMessage($severity, $message);
                } elseif ($severity && $message->getSeverity() === Severity::Error) {
                    $this->addMessage(Severity::Warning, $message->getCode(), $message->getCodeTag(), $message->getText());
                } else {
                    $this->addMessage($message);
                }
            }
        }
        return $this;
    }

    /**
     * @return int
     *   1 of the Severity::... constants.
     */
    public function getSeverity()
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
    public function hasRealMessages()
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
    public function hasError()
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
    public function getByCode($code)
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
    public function getByCodeTag($codeTag)
    {
        foreach ($this->getMessages() as $message) {
            if ($message->getCodeTag() === $codeTag) {
                return $message;
            }
        }
        return null;
    }

    /**
     * @param int $severity
     *   A severity level to get the messages for. May be a bitwise combination
     *   of multiple severities.
     *
     * @return \Siel\Acumulus\Helpers\Message[]
     */
    public function getMessages($severity = Severity::All)
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
     *   - An array of formatted messages.
     *   - A string containing an html or plain text list of formatted messages.
     *
     * @see Message::format()
     */
    public function formatMessages($format, $severity = Severity::All)
    {
        $result = array();
        foreach ($this->getMessages($severity) as $message) {
            if (($message->getSeverity() & $severity) !== 0) {
                $result[] = $message->format($format);
            }
        }
        if (($format & Message::Format_ListItem) !== 0) {
            $result = implode("\n", $result);
            if (($format & Message::Format_Html) !== 0) {
                $result = "<ul>\n" . $result . "</ul>\n";
            } else {
                $result = $result . "\n";
            }
        }
        return $result;
    }
}
