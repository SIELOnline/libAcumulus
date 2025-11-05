<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Utils;

/**
 * Form contains form related test functionalities for library and shop tests.
 *
 * - Saving rendered form contents.
 * - Asserting that fields appear in the form.
 */
trait Form
{
    use AcumulusContainer;
    use Path;

    private static string $htmlStart = <<<LONGSTRING
<!DOCTYPE html>
<!--suppress GrazieInspection, HtmlUnknownTarget -->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>

LONGSTRING;
    private static string $htmlEnd = <<<LONGSTRING

</body>
</html>
LONGSTRING;

    /**
     * Saves form HTML contents.
     *
     * @param string $name
     *   The form name.
     * @param string $data
     *   The HTML string to be saved.
     */
    protected function saveFormHtml(string $name, string $data): void
    {
        $path = self::getDataPath() . '/Form';
        $fileName = "$name.html";
        if (file_exists("$path/$fileName")) {
            $fileName = "$name.latest.html";
        }
        $data = static::getContainer()->getUtil()->maskHtml($data);
        file_put_contents("$path/$fileName", static::$htmlStart . $data . static::$htmlEnd);
    }

    /**
     * Asserts that for each field in the form field definitions an HTML element with that
     * id exists in the render output.
     */
    protected function formContainsFields(array $expectedFields, string $form): void
    {
        foreach ($expectedFields as $id => $field) {
            $id = $field['id'] ?? $id;
            static::assertStringContainsString("id=\"$id\"", $form, "id=\"$id\" not found in form");
            if (isset($field['fields'])) {
                $this->formContainsFields($field['fields'], $form);
            }
        }
    }
}
