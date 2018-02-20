<?php
namespace Siel\Acumulus\Web;

/**
 * Defines an interface to communicate with the Acumulus WebAPI.
 */
interface CommunicatorInterface {
    /**
     * Sends a message to the given API function and returns the results.
     *
     * @param string $apiFunction
     *   The API function to invoke.
     * @param array $message
     *   The values to submit.
     * @param \Siel\Acumulus\Web\Result $result
     *   It is possible to already create a Result object before calling the Web
     *   Service to store local messages. By passing this Result object these
     *   local messages will be merged with any remote messages in the returned
     *   Result object.
     *
     * @return \Siel\Acumulus\Web\Result A Result object containing the results.
     * A Result object containing the results.
     */
    public function callApiFunction($apiFunction, array $message, Result $result = null);

    /**
     * Returns the uri to the requested API call.
     *
     * @param string $apiFunction
     *
     * @return string
     *   The uri to the requested API call.
     */
    public function getUri($apiFunction);
}
