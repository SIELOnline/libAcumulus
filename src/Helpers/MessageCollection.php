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
     * Constructor.
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
     * @param string|Exception|array $message
     *   Either:
     *   - A human readable, thus possibly translated, text.
     *   - An \Exception object, in which case other parameters are ignored.
     *   - An Acumulus API message array,
     *     see {@see https://www.siel.nl/acumulus/API/Basic_Response/}, in which
     *     case the 2nd parameter should be present to indicate the severity.
     * @param int $severity
     *   One of the Severity constants (except Severity::Exception).
     * @param string|int $fieldOrCodeOrTag
     *   Either:
     *   - The code tag part of an Acumulus API message (string) .
     *   - The form field name (string) .
     *   - The code (int) when no code tag is provided in combination with the
     *     code and the code is not a string.
     * @param int|string $code
     *   The code, typically an int, but a string is allowed as well.
     *
     * @return $this
     */
    public function addMessage($message, $severity = Severity::Unknown, $fieldOrCodeOrTag = '', $code = 0)
    {
        if (!$message instanceof Message) {
            switch (func_num_args()) {
                case 2:
                    $message = new Message($message, $severity);
                    break;
                case 3:
                    $message = new Message($message, $severity, $fieldOrCodeOrTag);
                    break;
                case 4:
                default:
                    $message = new Message($message, $severity, $fieldOrCodeOrTag, $code);
                    break;
            }
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
     * 4) Adding an array of text messages, giving them the passed severity.
     *
     * @param \Siel\Acumulus\Helpers\MessageCollection|\Siel\Acumulus\Helpers\Message[]|string[]|array[]|array $messages
     *   The message(s) to add. Either:
     *   - A MessageCollection to be merged into this one.
     *   - An array of Message objects.
     *   - A (numerically indexed) array of Acumulus API messages.
     *   - A (numerically indexed) array of message strings
     *   - An Acumulus API message array (with keys code, codetag and message).
     *     This edge case is supported because of the json conversion of an API
     *     call result might result in just 1 API message instead of an array
     *     with 1 api message.
     * @param int $severity
     *   - If $messages does not contain the severity for the individual
     *     messages (i.e they are not Message objects) the severity is used for
     *     all messages.
     *   - If $messages are Message objects, Severity might indicate the maximum
     *     severity with which to add the messages. This can be used to merge
     *     errors as mere warnings because the main result is not really
     *     influenced by these errors.
     *
     * @return $this
     */
    public function addMessages($messages, $severity = Severity::Unknown)
    {
        // Process $messages so that it becomes an array of messages in
        // whichever form.
        if ($messages instanceof MessageCollection) {
            $messages = $messages->getMessages();
        } elseif (count($messages) === 3 && isset($messages['code']) && isset($messages['codetag']) && isset($messages['message'])) {
            // 1 Acumulus API message array.
            $messages = [$messages];
        }

        foreach ($messages as $key => $message) {
            if (is_string($message)) {
                if (is_string($key)) {
                    // Text message for a field.
                    $this->addMessage($message, $severity, $key);
                } else {
                    // Just a text message.
                    $this->addMessage($message, $severity);
                }
            } elseif (is_array($message)) {
                // An Acumulus API message.
                $this->addMessage($message, $severity);
            } elseif ($severity !== Severity::Unknown && $message->getSeverity() > $severity) {
                // Message object but restrict severity
                if ($message->getField() !== '') {
                    // A Message object with a field.
                    $this->addMessage($message->getText(), $severity, $message->getField());
                } else {
                    // A Message object without a field.
                    $this->addMessage($message->getText(), $severity, $message->getCodeTag(), $message->getCode());
                }
            } else {
                // A message object and no changing of severity.
                $this->addMessage($message);
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
     * Returns the Messages in the collection for the given field.
     *
     * @param string $field
     *
     * @return Message[]
     *   The messages for the given field, may be empty.
     */
    public function getByField($field)
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
     *   - An array of formatted messages, keyed by field or numeric indices
     *     for messages without field.
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
