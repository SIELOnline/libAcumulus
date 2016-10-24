<?php
namespace Siel\Acumulus\Web;

/**
 * Defines an interface to communicate with the Acumulus WebAPI.
 */
interface CommunicatorInterface {
    /**
     * Sends a message to the given API function and returns the results.
     *
     * For debugging purposes the return array also includes a key 'trace',
     * containing an array with 2 keys, request and response, with the actual
     * strings as were sent.
     *
     * @param string $apiFunction
     *   The API function to invoke.
     * @param array $message
     *   The values to submit.
     *
     * @return array
     *   An array with the results including any warning and/or error messages.
     */
    public function callApiFunction($apiFunction, array $message);
}
