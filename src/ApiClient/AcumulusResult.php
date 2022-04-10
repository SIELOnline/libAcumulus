<?php
namespace Siel\Acumulus\ApiClient;

use LogicException;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Helpers\Util;

/**
 * Class AcumulusResult wraps an Acumulus web service result into an object.
 *
 * An AcumulusResult object contains
 * Most important:
 * - The received response, without the basic response part, converted to an
 *   array.
 * But also a lot of other info:
 * - Result status (internal code: one of the Severity::... constants).
 * - Exception, if one was thrown.
 * - Any error messages, local and/or remote.
 * - Any warnings, local and/or remote.
 * - Any notices, local.
 * - HttpRequest and HttpResponse objects, for logging purposes.
 *
 * @todo: complete error handling, especially http codes and error values in response
 */
class AcumulusResult extends MessageCollection
{
    protected /*Util*/ $util;
    protected /*Log*/ $log;
    protected /*?AcumulusRequest*/ $acumulusRequest = null;
    protected /*?HttpResponse*/ $httpResponse = null;
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

    public function __construct(
        AcumulusRequest $acumulusRequest,
        ?HttpResponse $httpResponse,
        Util $util,
        Translator $translator,
        Log $log
    ) {
        parent::__construct($translator);
        $this->util = $util;
        $this->log = $log;
        $this->acumulusRequest = $acumulusRequest;
        if ($httpResponse !== null) {
            $this->setHttpResponse($httpResponse);
        }
    }

