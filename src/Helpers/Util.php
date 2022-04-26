<?php
namespace Siel\Acumulus\Helpers;

use DOMDocument;
use DOMElement;
use DOMException;
use RuntimeException;
use Siel\Acumulus\ApiClient\AcumulusException;

/**
 * Class Util offers some utility functions:
 * - XML: convert an array from or to XML.
 * - HTML: check if a string is an HTML string
 * - JSON: Check for json decoding or encoding errors
 * - Password masking (for logging purposes)
 *
 * Though the utility methods in this class are meant to be generally usable,
 * they may contain some knowledge about Acumulus API details.
 */
class Util
{
    /**
     * Converts a keyed, optionally multi-level, array to XML.
     *
     * Acumulus specific:
     * Each key is converted to a tag, no attributes are used. Numeric
     * sub-arrays are repeated using the same key (not their numeric index).
     *
     * @param array $values
     *   The array to convert to XML.
     *
     * @return string
     *   The XML string
     *
     * @throws \RuntimeException
     *   An error occurred during the conversion to an XML string. This is in no
     *   way to be expected, so we throw a {@see \RuntimeException}, not an
     *   {@see \Siel\Acumulus\ApiClient\AcumulusException} which will not be
     *   caught during the request-response cycle.
     */
    public function convertArrayToXml(array $values): string
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->xmlStandalone = true;
        $dom->formatOutput = true;

        try {
            $dom = $this->convertToDom($values, $dom);
            $result = $dom->saveXML();
            if (!$result) {
                throw new RuntimeException('DOMDocument::saveXML failed');
            }
            // Backslashes get lost between here and the Acumulus API, but
            // encoding them makes them get through. Solve here until the
            // real error has been found and solved.
            /** @noinspection PhpUnnecessaryLocalVariableInspection */
            $result = str_replace('\\', '&#92;', $result);
            return $result;
        } catch (DOMException $e) {
            // Convert a DOMException to a RuntimeException, so we only have to
            // handle RuntimeExceptions.
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Recursively converts a value to a DOMDocument|DOMElement.
     *
     * @param mixed $values
     *   A keyed array, a numerically indexed array, or a scalar type.
     * @param DOMDocument|DOMElement $element
     *   The element to append the values to.
     *
     * @return DOMDocument|DOMElement
     *
     * @throws \DOMException
     */
    protected function convertToDom($values, $element)
    {
        /** @var DOMDocument $document */
        static $document = null;
        $isFirstElement = true;

        if ($element instanceof DOMDocument) {
            $document = $element;
        }
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if (is_int($key)) {
                    if ($isFirstElement) {
                        $node = $element;
                        $isFirstElement = false;
                    } else {
                        $node = $document->createElement($element->tagName);
                        $element->parentNode->appendChild($node);
                    }
                } else {
                    $node = $document->createElement($key);
                    $element->appendChild($node);
                }
                $this->convertToDom($value, $node);
            }
        } else {
            $element->appendChild($document->createTextNode(is_bool($values) ? ($values ? 'true' : 'false') : $values));
        }

