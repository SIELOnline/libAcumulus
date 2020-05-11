<?php
namespace Siel\Acumulus\Helpers;

use Exception;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

/**
 * Class Message defines a message.
 *
 * Messages may appear in any part of the system and need often be transferred
 * through the system layers and displayed on screen, in log files or in mails.
 *
 * Therefore we define 1 class that wraps messages of all possible sources and
 * possible places to show.
 */
class Message
{
    // Format in which to return messages.
    // 2 basic formats: plain or html, but no severity, and not as a list item.
    const Format_Plain = 0;
    const Format_Html = 1;
    // PHP7.1: These 2 could become protected.
    const Format_ListItem = 2;
    const Format_AddSeverity = 4;
    // Combinations of the above.
    const Format_PlainWithSeverity = self::Format_Plain | self::Format_AddSeverity;
    const Format_HtmlWithSeverity = self::Format_Html | self::Format_AddSeverity;
    const Format_PlainList = self::Format_Plain | self::Format_ListItem;
    const Format_HtmlList = self::Format_Html | self::Format_ListItem;
    const Format_PlainListWithSeverity = self::Format_Plain | self::Format_ListItem | self::Format_AddSeverity;
    const Format_HtmlListWithSeverity = self::Format_Html | self::Format_ListItem | self::Format_AddSeverity;

    /** @var \Siel\Acumulus\Helpers\Translator */
    static protected $translator;

    /** @var int */
    protected $severity;

    /** @var int|string */
    protected $code;

    /** @var string */
    protected $codeTag;

    /** @var string */
    protected $text;

    /** @var \Exception|null */
    protected $exception;

    /**
     * Message constructor.
     *
     * NOTE: This is an "overloaded" method. Parameter naming is based on the
     * case where all parameters are supplied.
     *
     * @param int|Exception $severity
     *   Either:
     *   - One of the Severity constants (except Severity::Exception)
     *   - An \Exception object, in which case other parameters are ignored.
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
     */
    public function __construct($severity, $code = 0, $codeTag = '', $text = '')
    {
        // PHP7: instanceof Throwable.
        if ($severity instanceof Exception) {
            // Only 1 argument: an Exception.
            $this->severity = Severity::Exception;
            $this->code = $severity->getCode();
            $this->codeTag = '';
            $this->text = $severity->getMessage();
            $this->exception = $severity;
        } else {
            $this->severity = $severity;
            $this->exception = null;
            if (is_array($code)) {
                // Only 2 arguments: a severity and an Acumulus API message
                // array.
                $this->code = $code['code'];
                $this->codeTag = $code['codetag'];
                $this->text = $code['message'];
            } else {
                // All arguments passed in.
                $this->code = $code;
                $this->codeTag = $codeTag;
                $this->text = $text;
            }
        }
    }

    /**
     * @param \Siel\Acumulus\Helpers\Translator $translator
     */
    public static function setTranslator(Translator $translator)
    {
        self::$translator = $translator;
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
    protected function t($key)
    {
        return static::$translator instanceof Translator ? static::$translator->get($key) : $key;
    }

    /**
     * @return int
     *   One of the Severity::... constants.
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * Returns a textual representation of the status.
     *
     * @return string
     */
    public function getSeverityText()
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
    public function getCodeTag()
    {
        return $this->codeTag;
    }

    /**
     * @return string
     *   A human readable, thus possibly translated, text.
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @return \Exception|null
     *   The exception used to construct this message, or null if this message
     *   is not an Message::Exception level message.
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * Returns a formatted message text.
     *
     * - In the basis it returns: "code, codeTag: Text".
     * - If Format_AddSeverity is set, "Severity :" will be prepended.
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
    public function format($format)
    {
        $isHtml = ($format & self::Format_Html) !== 0;
        $text = '';

        // Severity:
        if (($format & self::Format_AddSeverity) !== 0) {
            $severity = $this->getSeverityText();
            if ($isHtml) {
                $severity = '<span>' . htmlspecialchars($severity, ENT_NOQUOTES) . '</span>';
            }
            $text .= "$severity: ";
        }

        // Code and code tag:
        $codes = implode(', ', array_filter([$this->getCode(), $this->getCodeTag()]));
        if (!empty($codes)) {
            if ($isHtml) {
                $codes = '<span>' . htmlspecialchars($codes, ENT_NOQUOTES) . '</span>';
            }
            $text .= "$codes: ";
        }

        // Text:
        $messageText = $this->getText();
        if ($isHtml) {
            $messageText = '<span>' .  htmlspecialchars($messageText, ENT_NOQUOTES) . '</span>';
        }
        $text .= $messageText;

        // List item:
        if (($format & self::Format_ListItem) !== 0) {
            $text = $isHtml ? "<li>$text</li>" : "* $text\n";
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
