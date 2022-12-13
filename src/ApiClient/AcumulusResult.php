<?php

declare(strict_types=1);

namespace Siel\Acumulus\ApiClient;

use JsonException;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Helpers\Util;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;

/**
 * Class AcumulusResult processes and wraps an Acumulus web service result.
 *
 * An AcumulusResult object contains
 * Most important:
 * - The received response, without the basic response part, converted to an
 *   array.
 * But also a lot of other info:
 * - Result status (internal code: one of the
 *   {@see \Siel\Acumulus\Helpers\Severity}::... constants).
 * - Any error messages, local and/or remote.
 * - Any warnings, local and/or remote.
 * - Any notices, local.
 * - {@see HttpRequest} and {@see HttpResponse} objects, for logging purposes.
 *
 * Error handling
 * --------------
 * The basic strategy is to distinguish between:
 * - Errors at the protocol/communication level. These will be thrown as
 *   {@see AcumulusException}s or {@see AcumulusResponseException}s.
 * - Application (domain) level errors. Think of things like input validation
 *   errors, or object does no(t) (longer) exist. These will be set as error
 *   messages in this AcumulusResult object. No exception will be thrown,
 *   calling code should thus check for errors and act accordingly, e.g. showing
 *   form error messages to the user, deleting no longer valid concept or entry
 *   id's, or send a mail to inform the user that errors occurred.
 * - Success (optionally with warnings). Any warnings will be set as warning
 *   messages in this AcumulusResult object. The calling code should happily
 *   process the response.
 *
 * Also see {@link https://www.siel.nl/acumulus/API/Basic_Response/}.
 *
 * To distinguish the above situations we look at the following conditions (each
 * condition assumes the negation of all former conditions):
 * 1. No response was obtained: httpResponse = null: protocol level error.
 * 2. status code indicates a protocol level error.
 * 3. status code indicates either a protocol or application level error.
 * 4. status code indicates an application level error.
 * 5. status code indicates success.
 *
 * Ad 1.
 *
 * Executing the request resulted in an exception on our side.
 * We will not handle this exception at the ApiClient level, but will catch it,
 * to log and rethrow it. No AcumulusResult is constructed, so this situation is
 * not handled here, but in {@see Acumulus::callApiFunction()}
 *
 * Most probably, other layers will also not handle these exceptions, so the
 * user request will fail completely. If a request can be handled in a
 * reasonable way, without the result of a specific API call, likely an
 * additional information retrieving call, higher layers may catch and dispose
 * the error and continue their work.
 *
 * Ad 2.
 *
 * The HTTP request was executed but something went wrong on the server side.
 * The status code is not one of {200, 400, 403, 404}:
 * - Response might be from another part of the server, e.g. the load balancer,
 *   or web server daemon. In this case, the body is probably an HTML error
 *   page, thus having a 'Content-type: text/html[; ...]' header.
 * - The response might also be from the API server in which case a JSON (or
 *   XML) formatted error message is expected in the body (and an accompanying
 *   Content-Type header).
 *
 * We will throw an exception from code called during the construction of this
 * class, so no AcumulusResult object will be returned.
 *
 * Ad 3.
 *
 * The status code is 404 (or 403). For now the only code that indicates a
 * protocol or domain level error.
 * - A 404 indicating a protocol level error indicates an incorrect uri
 *   ("impossible" with tested code) and will have an HTML body.
 * - A 404 with a properly formatted Acumulus API response indicates that a
 *   requested object was not found. The basic response will contain:
 *     - 'status' = 1 (errors).
 *     - Non-empty 'errors' with (at least) one 'error' with its 'code' being
 *      something like '404 {Not Found}' (though it may be translated?).
 * - It may contain additional properties that specify which of the values that
 *   were sent caused the error (the "id-field" that contains an id that was not
 *   found).
 *
 * Ad 4.
 *
 * Status code 400 is used for all domain level errors.
 * - A properly formatted Acumulus API response will be found in the body, whose
 *   basic response will contain:
 *     - 'status' = 1 (errors).
 *     - Non-empty 'errors' with at least one 'error' with its 'code' being
 *      something like '400 {Bad Request}' (may be translated?).
 * - It may contain additional properties that specify which of the values that
 *   were sent caused the error (validation error, not found/existing error).
 *
 * Ad 5.
 *
 * The status code will be 200. The request was executed successfully, but may
 * contain warnings. The (basic) response is expected to contain:
 * - 'status' to be 0 (success) or 2 (warnings).
 * - if 'status' = 2, 'warnings' is expected to be non-empty
 * - 'errors' is expected to be empty.
 * - Other properties (outside the basic response) will contain the actual API
 *   response to the request, accessible by calling code via the method
 *   {@see getMainAcumulusResponse()}.
 */