        return $element;
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
     * @throws \Siel\Acumulus\ApiClient\AcumulusException
     *   Either:
     *   - The $xml string is not valid xml
     *   - The $xml string could not be converted to an (associative) array
     *     (we use json_encode() and json_decode() to convert to an array, so
     *     this would probably mean a structure that is too deep).
     */
    public function convertXmlToArray(string $xml): array
    {
        // Convert the response to an array via a 3-way conversion:
        // - create a simplexml object
        // - convert that to json
        // - convert json to array
        libxml_use_internal_errors(true);
        $result = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$result) {
            $this->raiseLibxmlError();
        }
        return $this->convertJsonToArray($this->convertToJson($result));
    }

    /**
     * Converts an object or array to JSON.
     *
     * @param object|array $objectOrArray
     *
     * @return string
     *   The JSON representation for the given object or array.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException
     *   The parameter is not an object or array or an error occurred during
     *   conversion.
     */
    public function convertToJson($objectOrArray): string
    {
        if (!is_object($objectOrArray) && !is_array($objectOrArray)) {
            throw new AcumulusException('Not an object or array');
        }
        $result = json_encode($objectOrArray);
        if (!$result) {
            $this->raiseJsonError();
        }
        return $result;
    }

    /**
     * Converts a JSON string to an (associative) array.
     *
     * @param string $json
     *   A string containing JSON.
     *
     * @return array
     *  An (associative) array representation of the JSON string.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException
     *   Either:
     *   - The $json string is not valid JSON.
     *   - The $json string could not be converted to an (associative) array
     *     because it is either not an object, or it is too deep.
     */
    public function convertJsonToArray(string $json): array
    {
        $result = json_decode($json, true);
        if ($result === null) {
            $this->raiseJsonError();
        } elseif (!is_array($result)) {
            throw new AcumulusException('Not a JSON structure');
        }
        return $result;
    }

    /**
     * Checks if a string, typically an HTTP response, is an HTML string.
     *
     * @param string $response
     *
     * @return bool
     *   True if the response is HTML, false otherwise.
     */
    public function isHtmlResponse(string $response): bool
    {
        return strtolower(substr($response, 0, strlen('<!doctype html'))) === '<!doctype html'
            || strtolower(substr($response, 0, strlen('<html'))) === '<html'
            || strtolower(substr($response, 0, strlen('<body'))) === '<body';
    }

    /**
     * Throws an exception containing the received HTML.
     *
     * @param string $body
     *   HTML string, probably containing an error page.
     *
     * @returns string
     *   The plain text of this page.
     */
    public function convertHtmlToPlainText(string $body): string
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'utf-8');
        if ($doc->loadHTML($body, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED)) {
            $body = $doc->textContent;
        }
        $lines = preg_split('/[\r\n]/', $body, -1, PREG_SPLIT_NO_EMPTY);
        return implode("\n", array_filter(array_map('trim', $lines)));
    }

    /**
     * Recursively masks passwords in an array.
     *
     * Acumulus API specific: passwords fields contain 'password' in their name.
     */
    public function maskArray(array $subject): array
    {
        array_walk_recursive($subject, function (&$value, $key) {
            if (strpos(strtolower($key), 'password') !== false) {
                $value = 'REMOVED FOR SECURITY';
            }
        });
        return $subject;
    }

    /**
     * Masks passwords in an XML or json string
     *
     * To be used when logging raw http responses instead of the fullResponse
     * property from an {@see \Siel\Acumulus\ApiClient\AcumulusResult}.
     *
     * Acumulus API specific: passwords fields end with 'password'.
     */
    public function maskXmlOrJsonString(string $subject): string
    {
        return $this->maskJson($this->maskXml($subject));
    }

    /**
     * Masks passwords in an XML string
     *
     * Acumulus API specific: passwords fields end with 'password'.
     */
    public function maskXml(string $subject): string
    {
        // Mask all values that have 'password' in their key.
        return preg_replace(
            '|<([a-z]*)password>.*</[a-z]*password>|',
            '<$1password>REMOVED FOR SECURITY</$1password>',
            $subject
        );
    }

    /**
     * Masks passwords in a Json string
     *
     * Acumulus API specific: passwords fields end with 'password'.
     */
    public function maskJson(string $subject): string
    {
        // Mask all values that have 'password' in their key.
        return preg_replace(
            '!"([a-z]*)password"(\s*):(\s*)"(((\\\\.)|[^\\\\"])*)"!',
            '"$1password"$2:$3"REMOVED FOR SECURITY"',
            $subject
        );
    }

    /**
     * Throws an exception with all libxml error messages as message.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException
     *   Always.
     */
    protected function raiseLibxmlError()
    {
        $errors = libxml_get_errors();
        $messages = [];
        foreach ($errors as $error) {
            // Overwrite our own code with the 1st code we get from libxml.
            $messages[] = sprintf('Line %d, column: %d: %s %d - %s', $error->line, $error->column, $error->level === LIBXML_ERR_WARNING ? 'warning' : 'error', $error->code, trim($error->message));
        }
        throw new AcumulusException(implode("\n", $messages));
    }

    /**
     * Throws an exception with an error message based on the last json error.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException
     *   Always.
     */
    public function raiseJsonError()
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
        throw new AcumulusException($message);
    }
}
