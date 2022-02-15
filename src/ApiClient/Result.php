<?php
namespace Siel\Acumulus\ApiClient;

use DOMDocument;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

/**
 * Class Result wraps an Acumulus web service result into an object.
 *
 * A Result object will contain
 * Most important:
 * - The received result, without any warnings or errors, converted to an array.
 *
 * But also a lot of other info:
 * - Result status (internal code: one of the Severity::... constants).
 * - Exception, if one was thrown.
 * - Any error messages, local and/or remote.
 * - Any warnings, local and/or remote.
 * - Any notices, local.
 * - Http request and response objects, for logging purposes.
 */
class Result extends MessageCollection
{
    // Code tags for messages containing the raw (but masked) request and
    // response.
    public const CodeTagRawRequest = 'Request';
    public const CodeTagRawResponse = 'Response';

    /**
     * @var \Siel\Acumulus\ApiClient\AcumulusRequest|null
     *   The Acumulus request.
     */
    protected /*?AcumulusRequest*/ $acumulusRequest = null;

    /**
     * @var \Siel\Acumulus\ApiClient\HttpResponse|null
     *   The received HTTP response.
     */
    protected /*?HttpResponse*/ $httpResponse = null;

    /**
     * @var int|null
     *   The received api status or null if not yet sent.
     */
    protected /*?int*/ $apiStatus = null;

    /**
     * @var array
     *   The structured response as was received from the web service.
     */
    protected /*array*/ $response = [];

    /**
     * @var string
     *   The key that contains the main response of a service call.
     *
     *   Along the general response structure (status, errors, and warnings),
     *   each service call result will contain the result specific for that
     *   call. This variable contains the key under which to find that. It
     *   should be set by each service call, allowing users of the service to
     *   retrieve the main result without the need to know more details than
     *   strictly needed of the Acumulus API.
     */
    protected /*string*/ $mainResponseKey = '';

    /**
     * @var bool
     *   Indicates if the main response should be a list.
     */
    protected /*bool*/ $isList = false;

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
     * @return int
     *   The status is the result of taking the worst of:
     *   - the response.
     *   - the severity of the message collection.
     *   - ignoring messages of Severity::Log
     */
    public function getStatus(): int
    {
        $status = $this->ApiStatus2Severity($this->apiStatus);
        $severity = $this->getSeverity();
        if ($severity > $status && $severity !== Severity::Log) {
            $status = $severity;
        }
        return $status;
    }

    /**
     * Returns a textual translated representation of the status.
     *
     * @return string
     */
    public function getStatusText(): string
    {
        switch ($this->getStatus()) {
            case Severity::Unknown:
                return $this->t('request_not_yet_sent');
            case Severity::Success:
                return $this->t('message_response_success');
            case Severity::Info:
                return $this->t('message_response_info');
            case Severity::Notice:
                return $this->t('message_response_notice');
            case Severity::Warning:
                return $this->t('message_response_warning');
            case Severity::Error:
                return $this->t('message_response_error');
            case Severity::Exception:
                return $this->t('message_response_exception');
            default:
                return sprintf($this->t('severity_unknown'), $this->getSeverity());
        }
    }

    /**
     * @param int $apiStatus
     *   The status as returned by the API. 1 of the Api::Status_... constants.
     */
    protected function setApiStatus(int $apiStatus): void
    {
        $this->apiStatus = $apiStatus;
    }

    /**
     * Returns the corresponding internal status.
     *
     * @param int|null $apiStatus
     *   The status as returned by the API.
     *
     * @return int
     *   The corresponding internal status.
     */
    protected function ApiStatus2Severity(?int $apiStatus): int
    {
        // O and null are not distinguished by a switch.
        if ($apiStatus === null) {
            return Severity::Unknown;
        }
        switch ($apiStatus) {
            case Api::Status_Success:
                return Severity::Success;
            case Api::Status_Errors:
                return Severity::Error;
            case Api::Status_Warnings:
                return Severity::Warning;
            case Api::Status_Exception:
                return Severity::Exception;
            default:
                throw new RuntimeException(sprintf('Unknown api status %d', $apiStatus));
        }
    }

