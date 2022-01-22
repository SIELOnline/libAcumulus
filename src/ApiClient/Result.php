<?php
namespace Siel\Acumulus\ApiClient;

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
     * @var \Siel\Acumulus\ApiClient\HttpRequest|null
     *   The HTTP request.
     */
    protected ?HttpRequest $httpRequest;

    /**
     * @var \Siel\Acumulus\ApiClient\HttpResponse|null
     *   The received HTTP response.
     */
    protected ?HttpResponse $httpResponse;

    /**
     * @var int|null
     *   The received api status or null if not yet sent.
     */
    protected ?int $apiStatus;

    /**
     * @var array
     *   The structured response as was received from the web service.
     */
    protected array $response;

    /**
     * @var string
     *   The key that contains the main response of a service call.
     *
     *   Besides the general response structure (status, errors, and warnings),
     *   each service call result will contain the result specific for that
     *   call. This variable contains the key under which to find that. It
     *   should be set by each service call, allowing users of the service to
     *   retrieve the main result without the need to know more details than
     *   strictly needed of the Acumulus API.
     */
    protected string $mainResponseKey;

    /**
     * @var bool
     *   Indicates if the main response should be a list.
     */
    protected bool $isList;

    /**
     * Result constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->httpRequest = null;
        $this->httpResponse = null;
        $this->apiStatus = null;
        $this->response = [];
        $this->mainResponseKey = '';
        $this->isList = false;
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
     * {@see https://www.siel.nl/acumulus/API/Basic_Response/} for the common
     * part of a response.
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

    /**
     * @return \Siel\Acumulus\ApiClient\HttpRequest|null
     */
    public function getHttpRequest(): ?HttpRequest
    {
        return $this->httpRequest;
    }

    /**
     * Sets the http request.
     *
     * Information from the http request is solely used for logging purposes.
     *
     * @param \Siel\Acumulus\ApiClient\HttpRequest $httpRequest
     */
    public function setHttpRequest(HttpRequest $httpRequest): void
    {
        $this->httpRequest = $httpRequest;
    }

    /**
     * @return \Siel\Acumulus\ApiClient\HttpResponse|null
     */
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
        // @todo: invoke $this->setResponse() from here.
    }
}
