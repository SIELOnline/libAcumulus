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
 * A Result object will contain the:
 * - result status (internal code: one of the Severity::... constants).
 * - exception, if one was thrown.
 * - any error messages, local and/or remote.
 * - any warnings, local and/or remote.
 * - any notices, local.
 * - (raw) message sent, for logging purposes.
 * - (raw) message received, for logging purposes.
 * - result array.
 */
class Result extends MessageCollection
{
    // Code tags for raw request and response
    const CodeTagRawRequest = 'Request';
    const CodeTagRawResponse = 'Response';

    /**
     * @var int|null
     *   The received api status or null if not yet sent.
     */
    protected $apiStatus;

    /**
     * @var array
     *   The structured response as was received from the web service.
     */
    protected $response;

    /**
     * @var string
     *   The key that contains the main response of a service call.
     *
     *   Besides the general response structure (status, errors, and warnings),
     *   each service call result will contain the result specific for that
     *   call. This variable contains the key under which to find that. It
     *   should be set by each service call, allowing users of the service to
     *   retrieve the main result without the need to know more details then
     *   strictly needed of the Acumulus API.
     */
    protected $mainResponseKey;

    /**
     * @var bool
     *   Indicates if the main response should be a list.
     */
    protected $isList;

    /**
     * Result constructor.
     */
    public function __construct()
    {
        parent::__construct();
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
    protected function t($key)
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
    public function getStatus()
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
    public function getStatusText()
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
     */
    protected function setApiStatus(int $apiStatus)
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
    protected function ApiStatus2Severity($apiStatus)
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
     * @see https://www.siel.nl/acumulus/API/Basic_Response/ For the common part
     *   of a response.
     */
    public function setResponse(array $response)
    {
        // Move the common parts into their respective properties.
        if (isset($response['status'])) {
            $this->setApiStatus($response['status']);
            unset($response['status']);
        } else {
            $this->addMessage(new RuntimeException('Status not set in reponse'));
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
                // Check for an empty list result.
                if (!empty($response)) {
                    // Not empty: remove further indirection, i.e. get value of
                    // "singular", which will be the first (and only) key.
                    $response = reset($response);
                    // If there was only 1 list result, it wasn't put in an array.
                    if (!is_array(reset($response))) {
                        $response = array($response);
                    }
                }
            }
        }
        return $response;
    }

    /**
     * Sets the raw request as sent to the Acumulus web API.
     *
     * Only used for logging purposes.
     *
     * @param string $rawRequest
     */
    public function setRawRequest($rawRequest)
    {
        $this->addMessage(
            preg_replace('|<[a-z]*password>.*</[a-z]*password>|', '<$1password>REMOVED FOR SECURITY</$1password>', $rawRequest),
            Severity::Log,
            self::CodeTagRawRequest,
            0
        );
    }

    /**
     * Sets the raw response as received from the Acumulus web API.
     *
     * Only used for logging purposes.
     *
     * @param string $rawResponse
     */
    public function setRawResponse($rawResponse)
    {
        $rawResponse = preg_replace('|<([a-z]*)password>.*</[a-z]*password>|', '<$1password>REMOVED FOR SECURITY</$1password>', $rawResponse);
        $rawResponse = preg_replace('|"([a-z]*)password"(\s*):(\s*)"[^"]+"|', '"$1password"$2:$3"REMOVED FOR SECURITY"', $rawResponse);
        $this->addMessage(
            $rawResponse,
            Severity::Log,
            self::CodeTagRawResponse,
            0
        );
    }
}
