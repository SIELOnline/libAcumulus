<?php
namespace Siel\Acumulus\Web;

use Exception;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\TranslatorInterface;

/**
 * Class Result wraps an Acumulus web service result into an object.
 *
 * A Result object will contain the:
 * - result status (internal code: 1 of the self::Status_... constants)
 * - exception, if one was thrown
 * - any error messages, local and/or remote
 * - any warnings, local and/or remote
 * - message sent, for logging purposes
 * - (raw) message received, for logging purposes
 * - result array
 */
class Result {
    // Web service configuration related constants.
    // Send status: bits 1, 2 and 3. Can be combined with an Invoice_Sent_...
    // const. Not necessarily a single bit per value, but the order should be by
    // increasing worseness.
    const Status_Success = 0;
    const Status_Warnings = 1;
    const Status_Errors = 2;
    const Status_Exception = 4;

    // Format in which to return messages
    const Format_Array = 1;
    const Format_PlainTextArray = 2;
    const Format_FormattedText = 3;
    const Format_Html = 4;

    // Whether to add the raw request and response.
    const AddReqResp_Never = 1;
    const AddReqResp_Always = 2;
    const AddReqResp_WithOther = 3;

    /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
    protected $translator;

    /**
     * Whether the message was sent.
     *
     * This bool indicates whether curl_exec was called and thus if the service
     * call may have arrived and may have been processed at the server.
     *
     * @var bool
     */
    protected $isSent;

    /**
     * The status as returned by the web service.
     *
     * @see https://www.siel.nl/acumulus/API/Basic_Response/
     *
     * @var int
     */
    protected $status;

    /**
     * A - possibly empty - list of error messages.
     *
     * @var \Exception|null
     */
    protected $exception;

    /**
     * A - possibly empty - list of error messages.
     *
     * @var array[]
     */
    protected $errors;

    /**
     * A - possibly empty - list of warning messages.
     *
     * @var array[]
     */
    protected $warnings;

    /**
     * The raw contents of the request as was sent to the web service.
     *
     * @var string|null
     */
    protected $rawRequest;

    /**
     * The raw contents of the response as was received from the web service.
     *
     * @var string|null
     */
    protected $rawResponse;

    /**
     * The structured response as was received from the web service.
     *
     * @var array
     */
    protected $response;

    /**
     * The key that contains the main response of a service call.
     *
     * Besides the general response structure (status, errors, and warnings),
     * each service call result will contain the result specific for that call.
     * This variable contains the key under which to find that. It should be set
     * by each service call, allowing users of the service to retrieve the main
     * result without the need to know more details then strictly needed of the
     * Acumulus API.
     *
     * @var string
     */
    protected $mainResponseKey;

    /**
     * Indicates if the main response should be a list.
     *
     * @var bool
     */
    protected $isList;

    /**
     * Result constructor.
     *
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->status = null;
        $this->isSent = false;
        $this->exception = null;
        $this->errors = array();
        $this->warnings = array();
        $this->rawRequest = null;
        $this->rawResponse = null;
        $this->response = array();
        $this->mainResponseKey = '';
        $this->isList = false;

        $this->translator = $translator;
        $webTranslations = new Translations();
        $this->translator->add($webTranslations);
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
        return $this->translator->get($key);
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Returns a textual representation of the status.
     *
     * @return string
     */
    public function getStatusText()
    {
        switch ($this->getStatus()) {
            case self::Status_Success:
                return $this->t('message_response_success');
            case self::Status_Errors:
                return $this->t('message_response_errors');
            case self::Status_Warnings:
                return $this->t('message_response_warnings');
            case self::Status_Exception:
                return $this->t('message_response_exception');
            case null:
                return $this->t('message_response_not_set');
            default:
                return sprintf($this->t('message_response_unknown'), $this->getStatus());
        }
    }

    /**
     * @return bool
     */
    public function isSent()
    {
        return $this->isSent;
    }

    /**
     * @param bool $isSent
     *
     * @return $this
     */
    public function setIsSent($isSent)
    {
        $this->isSent = $isSent;
        return $this;
    }

    /**
     * Sets the status code.
     *
     * @param int $status
     *   An internal status code, i.e. a self::Status_... constant.
     *
     * @return $this
     */
    protected function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Raises the status code to the passed $minimalStatus.
     *
     * @param int $minimalStatus
     *   The minimal (internal) status code that the result should have.
     *
     * @return $this
     */
    protected function raiseStatus($minimalStatus)
    {
        if ($this->getStatus() === null || $this->getStatus() < $minimalStatus) {
            $this->setStatus($minimalStatus);
        }
        return $this;
    }