    /**
     * @return int
     *   The status is the result of taking the worst of:
     *   - The response API status.
     *   - The severity of the message collection.
     *   - While ignoring messages of Severity::Log.
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

    public function getAcumulusRequest(): ?AcumulusRequest
    {
        return $this->acumulusRequest;
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
     *
     * @throws \LogicException
     * @throws \RuntimeException
     *   Either we received:
     *   - An HTML response (probably an error page).
     *   - An invalid json message.
     *   - An invalid xml message.
     */
    protected function setHttpResponse(HttpResponse $httpResponse): void
    {
        if ($this->httpResponse !== null) {
            throw new LogicException('AcumulusResult::setHttpResponse() may only be called once.');
        }
        if ($this->getAcumulusRequest() === null) {
            throw new LogicException('AcumulusResult::setHttpResponse() may only be called after AcumulusResult::setAcumulusRequest()');
        } elseif ($this->getAcumulusRequest()->getSubmit() === null) {
            throw new LogicException('AcumulusResult::setHttpResponse() may only be called after AcumulusResult::setAcumulusRequest() with its submit property set');
        }

        $this->httpResponse = $httpResponse;
        // @todo: start using http codes (403, 429, 500, ...)

        $body = $this->httpResponse->getBody();
        if (empty($body)) {
            // Curl did return a non-empty response, otherwise we would not be
            // here. So, apparently that only contained headers.
            // @todo: Is this a non 200 response or can we consider this as a critical error?
            $this->createAndAddMessage('Empty response body', Severity::Error, 701);
        } elseif ($this->util->isHtmlResponse($body)) {
            // When the API is gone we might receive an HTML error message page.
            $this->util->raiseHtmlReceivedError($body);
        } else {
            // Decode the response as either json or xml.
            $response = [];
            $outputFormat = $this->getAcumulusRequest()->getSubmit()['format'] ?? 'xml';
            if ($outputFormat === 'json') {
                $response = json_decode($body, true);
            }
            // Even if we pass json as <format> we might receive an XML response
            // in case the request was rejected before or during parsing. So, if
            // $response = null, we also try to decode $body as XML.
            if ($outputFormat === 'xml' || !is_array($response)) {
                try {
                    $response = $this->util->convertXmlToArray($body);
                } catch (RuntimeException $e) {
                    // Not an XML response. Treat it as a json error if we were
                    // expecting a json response.
                    if ($outputFormat === 'json') {
                        $this->util->raiseJsonError();
                    }
                    // Otherwise, treat it as the XML exception that was raised.
                    throw $e;
                }
            }
            $this->setResponse($response);
        }
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
    public function getMainResponse(): array
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
    protected function setResponse(array $response): self
    {
        // Move the common parts into their respective properties.
        if (isset($response['status'])) {
            $this->setApiStatus($response['status']);
            if ($response['status'] === Api::Status_Exception) {
                $this->addException(new RuntimeException($this->getStatusText()));
            }
            unset($response['status']);
        } else {
            $this->addException(new RuntimeException('Status not set in response'));
        }

        if (!empty($response['errors']['error'])) {
            $this->addApiMessages($response['errors']['error'], Severity::Error);
        }
        unset($response['errors']);

        if (!empty($response['warnings']['warning'])) {
            $this->addApiMessages($response['warnings']['warning'], Severity::Warning);
        }
        unset($response['warnings']);

        $this->response = $response;

        return $this;
    }

    /**
     * @param string $mainResponseKey
     * @param bool $isList
     *
     * @return $this
     */
    public function setMainResponseKey(string $mainResponseKey, bool $isList = false): AcumulusResult
    {
        $this->mainResponseKey = $mainResponseKey;
        $this->isList = $isList;
        $this->response = $this->simplifyResponse($this->response);
        return $this;
    }

    /**
     * Simplify the response by removing the main key.
     *
     * @param array $response
     *
     * @return array
     *
     * @noinspection PhpSeparateElseIfInspection
     */
    protected function simplifyResponse(array $response): array
    {
        // Simplify response by removing main key (which should be the only
        // remaining one).
        if (!empty($this->mainResponseKey)) {
            if (isset($response[$this->mainResponseKey])) {
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
            } else {
                // Not set: probably an error occurred. This object offers ways
                // to discover so. Therefore, we return an empty list if it
                // should have been a list.
                if ($this->isList) {
                    $response = [];
                }
            }
        }

        return $response;
    }

    /**
     * @param array $apiMessages
     *   An array with keys 'message', 'code', and 'codetag'.
     * @param int $severity
     *   One of the Severity::... constants.
     *
     * @return $this
     */
    public function addApiMessages(array $apiMessages, int $severity): MessageCollection
    {
        if (count($apiMessages) === 3
            && isset($apiMessages['code'])
            && isset($apiMessages['codetag'])
            && isset($apiMessages['message'])
        ) {
            // 1 Acumulus API message array.
            $apiMessages = [$apiMessages];
        }
        foreach ($apiMessages as $apiMessage) {
            $this->addMessage(Message::createFromApiMessage($apiMessage, $severity));
        }
        return $this;
    }

    /**
     * Returns the masked raw request, response, and exception. if one occurred.
     *
     * @param bool $log
     *   if true, the non-empty messages are logged with the given $logLevel.
     * @param int $logLevel
     *    The log level to log the messages with (if $log = true).
     *
     * @return string[]
     *   An array of non-empty messages keyed by the keys:
     *   - 'Request': The uri and a string representation of the
     *     submit-structure.
     *   - 'Response': The http response status code and the response body.
     *     Non-present if no http response was received.
     *   - 'Exception': The exception message. Non-present if no exception was
     *     thrown.
     */
    public function toLogMessages(bool $log = false, int $logLevel = Severity::Log): array
    {
        $messages = array_filter([
            'Request' => $this->getMaskedRequest(),
            'Response' => $this->getMaskedResponse(),
            'Exception' => $this->formatMessages(Message::Format_Plain, Severity::Exception),
        ]);

        $result = [];
        foreach ($messages as $what => $message) {
            // formatMessages() will always return an array: treat all entries
            // as an array (of probably just 1 entry).
            $message = (array) $message;
            foreach ($message as $message1) {
                $message1 = "$what: $message1";
                $result[$what] = $message1;
                if ($log) {
                    $this->log->log($logLevel, $message1);
                }
            }
        }
        return $result;
    }

    /**
     * Returns the submit-structure as a string, with passwords masked.
     *
     * - We use var_export() that returns parsable text, so we may use it, e.g,
     *   to create test input.
     * - We mask all values that have 'password' in their key, so it can be used
     *   for logging purposes.
     */
    protected function getMaskedRequest(): string
    {
        $acumulusRequest = $this->getAcumulusRequest();
        $submit = $acumulusRequest->getSubmit();
        $submit = $submit !== null
            ? var_export($this->util->maskArray($submit), true)
            : '';
        return sprintf("%s\n%s", $acumulusRequest->getUri(), $submit);
    }

    /**
     * Returns the response from the Acumulus API, with passwords masked.
     *
     * - We mask all values of tags that end with 'password'.
     * - The plugins default to json output format, but we also accept a string
     *   in XML format.
     * - By masking any password, the result can be used for logging purposes.
     */
    protected function getMaskedResponse(): string
    {
        if ($this->getHttpResponse() !== null) {
            $code = $this->getHttpResponse()->getHttpCode();
            $body = $this->getHttpResponse()->getBody();
            $body = $this->util->maskJson($this->util->maskXml($body));
            $response = sprintf('%d - %s', $code, $body);
        }
        return $response ?? '';
    }
}
