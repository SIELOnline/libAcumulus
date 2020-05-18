<?php
namespace Siel\Acumulus\ApiClient;

use RuntimeException;

/**
 * HttpCommunicator implements the communication with the Acumulus WebAPI at the
 * https level.
 *
 * It offers:
 * - Https communication with the Acumulus webservice using the curl library:
 *   setting up the connection, sending the request, receiving the response.
 * - Good error handling.
 */
class HttpCommunicator
{

    /**
     * Executes an http post request.
     *
     * @param string $uri
     *   The uri to send the HTTP request to.
     * @param array|string $post
     *   An array of values to be placed in the POST body or an url-encoded
     *   string that contains all the POST values
     *
     * @return string
     *  The response body from the HTTP response.
     *
     * @throws \RuntimeException
     */
    public function post($uri, $post)
    {
        // Open a curl connection.
        $ch = curl_init();
        if (!$ch) {
            $this->raiseCurlError($ch, 'curl_init()');
        }

        // Configure the curl connection.
        // Since 2017-09-19 the Acumulus web service only accepts TLS 1.2.
        // - Apparently, some curl libraries do support this version but do not
        //   use it by default, so we force it.
        // - Apparently, some up-to-date curl libraries do not define this
        //   constant,so we define it, if not defined.
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }
        $options = [
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_TIMEOUT => 20,
            //CURLOPT_PROXY => '127.0.0.1:8888', // Uncomment to debug with Fiddler.
            //CURLOPT_SSL_VERIFYPEER => false, // Uncomment to debug with Fiddler.
        ];
        if (!curl_setopt_array($ch, $options)) {
            $this->raiseCurlError($ch, 'curl_setopt_array()');
        }

        // Send and receive over the curl connection.
        $response = curl_exec($ch);
        if ($response === false) {
            $this->raiseCurlError($ch, 'curl_exec()');
        }
        // Close the connection (this operation cannot fail).
        curl_close($ch);

        return $response;
    }

    /**
     * Adds a curl error message to the result.
     *
     * @param resource|bool $ch
     * @param string $function
     *
     * @throws \RuntimeException
     */
    protected function raiseCurlError($ch, $function)
    {
        $curlVersion = curl_version();
        $message = sprintf('%s (curl: %s): ', $function, $curlVersion['version']);
        if ($ch) {
            $code = curl_errno($ch);
            $message .= sprintf('%d - %s', $code, curl_error($ch));
        } else {
            $code = 703;
            $message .= 'no curl handle';
        }
        curl_close($ch);
        throw new RuntimeException($message, $code);
    }
}