    /**
     * @return \Exception|null
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @param \Exception $exception
     *
     * @return $this
     */
    public function setException(Exception $exception)
    {
        $this->exception = $exception;
        return $this->raiseStatus(self::Status_Exception);
    }

    /**
     * Returns a - possibly empty - list of (formatted) error messages.
     *
     * @param int $format
     *
     * @return array[]|string[]|string
     */
    public function getErrors($format = self::Format_Array)
    {
        return $this->formatMessages($this->errors, $format, 'message_error');
    }

    /**
     * Adds multiple errors to the list of errors.
     *
     * A single error may be passed in as a 1-dimensional array.
     *
     * @param array[] $errors
     *
     * @return $this
     */
    protected function addErrors(array $errors)
    {
        // If there was exactly 1 error, it wasn't put in an array of errors.
        if (array_key_exists('code', $errors)) {
            $errors = array($errors);
        }
        foreach ($errors as $error) {
            $this->addError($error);
        }
        return $this;
    }

    /**
     * Adds an error to the list of errors.
     *
     * @param int|array $code
     *   Error/warning code number. Usually of type 4xx, 5xx, 6xx or 7xx
     *  (internal). It may also be an already completed error array.
     * @param string $codeTag
     *   Special code tag. Use this as a reference when communicating with
     *   Acumulus technical support.
     * @param string $message
     *   A message describing the warning or error.
     *
     * @return $this
     */
    public function addError($code, $codeTag = '', $message = '')
    {
        if (is_array($code)) {
            $message = $code['message'];
            $codeTag = $code['codetag'];
            $code = $code['code'];
        }
        if (empty($codeTag)) {
            $this->errors[] = array(
                'code' => '',
                'codetag' => $codeTag,
                'message' => $message
            );
        } elseif (!array_key_exists($codeTag, $this->errors)) {
            $this->errors[$codeTag] = array(
                'code' => $code,
                'codetag' => $codeTag,
                'message' => $message
            );
        }
        return $this->raiseStatus(self::Status_Errors);
    }

    /**
     * Returns a - possibly empty - list of warning messages.
     *
     * @param int $format
     *
     * @return array[]|string[]|string
     */
    public function getWarnings($format = self::Format_Array)
    {
        return $this->formatMessages($this->warnings, $format, 'message_warning');
    }

    /**
     * Adds multiple warnings to the list of warnings.
     *
     * A single warning may be passed in as a 1-dimensional array.
     *
     * @param array[] $warnings
     *
     * @return $this
     */
    protected function addWarnings(array $warnings)
    {
        // If there was exactly 1 warning, it wasn't put in an array of warnings.
        if (array_key_exists('code', $warnings)) {
            $warnings = array($warnings);
        }
        foreach ($warnings as $warning) {
            $this->addWarning($warning);
        }
        return $this;
    }

    /**
     * Adds a warning to the list of warnings.
     *
     * @param int $code
     *   Error/warning code number. Usually of type 4xx, 5xx, 6xx or 7xx
     *   (internal). It may also be an already completed warning array.
     * @param string $codeTag
     *   Special code tag. Use this as a reference when communicating with
     *   Acumulus technical support.
     * @param string $message
     *   A message describing the warning or error.
     *
     * @return $this
     */
    public function addWarning($code, $codeTag = '', $message = '')
    {
        if (is_array($code)) {
            $message = $code['message'];
            $codeTag = $code['codetag'];
            $code = $code['code'];
        }
        if (empty($codeTag)) {
            $this->warnings[] = array(
                'code' => $code,
                'codetag' => $codeTag,
                'message' => $message
            );
        } elseif (!array_key_exists($codeTag, $this->warnings)) {
            $this->warnings[$codeTag] = array(
                'code' => $code,
                'codetag' => $codeTag,
                'message' => $message
            );
        }
        return $this->raiseStatus(self::Status_Warnings);
    }

