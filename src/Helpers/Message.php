<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Throwable;

use function assert;
use function sprintf;

/**
 * Class Message defines a - human-readable - message.
 *
 * Messages may appear in any part of the system and need often be transferred
 * through the system layers and displayed on screen, in log files or in mails.
 *
 * Therefore, we define 1 class that wraps messages of all possible sources and
 * possible places to show.
 *
 * Messages are kind of immutable, though the severity can be changed when
 * copied to another message collection because an error in a sub-call may not
 * be more than a warning for the overall result. Furthermore, as a message is
 * not created via the {@see Container}, the {@see Translator} cannot be
 * injected upon construction, but should be set afterwards.
 * {@see MessageCollection} will normally do so, as all messages are normally
 * part of a {@see MessageCollection}.
 *
 * @noinspection PhpLackOfCohesionInspection
 */
class Message
{
    // Formats in which to return messages.
    /** @var int Format as plain text */
    public const Format_Plain = 0;
    /** @var int Format as HTML. */
    public const Format_Html = 1;
    // PHP7.1: These 2 could become protected.
    /** @var int Format as list-item */
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

    public static function createFromException(Throwable $e): Message
    {
        return new Message($e->getMessage(), Severity::Exception, $e->getCode(), '', '', $e);
    }

    /**
     * @param array $apiMessage
     *   An array with keys 'message', 'code', and 'codetag'.
     * @param int $severity
     *   One of the {@see Severity} constants.
     */
    public static function createFromApiMessage(array $apiMessage, int $severity): Message
    {
        return new Message($apiMessage['message'], $severity, $apiMessage['code'], $apiMessage['codetag']);
    }

    public static function createForFormField(string $message, int $severity, string $field): Message
    {
        return new Message($message, $severity, 0, '', $field);
    }

    /**
     * @param string $message
     * @param int $severity
     * @param int|string $code
     *
     * @return \Siel\Acumulus\Helpers\Message
     */
    public static function create(string $message, int $severity, int|string $code = 0): Message
    {
        return new Message($message, $severity, $code);
    }

    protected ?Translator $translator = null;
    protected string $text;
    protected int $severity;
    protected int|string $code;
    protected string $codeTag;
    protected string $field;
    /** @var \Throwable|null */
    protected ?Throwable $exception;

    /**
     * Message constructor.
     *
     * It is protected as the static create methods should be used to create a
     * specific type of message (exception, api, form, simple text message).
     */
    protected function __construct(
        string $text,
        int $severity,
        int|string $code = 0,
        string $codeTag = '',
        string $field = '',
        ?Throwable $exception = null
    ) {
        $this->text = $text;
        $this->severity = $severity;
        $this->code = $code;
        $this->codeTag = $codeTag;
        $this->field = $field;
        $this->exception = $exception;
    }

    /**
     * The creating party should also set a translator.
     *
     * @param \Siel\Acumulus\Helpers\Translator $translator
     *
     * @return $this
     */
    public function setTranslator(Translator $translator): Message
    {
        $this->translator = $translator;
        return $this;
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
        return $this->translator instanceof Translator ? $this->translator->get($key) : $key;
    }

    /**
     * @return string
     *   A human-readable, thus possibly translated, text.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return int
     *   One of the {@see Severity} constants.
     */
    public function getSeverity(): int
    {
        return $this->severity;
    }

    /**
     * Overrides the severity of the message.
     *
     * @param int $severity
     *   One of the {@see Severity} constants.
     */
    public function setSeverity(int $severity): void
    {
        $this->severity = $severity;
    }

    /**
     * Returns a textual representation of the status.
     */
    protected function getSeverityText(): string
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
                return $this->t('severity_' . $this->getSeverity());
            default:
                assert(false, sprintf($this->t('severity_unknown'), $this->getSeverity()));
        }
    }

    /**
     * @return int|string
     *   A code identifying the message, typically:
     *   - An http response code.
     *   - The exception code.
     *   - The Acumulus API message code, usually a number 4xx, 5xx, or 6xx,
     *     see {@link https://www.siel.nl/acumulus/API/Basic_Response/}.
     *   - A 7xx number used internally to define messages.
     */
    public function getCode(): int|string
    {
        return $this->code;
    }

    /**
     * @return string
     *   A code tag used by the Acumulus API to report errors or warnings,
     *   see {@link https://www.siel.nl/acumulus/API/Basic_Response/}. For
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
     * @return \Throwable|null
     *   The exception used to construct this message, or null if this message
     *   is not a Severity::Exception level message.
     */
    public function getException(): ?Throwable
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
     * - If Format_ListItem is set, a list indication will be added: either an
     *   asterisk ("* ...\n") or the HTML list tag "<li>...</li>".
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
            // @nth: replace spaces with &nbsp; to retain indentation (i.e. all spaces
            //   after a <br> (if we do this after the nl2br) or after a /n.
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
