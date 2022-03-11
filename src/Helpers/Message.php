<?php
namespace Siel\Acumulus\Helpers;

use Exception;

/**
 * Class Message defines a message.
 *
 * Messages may appear in any part of the system and need often be transferred
 * through the system layers and displayed on screen, in log files or in mails.
 *
 * Therefore, we define 1 class that wraps messages of all possible sources and
 * possible places to show.
 */
class Message
{
    // Formats in which to return messages.
    /** @var int Format as plain text */
    public const Format_Plain = 0;
    /** @var int Format as html. */
    public const Format_Html = 1;
    // PHP7.1: These 2 could become protected.
    /** @var int Format as list item */
    public const Format_ListItem = 2;
    /** @var int Format with the severity level prepended. */
    public const Format_AddSeverity = 4;
    // Combinations of the above.
    public const Format_PlainWithSeverity = self::Format_Plain | self::Format_AddSeverity;
    public const Format_HtmlWithSeverity = self::Format_Html | self::Format_AddSeverity;
    public const Format_PlainList = self::Format_Plain | self::Format_ListItem;
    public const Format_HtmlList = self::Format_Html | self::Format_ListItem;
    public const Format_PlainListWithSeverity = self::Format_Plain | self::Format_ListItem | self::Format_AddSeverity;
    public const Format_HtmlListWithSeverity = self::Format_Html | self::Format_ListItem | self::Format_AddSeverity;

    /** @var string */
    protected $text;

    /** @var int */
    protected $severity;

    /** @var int|string */
    protected $code;

    /** @var string */
    protected $codeTag;

    /** @var string */
    protected $field;

    /** @var \Exception|null */
    protected $exception;