    /**
     * Merges sets of exception, error and warning messages of 2 results.
     *
     * This allows to inform the user about errors and warnings that occurred
     * during additional API calls, e.g. querying VAT rates or deleteing old
     * entries.
     *
     * @param \Siel\Acumulus\Web\Result $other
     *   The other result to add the messages from.
     * @param bool $errorsAsWarnings
     *   Whether errors should be merged as errors or as mere warnings because
     *   the main result is not really influenced by these errors.
     *
     * @return $this
     */
    public function mergeMessages(Result $other, $errorsAsWarnings = false)
    {
        if ($this->getException() === null && $other->getException() !== null) {
            $this->setException($other->getException());
        }
        if ($errorsAsWarnings) {
            $this->addWarnings($other->getErrors());
        } else {
            $this->addErrors($other->getErrors());
        }
        return $this->addWarnings($other->getWarnings());
    }

    /**
     * Returns whether the result contains a warning, error or exception.
     *
     * @return bool
     *   True if the result contains at least 1 warning, error or exception,
     *   false otherwise.
     */
    public function hasMessages()
    {
        return !empty($this->warnings) || !empty($this->errors) || !empty($this->exception);
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
        return $this->getStatus() >= self::Status_Errors;
    }

    /**
     * Returns whether the result contains a given code.
     *
     * @param int $code
     *
     * @return bool
     *   True if the result contains a given code, false otherwise.
     */
    public function hasCode($code)
    {
        if ($this->getException() !== null && $this->getException()->getCode() === $code) {
            return true;
        }

        foreach ($this->getErrors() as $message) {
            if ($message['code'] == $code) {
                return true;
            }
        }

        foreach ($this->getWarnings() as $message) {
            if ($message['code'] == $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the result contains a given codeTag.
     *
     * @param string $codeTag
     *
     * @return bool
     *   True if the result contains a given codeTag, false otherwise.
     */
    public function hasCodeTag($codeTag)
    {
        return array_key_exists($codeTag, $this->errors) || array_key_exists($codeTag, $this->warnings);
    }

    /**
     * If the result contains any errors or warnings, a list of verbose messages
     * is returned.
     *
     * @param int $format
     *   The format in which to return the messages:
     *   - Result::Format_PlainTextArray: an array of strings
     *   - Result::Format_FormattedText: a plain text string with all messages
     *     on its own line indented by a '*'.
     *   - Result::Format_Html: a html string with all messages in an
     *     unordered HTML list
     *
     * @return string|string[]
     *   An string or array with textual messages that can be used to inform the
     *   user.
     */
    public function getMessages($format = self::Format_Array)
    {
        $messages = array();

        // Collect the messages.
        if (($e = $this->getException()) !== null) {
            $message = $e->getCode() . ': ';
            $message .= $e->getMessage();
            $messages[] = $this->t('message_exception') . ' ' . $message;
        }

        $messages = array_merge($messages, $this->getErrors(self::Format_PlainTextArray));
        $messages = array_merge($messages, $this->getWarnings(self::Format_PlainTextArray));

        return $this->formatMessages($messages, $format);
    }

    /**
     * Formats a set of messages.
     *
     * @param string[] $messages
     * @param int $format
     *
     * @param string $type
     *
     * @return string|\string[]
     */
    protected function formatMessages($messages, $format, $type = '')
    {

        if ($format === self::Format_Array) {
            $result = $messages;
        } else {
            $result = array();
            foreach ($messages as $message) {
                if (is_array($message)) {
                    $messagePart = "{$message['code']}: ";
                    $messagePart .= $this->t($message['message']);
                    if ($message['codetag']) {
                        $messagePart .= " ({$message['codetag']})";
                    }
                    $message = $this->t($type) . ' ' . $messagePart;
                }
                if ($format === self::Format_FormattedText) {
                    $message = "* $message\n\n";
                } elseif ($format === self::Format_Html) {
                    $message = "<li>" . nl2br(htmlspecialchars($message, ENT_NOQUOTES)) . "</li>\n";
                }
                $result[] = $message;
            }
            if ($format === self::Format_FormattedText) {
                $result = implode('', $result);
            } elseif ($format === self::Format_Html) {
                $result = "<ul>\n" . implode('', $result) . "</ul>\n";
            }
        }
        return $result;
    }

    /**
     * Returns the structured non-common part of the received response.
     *
     * @return array
     *   The structured non-common part of the response as received from the
     *   Acumulus web service, i.e. the status, errors and warnings keys are
     *   removed. In case of errors, this array may be empty.
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Sets the (structured) response as was received from the web service.
     *
     * @param array $response
     *   The structured response (json or xml string converted to an array).
     *
     * @return $this
     *
     * @see https://www.siel.nl/acumulus/API/Basic_Response/ For the
     *   common part of a response.
     */
    public function setResponse(array $response)
    {
        // Move the common parts into their respective properties.
        $this->raiseStatus(isset($response['status']) ? $this->ApiStatus2InternalStatus($response['status']) : self::Status_Errors);
        unset($response['status']);

        if (!empty($response['errors']['error'])) {
            $this->addErrors($response['errors']['error']);
        }
        unset($response['errors']);

        if (!empty($response['warnings']['warning'])) {
            $this->addWarnings($response['warnings']['warning']);
        }
        unset($response['warnings']);

        $response = $this->simplifyResponse($response);
        $this->response = $response;

        return $this;
    }

    /**
     * Returns the corresponding internal status.
     *
     * @param $status
     *   The status as returned by the API.
     *
     * @return int
     *   The corresponding internal status.
     */
    protected function ApiStatus2InternalStatus($status)
    {
        switch ($status) {
            case Api::Success:
                return self::Status_Success;
            case Api::Errors:
                return self::Status_Errors;
            case Api::Warnings:
                return self::Status_Warnings;
            case Api::Exception:
            default:
                return self::Status_Exception;
        }
    }

    /**
     * @param string $mainResponseKey
     * @param bool $isList
     *
     * @return $this
     */
    public function setMainResponseKey($mainResponseKey, $isList = false)
    {
        $this->mainResponseKey = $mainResponseKey;
        $this->isList = $isList;
        if (!empty($this->response)) {
            $this->response = $this->simplifyResponse($this->response);
        }
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRawRequest()
    {
        return $this->rawRequest;
    }

    /**
     * @param string|null $rawRequest
     *
     * @return $this
     */
    public function setRawRequest($rawRequest)
    {
        $this->rawRequest = preg_replace('|<password>.*</password>|', '<password>REMOVED FOR SECURITY</password>', $rawRequest);
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    /**
     * @param string|null $rawResponse
     *
     * @return $this
     */
    public function setRawResponse($rawResponse)
    {
        $this->rawResponse = $rawResponse;
        return $this;
    }

    /**
     * Returns a string with messages for support, ie the request and response.
     *
     * In html format it will be formatted in a details tag so that it is closed
     * by default.
     *
     * @param $format
     *
     * @return string
     *
     */
    public function getRawRequestResponse($format)
    {
        $result = '';
        if ($this->getRawRequest() !== null || $this->getRawResponse() !== null) {
            $messages = array();
            if ($this->getRawRequest() !== null) {
                $messages[] = $this->t('message_sent') . ":\n" . $this->getRawRequest();
            }
            if ($this->getRawResponse() !== null) {
                $messages[] = $this->t('message_received') . ":\n" . $this->getRawResponse();
            }
            $result .= $this->formatMessages($messages, $format);
        }
        return $result;
    }

    /**
     * Returns a string representation of the result object for logging purposes.
     *
     * @return string
     *   A string representation of the result object for logging purposes.
     */
    public function toLogString()
    {
        $logParts = array();
        if ($this->getStatus() !== null) {
            $logParts[] = 'status=' . $this->getStatus();
        }
        if ($this->getRawRequest() !== null) {
            $logParts[] = 'request=' . $this->getRawRequest();
        }
        if ($this->getRawResponse() !== null) {
            $logParts[] = 'response=' . $this->getRawResponse();
        }
        if ($this->getException() !== null) {
            $logParts[] = $this->getException()->__toString();
        }
        return implode('; ', $logParts);
    }

    /**
     * Simplify the response by removing the main key.
     *
     * @param array $response
     *
     * @return array
     */
    protected function simplifyResponse(array $response)
    {
        // Simplify response by removing main key (which should be the only
        // remaining one).
        if (!empty($this->mainResponseKey) && isset($response[$this->mainResponseKey])) {
            $response = $response[$this->mainResponseKey];

            if ($this->isList) {
                // Remove further indirection, i.e. get value of "singular",
                // which will be the first (and only) key.
                $response = reset($response);
                // If there was only 1 list result, it wasn't put in an array.
                if (!is_array(reset($response))) {
                    $response = array($response);
                }
            }
        }
        return $response;
    }
}