class AcumulusResult extends MessageCollection
{
    protected Util $util;
    protected Log $log;
    protected AcumulusRequest $acumulusRequest;
    protected HttpResponse $httpResponse;
    protected ?int $apiStatus = null;

    /**
     * @var array
     *   The full structured response as was received from the web service.
     */
    protected array $fullAcumulusResponse = [];

    /**
     * @var array
     *   The main response as was received from the web service.
     */
    protected array $mainAcumulusResponse = [];

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
    protected string $mainAcumulusResponseKey = '';

    /**
     * @var bool
     *   Indicates if the main response should be a list.
     */
    protected bool $isList = false;
    /**
     * @var int[]
     *   A list of http status codes that indicate that no unexpected exceptions
     *   occurred. Domain level errors, like input validation errors or
     *   non-existing id's, may be returned under these codes.
     */
    protected array $validHttpStatusCodes = [200, 400];
    protected array $possiblyValidHttpStatusCodes = [403, 404];

    /**
     * Constructs an AcumulusResult.
     *
     * @throws AcumulusResponseException
     */
    public function __construct(
        AcumulusRequest $acumulusRequest,
        HttpResponse $httpResponse,
        Util $util,
        Translator $translator
    ) {
        parent::__construct($translator);
        $this->util = $util;
        $this->acumulusRequest = $acumulusRequest;
        $this->httpResponse = $httpResponse;
        $this->processHttpResponse();
    }

    /**
     * Returns the structured main response part of the received response.
     *
     * @return array
     *   The main response part of the response as received from the Acumulus
     *   web service converted to a(n array of) keyed array(s). The status,
     *   errors and warnings are removed. In case of errors, this array may be
     *   empty or may contain the parameters that caused the error.
     */
    public function getMainAcumulusResponse(): array
    {
        return $this->mainAcumulusResponse;
    }

