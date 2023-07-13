<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use RuntimeException;

use function is_array;

/**
 * FieldExpanderHelp formats the field references help.
 */
class FieldExpanderHelp
{
    /**
     * Returns field reference help data.
     *
     * @return array
     *   The json decoded field reference data. An array describing the most important
     *   objects, properties and methods that can be used as field reference.
     *
     * @throws \JsonException|\RuntimeException
     */
    protected function getJson(string $jsonFile = 'FieldExpanderHelp.json'): array
    {
        $filename = __DIR__ . "/$jsonFile";
        if (!is_readable($filename)) {
            throw new RuntimeException("File '$filename' not found or not readable.");
        }
        $json = file_get_contents($filename);
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws \JsonException|\RuntimeException
     */
    public function getHelp(): string
    {
        return $this->arrayToList($this->getJson());
    }

    /**
     * Converts an array with texts to an HTML list.
     *
     * @param array $list
     *   Multi-level list of strings keyed by object/property name.
     */
    protected function arrayToList(array $list): string
    {
        $result = '';
        if (count($list) !== 0) {
            $result .= '<dl class="property-list">';
            foreach ($list as $key => $line) {
                $result .= "<dt>$key</dt>";
                if (is_array($line)) {
                    $info = $line['info'] ?? '';
                    unset($line['info']);
                    $result .= '<dd>';
                    $result .= "<span class=\"info\">$info</span>";
                    $result .= $this->arrayToList($line);
                    $result .= '</dd>';
                } else {
                    $result .= "<dd>$line</dd>";
                }
            }
            $result .= "</dl>\n";
        }
        return $result;
    }

}