    /**
     * Message constructor.
     *
     * NOTE: This is an "overloaded" method. Parameter naming is based on the
     * case where all parameters are supplied.
     *
     * @param string|Exception|array $message
     *   Either:
     *   - A human-readable, thus possibly translated, text.
     *   - An \Exception object, in which case other parameters are ignored.
     *   - An Acumulus API message array,
     *     see {@link https://www.siel.nl/acumulus/API/Basic_Response/}, in which
     *     case the 2nd parameter should be present to indicate the severity.
     * @param int $severity
     *   One of the Severity constants (except Severity::Exception).
     * @param string $fieldOrCodeOrTag
     *   Either:
     *   - The 'codetag' part of an Acumulus API message.
     *   - The form field name.
     *   - An integer code. THe 4th parameter should be absent.
     * @param int|string $code
     *   The code, typically an int, but a string is allowed as well.
     *
     * @todo: create separate message create functions (createExceptionMessage,
     *   createFormFieldMessage, CreateApiMessage, createInternalMessage to
     *   simplify this constructor.
     */
    public function __construct($message, int $severity = Severity::Unknown, string $fieldOrCodeOrTag = '', $code = 0)
    {
        // PHP7: instanceof Throwable.
        if ($message instanceof Exception) {
            // Only 1 argument: an Exception.
            $this->text = $message->getMessage();
            $this->severity = Severity::Exception;
            $this->code = $message->getCode();
            $this->codeTag = '';
            $this->exception = $message;
            $this->field = '';
        } else {
            $this->severity = $severity;
            $this->exception = null;
            if (is_array($message)) {
                // Only 1 argument: a Message to be cloned.
                $this->text = $message['message'];
                $this->code = $message['code'];
                $this->codeTag = $message['codetag'];
                $this->field = '';
            } else {
                $this->text = $message;
                if (is_int($fieldOrCodeOrTag)) {
                    // It's an integer, thus a code.
                    $this->code = $fieldOrCodeOrTag;
                    $this->codeTag = '';
                    $this->field = '';
                } elseif (func_num_args() === 3) {
                    // 3 parameters passed, 3rd parameter is a string indicating
                    // a form field.
                    $this->field = $fieldOrCodeOrTag;
                    $this->code = 0;
                    $this->codeTag = '';
                } else {
                    // 2 or 4 parameters passed: 3 and 4 are codeTag resp. code.
                    $this->code = $code;
                    $this->codeTag = $fieldOrCodeOrTag;
                    $this->field = '';
                }
            }
        }
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
     */
    protected function t(string $key): string
    {
        return Translator::$instance instanceof Translator ? Translator::$instance->get($key) : $key;
    }

    /**
     * @return string
     *   A human-readable, thus possibly translated, text.
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @return int
     *   One of the Severity::... constants.
     */
    public function getSeverity(): int
    {
        return $this->severity;
    }

    /**
     * Returns a textual representation of the status.
     *
     * @return string
     */
    public function getSeverityText(): string
    {
        switch ($this->getSeverity()) {
            case Severity::Success:
            case Severity::Log:
            case Severity::Info:
            case Severity::Notice:
            case Severity::Warning:
            case Severity::Error:
            case Severity::Exception:
            case Severity::Unknown:
                return $this->t($this->getSeverity());
            default:
                return sprintf($this->t('severity_unknown'), $this->getSeverity());
        }
    }

    /**
     * @return int|string
     *   A code identifying the message, typically:
     *   - An http response code.
     *   - The exception code.
     *   - The Acumulus API message code, usually a number 4xx, 5xx, or 6xx,
     *     see {@see https://www.siel.nl/acumulus/API/Basic_Response/}.
     *   - A 7xx number used internally to define messages.
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return string
     *   A code tag used by the Acumulus API to report errors or warnings,
     *   see {@see https://www.siel.nl/acumulus/API/Basic_Response/}. For
     *   messages with another source, it will be empty.
     */
    public function getCodeTag(): string
    {
        return $this->codeTag;
    }

    /**
     * @return string
     *   The (form) field name at which this message points.
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return \Exception|null
     *   The exception used to construct this message, or null if this message
     *   is not a Severity::Exception level message.
     */
    public function getException(): ?Exception
    {
        return $this->exception;
    }

    /**
     * Returns a formatted message text.
     *
     * - In the basis it returns: "code, codeTag: Text".
     * - If Format_AddSeverity is set, "Severity :" will be prepended if it is
     *   info or higher severity (i.e. non-log and non-success).
     * - If Format_Html is set, the 2 or 3 parts of the message will each be
     *   wrapped in a <span> and newlines in the message text will be converted
     *   to <br>.
     * - If Format_ListItem is set, list indication will be added, either a
     *   "* ...\n" or a "<li>...</li>".
     *
     * @param int $format
     *   Any (mix) of the Format_... constants.
     *
     * @return string
     *   The formatted message.
     */
    public function format(int $format): string
    {
        $isHtml = ($format & self::Format_Html) !== 0;
        $text = '';

        // Severity.
        if (($format & self::Format_AddSeverity) !== 0 && ($this->getSeverity() & Severity::InfoOrWorse) !== 0) {
            $severity = $this->getSeverityText() . ':';
            if ($isHtml) {
                $severity = '<span>' . htmlspecialchars($severity, ENT_NOQUOTES) . '</span>';
            }
            $text .= $severity . ' ';
        }

        // Code and code tag.
        $codes = implode(', ', array_filter([$this->getCode(), $this->getCodeTag()]));
        if (!empty($codes)) {
            $codes .= ':';
            if ($isHtml) {
                $codes = '<span>' . htmlspecialchars($codes, ENT_NOQUOTES) . '</span>';
            }
            $text .= $codes . ' ';
        }

        // Text.
        $messageText = $this->getText();
        if ($isHtml) {
            $messageText = '<span>' .  htmlspecialchars($messageText, ENT_NOQUOTES) . '</span>';
            $messageText = nl2br($messageText, false);
        }
        $text .= $messageText;

        // List item:
        if (($format & self::Format_ListItem) !== 0) {
            $text = $isHtml ? "<li>$text</li>" : "• $text";
        }

        return $text;
    }

    /**
     * @return string
     *   Returns a plain format string representation of this message.
     */
    public function __toString()
    {
        return $this->format(self::Format_Plain);
    }
}