    /**
     * @return int
     *   The status is the result of taking the worst of:
     *   - The response API status (converted to a Severity).
     *   - The severity of the message collection (while ignoring messages of
     *     Severity::Log).
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

    public function getAcumulusRequest(): AcumulusRequest
    {
        return $this->acumulusRequest;
    }

    public function getHttpResponse(): HttpResponse
    {
        return $this->httpResponse;
    }

    /**
     * Returns the status code and password masked response from the Acumulus API.
     *
     * We mask all values of tags/keys that have 'password' in their name.
     * By masking any password, this result can be used for logging purposes.
     *
     * @throws JsonException
     */
    public function getMaskedResponse(): string
    {
        $code = $this->getHttpResponse()->getHttpStatusCode();
        $body = $this->util->maskArray($this->fullAcumulusResponse);
        return sprintf("Response: status=%d\nbody=%s", $code, json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * Convenience method to get the Content-Type header of the HTTP response.
     */
    protected function getHttpContentType(): string
    {
        return $this->getHttpResponse()->getHeader('Content-Type');
    }

    /**
     * Returns the format the contents should be in.
     *
     * Expect one of the following values ot be returned:
     * - 'json': If the 'format' tag was set to 'json' and we have a (domain
     *   level) response from the API server.
     * - 'xml': If 'format' was set to 'xml' and we have a (domain level)
     *   response from the API server OR if the API server encountered an error
     *   before parsing the 'format' tag, e.g. invalid xml.
     * - 'html': If the response came from another system, e.g. the load
     *   balancer (429) or http daemon (404)
     * - '': Absent ot incorrect Content-Type header or plain text
     */
    protected function getContentFormat(): string
    {
        $contentType = $this->getHttpContentType();
        return preg_match('|[^/]+(/([^ ;]*))?|', $contentType, $matches) ? strtolower($matches[2]) : '';
    }

    /**
     * Returns the format as requested per the 'format' tag in the request.
     */
    protected function getRequestedFormat(): string
    {
        return $this->getAcumulusRequest()->getSubmit()['format'] ?? 'xml';
    }

    /**
     * Processes the (non-null) http response.
     *
     * See also the section about error handling in the
     * {@see AcumulusResult documentation for this class}.
     * We are not in situation 1 (client side runtime errors that are thrown as
     * an exception). But are we in situation 2, 3, 4, or 5?
     *
     * @throws AcumulusResponseException
     *   If any (non domain level) error occurred during the execution of the
     *   request (situation 2 or 3 in the documentation on error handling).
     */
    protected function processHttpResponse(): void
    {
        try {
            // Inspect the http status code and body to see if we have a
            // situation 2, or 3 protocol level error (=> exception).
            $this->checkHttpStatusCode();
            // We now know that we are in situation 3 application level error,
            // 4, or 5: extract and process the Acumulus API response and do
            // some sanity checks. (Failing checks will throw an exception, but
            // this is not to be expected as it would indicate bugs or changed
            // behaviour in the API server).
            $this->convertHttpBodyToFullAcumulusResponse();
            $this->assertBasicResponse();
            $this->processAcumulusResponse();
            $this->assertConsistentAcumulusResponse();
        } catch (AcumulusException $e) {
            $body = $this->util->maskXmlOrJsonString($this->getHttpResponse()->getBody());
            $code = $this->getHttpResponse()->getHttpStatusCode();
            throw new AcumulusResponseException($body, $code, $e->getPrevious() ?? $e);
        }
    }

    /**
     * Checks if the http status code denotes a valid "successful" response.
     *
     * Note that domain level errors are seen as a successful response. Most
     * domain level errors are returned as a 400, but, e.g, an "entry not found"
     * error will be thrown as a 404.
     *
     * So, a 404 is a possibly valid http status code. It will be considered
     * valid if we have a properly formatted answer, otherwise it will be seen
     * as a true 404, i.e. an incorrect uri.
     *
     * @throws AcumulusResponseException
     *   If the http status code denotes an unexpected runtime error.
     */
    protected function checkHttpStatusCode(): void
    {
        $code = $this->getHttpResponse()->getHttpStatusCode();
        $body = $this->getHttpResponse()->getBody();
        if (!in_array($code, $this->validHttpStatusCodes, true)
            && (!in_array($code, $this->possiblyValidHttpStatusCodes, true)
                || $this->getContentFormat() !== $this->getRequestedFormat())
        ) {
            if ($this->getContentFormat() === 'html') {
                $body = $this->util->convertHtmlToPlainText($body);
            } else {
                $body = $this->util->maskXmlOrJsonString($body);
            }
            throw new AcumulusResponseException($body, $code);
        }
    }

    /**
     * Converts the HTTP body to a full Acumulus response.
     *
     * @throws AcumulusException
     *   Errors during JSON or XML conversion without access to the http
     *   response to add the http body to the message.
     * @throws AcumulusResponseException
     *   Errors during converting the http response to an AcumulusResponse,
     *   with access to the http response, so the http body cold be added to the
     *   message.
     */
    protected function convertHttpBodyToFullAcumulusResponse(): void
    {
        // @todo: redo and test error handling now we throw json errors. (after utils.php is converted)
        $body = $this->getHttpResponse()->getBody();
        $contentFormat = $this->getContentFormat();
        if ($contentFormat === 'json') {
            $acumulusResponse = $this->util->convertJsonToArray($body);
        } elseif ($contentFormat === 'xml') {
            $acumulusResponse = $this->util->convertXmlToArray($body);
        }
        if (!isset($acumulusResponse)) {
            // Contradiction between Content-type and expected format.
            $body = $this->util->maskXmlOrJsonString($body);
            $code = $this->getHttpResponse()->getHttpStatusCode();
            $contentType = $this->getHttpContentType();
            throw new AcumulusResponseException($body, $code, "Content-Type: $contentType");
        }
        $this->fullAcumulusResponse = $acumulusResponse;
    }

    /**
     * Asserts that the basic response is part of the response.
     *
     * More info about the basic response:
     * {@link https://www.siel.nl/acumulus/API/Basic_Response/}.
     *
     * @throws AcumulusResponseException
     *   If $this->fullAcumulusResponse does not contain the basic response.
     */
    protected function assertBasicResponse(): void
    {
        $response = $this->fullAcumulusResponse;
        if (!array_key_exists('status', $response)
            || !array_key_exists('errors', $response)
            || !array_key_exists('warnings', $response)
        ) {
            $body = $this->util->maskXmlOrJsonString($this->getHttpResponse()->getBody());
            $code = $this->getHttpResponse()->getHttpStatusCode();
            throw new AcumulusResponseException($body, $code, 'Basic response not found');
        }
    }

    /**
     * Processes the - well formatted - HTTP response body.
     *
     * - The parts of the basic response are extracted into separate properties,
     *   accessible via e.g. {@see getStatus()}, {@see getMessages()}, or
     *   {@see hasError()}.
     * - The remainder, the "real" response to the request, is set in the
     *   (protected) property {@see $mainAcumulusResponse}. This will be further
     *   processed and simplified when {@see setMainAcumulusResponseKey()} gets
     *   called, after which it becomes available to the calling side via
     *   {@see getMainAcumulusResponse()}.
     *
     * See:
     * - {@link https://www.siel.nl/acumulus/API/Basic_Response/} for the common
     *   parts of a response.
     * - The section about error handling in the
     *   {@see AcumulusResult documentation for this class}.
     *   We are not in situation 1 or 2 (runtime errors that are thrown as an
     *   exception). But in situation 3, 4, or 5.
     *
     */
    protected function processAcumulusResponse(): void
    {
        $fullResponse = $this->fullAcumulusResponse;

        // Move the basic response parts into their properties.
        $this->setApiStatus((int) $fullResponse['status']);
        if ($fullResponse['status'] === Api::Status_Exception) {
            // @todo: status = exception => any (error) message or code in the answer?
            $this->addException(new AcumulusException($this->getStatusText()));
        }
        unset($fullResponse['status']);

        if (!empty($fullResponse['errors']['error'])) {
            $this->addApiMessages($fullResponse['errors']['error'], Severity::Error);
        }
        unset($fullResponse['errors']);

        if (!empty($fullResponse['warnings']['warning'])) {
            $this->addApiMessages($fullResponse['warnings']['warning'], Severity::Warning);
        }
        unset($fullResponse['warnings']);

        // What is left is the main response, but that will be further
        // simplified when the main response key is set.
        $this->mainAcumulusResponse = $fullResponse;
    }

    /**
     * Performs a sanity check on the full Acumulus response.
     *
     * @throws AcumulusResponseException
     */
    protected function assertConsistentAcumulusResponse(): void
    {
        $this->assertResponseFormat();
        if (($this->hasError() && $this->getHttpResponse()->getHttpStatusCode() === 200)
            || ($this->getSeverity() <= Severity::Warning && $this->getHttpResponse()->getHttpStatusCode() !== 200)
        ) {
            $code = $this->getHttpResponse()->getHttpStatusCode();
            $this->createAndAddMessage("Inconsistent HTTP status code $code", Severity::Notice);
            // We do not throw an exception as the Acumulus API does contain
            // inconsistencies between response status and HTTP status code.
//            $body = $this->util->maskXmlOrJsonString($this->getHttpResponse()->getBody());
//            throw new AcumulusResponseException($body, $code, 'Inconsistent status code');
        }
    }

    /**
     * Asserts that the format of the HTTP body is correct.
     *
     * We check that the format as requested per the 'format' tag in the request
     * has been used in the response. We do so by comparing the Content-Type
     * header to the requested format. If not the same, we must have an error
     * response as, probably, the server could not parse the XML request and
     * defaulted to an XML response.
     *
     * @throws AcumulusResponseException
     */
    protected function assertResponseFormat(): void
    {
        if ($this->getContentFormat() !== $this->getRequestedFormat() && !$this->hasError()) {
            $body = $this->util->maskXmlOrJsonString($this->getHttpResponse()->getBody());
            $code = $this->getHttpResponse()->getHttpStatusCode();
            throw new AcumulusResponseException($body, $code, 'Inconsistent response format');
        }
    }

    /**
     * @param string $mainResponseKey
     * @param bool $isList
     *
     * @return $this
     */
    public function setMainAcumulusResponseKey(string $mainResponseKey, bool $isList = false): AcumulusResult
    {
        $this->mainAcumulusResponseKey = $mainResponseKey;
        $this->isList = $isList;
        $this->mainAcumulusResponse = $this->simplifyMainResponse($this->mainAcumulusResponse);
        return $this;
    }

    /**
     * Simplify the response by removing the main key.
     *
     * @param array $response
     *
     * @return array
     */
    protected function simplifyMainResponse(array $response): array
    {
        // Simplify response by removing main key, which should be the only
        // remaining key, except in case of errors, when there may be a number
        // of keys indicating the erroneous parameter values.
        if (isset($response[$this->mainAcumulusResponseKey])) {
            $response = $response[$this->mainAcumulusResponseKey];

            // Check for a non-empty list result.
            if ($this->isList && !empty($response)) {
                // Not empty: remove further indirection, i.e. get value of
                // "singular", which will be the first (and only) key.
                $singular = reset($response);
                // If there was only 1 list result, it wasn't put in an array.
                $response = !is_array($singular) ? [$singular] : $singular;
            }
        } else {
            // Not set: probably an error occurred. This object offers ways
            // to discover so. Therefore, we return an empty list if it
            // should have been a list.
            // @todo: we loose access to any additional error information...
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($this->isList) {
                $response = [];
            }
        }

        return $response;
    }

    /**
     * @param array $apiMessages
     *   Either:
     *   - A single api message, being an array with keys 'message', 'code', and
     *    'codetag'.
     *   - An array of API messages.
     *
     * @param int $severity
     *   One of the Severity::... constants.
     */
    protected function addApiMessages(array $apiMessages, int $severity): void
    {
        if (count($apiMessages) === 3
            && isset($apiMessages['code'], $apiMessages['codetag'], $apiMessages['message'])
        ) {
            // A single Acumulus API message: make it an array of API messages.
            $apiMessages = [$apiMessages];
        }
        foreach ($apiMessages as $apiMessage) {
            $this->addMessage(Message::createFromApiMessage($apiMessage, $severity));
        }
    }

    /**
     * Indicates if an error is due to an object not found or if it is for some
     * other reason.
     */
    public function isNotFound(): bool
    {
        // This is ugly, but the alternative is to spread this code knowledge
        // over the calling code, which is inevitable for other errors, but the
        // "not found" is the most common error and should be easy to recognise
        // on the calling side.
        $result = false;
        if ($this->hasError())
        {
            if ($this->getHttpResponse()->getHttpStatusCode() === 404) {
                $result = true;
            } elseif ($this->getHttpResponse()->getHttpStatusCode() === 400) {
                switch ($this->mainAcumulusResponseKey) {
                    case 'entry':
                        // Get entry, set delete status.
                        // Search is on entry id.
                        $result = $this->getByCodeTag('BK07TG65N') !== null;
                        break;
                    case 'invoice':
                        // Email as PDF, get/set payment status
                        // Search is on token.
                        $result = $this->getByCodeTag('7CFBA8K') !== null || $this->getByCodeTag('AAB6C3AA') !== null;
                        break;
                    case 'concept':
                        // Concept info
                        // Search is on concept id.
                        $result = $this->getByCodeTag('FGY040XX') !== null;
                        break;
                    case 'stock':
                        // Stock add
                        // Search is on product id.
                        // @todo
                        $result = $this->getByCodeTag('@todo') !== null;
                        break;
                    case '':
                    default:
                        // Unknown, not yet set: we don't know: return false;
                }
            }
        }
        return $result;
    }
}