    /**
     * @return \Siel\Acumulus\ApiClient\AcumulusRequest|null
     */
    public function getAcumulusRequest(): ?AcumulusRequest
    {
        return $this->acumulusRequest;
    }

    /**
     * @param \Siel\Acumulus\ApiClient\AcumulusRequest|null $acumulusRequest
     */
    public function setAcumulusRequest(?AcumulusRequest $acumulusRequest): void
    {
        $this->acumulusRequest = $acumulusRequest;
    }

    /**
     * Returns the structured main response part of the received response.
     *
     * @return array
     *   The main response part of the response as received from the Acumulus
     *   web service converted to a(n array of) keyed array(s). The status,
     *   errors and warnings are removed. In case of errors, this array may be
     *   empty.
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * Sets the (structured) response as was received from the web service. See
     * {@link https://www.siel.nl/acumulus/API/Basic_Response/} for the common
     * parts of a response.
     *
     * @param array $response
     *   The structured response (json or xml string converted to an array).
     *
     * @return $this
     */
    public function setResponse(array $response): self
    {
        // Move the common parts into their respective properties.
        if (isset($response['status'])) {
            $this->setApiStatus($response['status']);
            unset($response['status']);
        } else {
            $this->addMessage(new RuntimeException('Status not set in response'));
        }

        if (!empty($response['errors']['error'])) {
            $this->addMessages($response['errors']['error'], Severity::Error);
        }
        unset($response['errors']);

        if (!empty($response['warnings']['warning'])) {
            $this->addMessages($response['warnings']['warning'], Severity::Warning);
        }
        unset($response['warnings']);

        $response = $this->simplifyResponse($response);
        $this->response = $response;

        return $this;
    }

    /**
     * @param string $mainResponseKey
     * @param bool $isList
     *
     * @return $this
     */
    public function setMainResponseKey(string $mainResponseKey, bool $isList = false): Result
    {
        $this->mainResponseKey = $mainResponseKey;
        $this->isList = $isList;
        if (!empty($this->response)) {
            $this->response = $this->simplifyResponse($this->response);
        }
        return $this;
    }

    /**
     * Simplify the response by removing the main key.
     *
     * @param array $response
     *
     * @return array
     */
    protected function simplifyResponse(array $response): array
    {
        // Simplify response by removing main key (which should be the only
        // remaining one).
        if (!empty($this->mainResponseKey) && isset($response[$this->mainResponseKey])) {
            $response = $response[$this->mainResponseKey];

            if ($this->isList) {
                // Check for an empty list result.
                if (!empty($response)) {
                    // Not empty: remove further indirection, i.e. get value of
                    // "singular", which will be the first (and only) key.
                    $response = reset($response);
                    // If there was only 1 list result, it wasn't put in an array.
                    if (!is_array(reset($response))) {
                        $response = [$response];
                    }
                }
            }
        }
        return $response;
    }

    public function getHttpResponse(): ?HttpResponse
    {
        return $this->httpResponse;
    }

