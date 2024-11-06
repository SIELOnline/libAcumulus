<?php
/**
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection  SensitiveParameter
 * @noinspection PhpLanguageLevelInspection  An attribute is a comment in 7.4
 */

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use DOMDocument;
use DOMElement;
use DOMException;
use RuntimeException;
use SensitiveParameter;
use Siel\Acumulus\ApiClient\AcumulusException;

use Siel\Acumulus\Meta;

use function count;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function sprintf;

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
     * Each key is converted to a tag (the tag name being the key in lowercase),
     * no attributes are used. Numeric sub-arrays are repeated using the same
     * tag (not a numeric index).
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
    protected function convertToDom(mixed $values, DOMDocument|DOMElement $element): DOMDocument|DOMElement
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
                    $node = $document->createElement(strtolower($key));
                    $element->appendChild($node);
                }
                $this->convertToDom($value, $node);
            }
        } else {
            if (is_bool($values)) {
                $text = $values ? 'true' : 'false';
            } else {
                $text = (string) $values;
            }
            $element->appendChild($document->createTextNode($text));
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
     * throws \Siel\Acumulus\ApiClient\AcumulusException|\JsonException
     * @throws \JsonException
     *   The parameter is not an object or array or an error occurred during
     *   conversion.
     */
    public function convertToJson(object|array $objectOrArray): string
    {
        try {
            return json_encode($objectOrArray, Meta::JsonFlags);
        } catch (\JsonException $e) {
            throw new AcumulusException($e->getMessage(), $e->getCode(), $e);
        }
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
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\JsonException
     *   Either:
     *   - The $json string is not valid JSON.
     *   - The $json string could not be converted to an (associative) array
     *     because it is either not an object, or it is too deep.
     */
    public function convertJsonToArray(string $json): array
    {
        $result = json_decode($json, true, 512, Meta::JsonFlags);
        if (!is_array($result)) {
            throw new AcumulusException('Not a JSON array');
        }
        return $result;
    }

    /**
     * Throws an exception containing the received HTML.
     *
     * @param string $body
     *   HTML string, probably containing an error page.
     *
     * @return string
     *   The plain text of this page.
     */
    public function convertHtmlToPlainText(string $body): string
    {
        // DOMDocument::loadHtml() does not accept an empty string as document.
        if ($body === '') {
            return $body;
        }
        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'utf-8');
        if ($doc->loadHTML($body, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED)) {
            $body = $doc->textContent;
        }
        $lines = preg_split('/[\r\n]/', $body, -1, PREG_SPLIT_NO_EMPTY);
        return implode("\n", array_filter(array_map('trim', $lines)));
    }

    /**
     * Converts an array with texts to a(n HTML) list.
     *
     * @param string[] $list
     *   List of strings, if the key is s a string, it serves as a
     *   (translatable) label.
     * @param bool $isHtml
     *   Return HTML or plain text.
     * @param callable $t
     *   Translate function.
     *
     * @return string
     */
    public function arrayToList(array $list, bool $isHtml, callable $t): string
    {
        $result = '';
        if (count($list) !== 0) {
            foreach ($list as $key => $line) {
                if (is_string($key) && !ctype_digit($key)) {
                    $key = $t($key);
                    $line = "$key: $line";
                }
                $result .= $isHtml ? "<li>$line</li>" : "• $line";
                $result .= "\n";
            }
            if ($isHtml) {
                $result = "<ul>$result</ul>";
            }
            $result .= "\n";
        }
        return $result;
    }

    /**
     * Recursively masks passwords in an array.
     *
     * Acumulus API specific: passwords fields contain 'password' in their name.
     */
    public function maskArray(#[SensitiveParameter] array $subject): array
    {
        array_walk_recursive($subject, static function (&$value, $key) {
            if (is_string($key) && stripos($key, 'password') !== false) {
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
    public function maskXmlOrJsonString(
        #[SensitiveParameter]
        string $subject
    ): string {
        return $this->maskJson($this->maskXml($subject));
    }

    /**
     * Masks passwords in an XML string
     *
     * Acumulus API specific: passwords fields end with 'password'.
     */
    public function maskXml(
        #[SensitiveParameter]
        string $subject
    ): string {
        // Mask all values that have 'password' in their tag.
        // @todo: use back reference in closing tag, but test it (is this still used, is it tested?)
        return preg_replace(
            '|<([a-z]*)password>.*</\1password>|s',
            '<$1password>REMOVED FOR SECURITY</$1password>',
            $subject
        );
    }

    /**
     * Masks passwords in a Json string
     *
     * Acumulus API specific: passwords fields end with 'password'.
     */
    public function maskJson(
        #[SensitiveParameter]
        string $subject
    ): string {
        // Mask all values that have 'password' in their key.
        return preg_replace(
            '!"([a-z]*)password"(\s*):(\s*)"(((\\\\.)|[^\\\\"])*)"!',
            '"$1password"$2:$3"REMOVED FOR SECURITY"',
            $subject
        );
    }

    /**
     * Masks passwords in an XML string
     *
     * Acumulus API specific: passwords fields end with 'password'.
     */
    public function maskHtml(
        #[SensitiveParameter]
        string $subject
    ): string {
        // Mask all "value"s of input elements of type ='password'.
        // @todo: use back reference in closing tag, but test it (is this still used, is it tested?)
        return preg_replace(
            '|name="password" value="[^"]*"|',
            'name="password" value="REMOVED FOR SECURITY"',
            $subject
        );
    }

    /**
     * Throws an exception with all libxml error messages as message.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException
     *   Always.
     */
    protected function raiseLibxmlError(): void
    {
        $errors = libxml_get_errors();
        $messages = [];
        foreach ($errors as $error) {
            // Overwrite our own code with the 1st code we get from libxml.
            $messages[] = sprintf(
                'Line %d, column: %d: %s %d - %s',
                $error->line,
                $error->column,
                $error->level === LIBXML_ERR_WARNING ? 'warning' : 'error',
                $error->code,
                trim($error->message)
            );
        }
        throw new AcumulusException(implode("\n", $messages));
    }
}