    /**
     * Sets the http response.
     *
     * Information from the http response is extracted and placed in the other
     * properties. After doing so, this property remains for logging purposes.
     *
     * @param \Siel\Acumulus\ApiClient\HttpResponse $httpResponse
     */
    public function setHttpResponse(HttpResponse $httpResponse): void
    {
        $this->httpResponse = $httpResponse;
        // @todo: start using http codes (404, 429, 500, ...)

        $body = $this->httpResponse->getBody();
        if (empty($body)) {
            // Curl did return a non-empty response, otherwise we would not be
            // here. So, apparently that only contained headers.
            // @todo: Is this a non 200 response or can we consider this as a critical error?
            $this->addMessage('Empty response body', Severity::Error, '', 701);
        } elseif ($this->isHtmlResponse($body)) {
            // When the API is gone we might receive an HTML error message page.
            $this->raiseHtmlReceivedError($body);
        } else {
            // Decode the response as either json or xml.
            $response = [];
            $outputFormat = $this->getAcumulusRequest()->getSubmitMessage()['format'] ?? 'xml';
            if ($outputFormat === 'json') {
                $response = json_decode($body, true);
            }
            // Even if we pass json as <format> we might receive an XML response
            // in case the request was rejected before or during parsing. So, if
            // $response = null, we also try to decode $body as XML.
            if ($outputFormat === 'xml' || !is_array($response)) {
                try {
                    $response = $this->convertXmlToArray($body);
                } catch (RuntimeException $e) {
                    // Not an XML response. Treat it as a json error if we were
                    // expecting a json response.
                    if ($outputFormat === 'json') {
                        $this->raiseJsonError();
                    }
                    // Otherwise, treat it as the XML exception that was raised.
                    throw $e;
                }
            }
            $this->setResponse($response);
        }
    }

    /**
     * @param string $response
     *
     * @return bool
     *   True if the response is HTML, false otherwise.
     */
    protected function isHtmlResponse(string $response): bool
    {
        return strtolower(substr($response, 0, strlen('<!doctype html'))) === '<!doctype html'
               || strtolower(substr($response, 0, strlen('<html'))) === '<html'
               || strtolower(substr($response, 0, strlen('<body'))) === '<body';
    }

    /**
     * Returns an error message containing the received HTML.
     *
     * @param string $response
     *   String containing an HTML document.
     *
     * @trows \RuntimeException
     *   Always
     */
    protected function raiseHtmlReceivedError(string $response)
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->loadHTML($response);
        $body = $doc->getElementsByTagName('body');
        if ($body->length > 0) {
            $body = $body->item(0)->textContent;
        } else {
            $body = '';
        }
        throw new RuntimeException("HTML response received: $body", 702);
    }

    /**
     * Converts an XML string to an array.
     *
     * @param string $xml
     *   A string containing XML.
     *
     * @return array
     *  An array representation of the XML string.
     *
     * @throws \RuntimeException
     */
    protected function convertXmlToArray(string $xml): array
    {
        // Convert the response to an array via a 3-way conversion:
        // - create a simplexml object
        // - convert that to json
        // - convert json to array
        libxml_use_internal_errors(true);
        if (!($result = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA))) {
            $this->raiseLibxmlError();
        }

        if (!($result = json_encode($result))) {
            $this->raiseJsonError();
        }
        if (($result = json_decode($result, true)) === null) {
            $this->raiseJsonError();
        }

        return $result;
    }

    /**
     * Throws an exception with all libxml error messages as message.
     *
     * @throws \RuntimeException
     *   Always.
     */
    protected function raiseLibxmlError()
    {
        $errors = libxml_get_errors();
        $messages = [];
        $code = 704;
        foreach ($errors as $error) {
            // Overwrite our own code with the 1st code we get from libxml.
            if ($code === 704) {
                $code = $error->code;
            }
            $messages[] = sprintf('Line %d, column: %d: %s %d - %s', $error->line, $error->column, $error->level === LIBXML_ERR_WARNING ? 'warning' : 'error', $error->code, trim($error->message));
        }
        throw new RuntimeException(implode("\n", $messages), $code);
    }

    /**
     * Throws an exception with an error message based on the last json error.
     *
     * @throws \RuntimeException
     *   Always.
     */
    protected function raiseJsonError()
    {
        $code = json_last_error();
        switch ($code) {
            case JSON_ERROR_NONE:
                $message = 'No error';
                break;
            case JSON_ERROR_DEPTH:
                $message = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $message = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $message = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $message = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $code = 705;
                $message = 'Unknown error';
                break;
        }
        $message = sprintf('json (%s): %d - %s', phpversion('json'), $code, $message);
        throw new RuntimeException($message, $code);
    }
}
