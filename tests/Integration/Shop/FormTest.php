<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\Shop;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormRenderer;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\ActivateSupportForm;
use Siel\Acumulus\Shop\BatchForm;
use Siel\Acumulus\Shop\InvoiceStatusForm;
use Siel\Acumulus\Shop\MappingsForm;
use Siel\Acumulus\Shop\MessageForm;
use Siel\Acumulus\Shop\RatePluginForm;
use Siel\Acumulus\Shop\RegisterForm;
use Siel\Acumulus\Shop\SettingsForm;
use Siel\Acumulus\Tests\AcumulusTestUtils;

use Siel\Acumulus\Tests\Data\GetTestData;

use function dirname;

/**
 * SettingsFormTest tests the creation and rendering of the settings form.
 */
class FormTest extends TestCase
{
    use AcumulusTestUtils;

    private static Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function setUpBeforeClass(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'get';
    }

    protected static function getAcumulusContainer(): Container
    {
        if (!isset(self::$container)) {
            self::$container = new Container('TestWebShop', 'nl');
            self::$container->addTranslations('Translations', 'Invoice');
        }
        return self::$container;
    }

    private function getInvoiceSource(): Source
    {
        $objects = (new GetTestData())->getJson();
        $order = $objects->order;
        return $this->getAcumulusContainer()->createSource(Source::Order, $order);
    }

    protected function getTestsPath(): string
    {
        return dirname(__FILE__, 3);
    }

    private function getForm(string $type): Form
    {
        return $this->getAcumulusContainer()->getForm($type);
    }

    private function getRenderer(): FormRenderer
    {
        return $this->getAcumulusContainer()->getFormRenderer();
    }

    public function formTypesProvider(): array
    {
        return [
            ['settings', SettingsForm::class],
            ['mappings', MappingsForm::class],
            ['batch', BatchForm::class],
            ['register', RegisterForm::class],
            ['activate', ActivateSupportForm::class],
            ['rate', RatePluginForm::class],
            ['message', MessageForm::class],
            ['invoice', InvoiceStatusForm::class],
        ];
    }

    /**
     * Tests the form creation and rendering process.
     *
     * @dataProvider formTypesProvider
     */
    public function testCreate(string $type, string $class): void
    {
        $form = $this->getForm($type);
        $this->assertInstanceOf($class, $form);

        if ($type === 'invoice') {
            /** @noinspection PhpPossiblePolymorphicInvocationInspection  will be an InvoiceStatusForm */
            $form->setSource($this->getInvoiceSource());
        }
        $renderer = $this->getRenderer();
        $output = $renderer->render($form);
        $name = substr($class, strrpos($class, '\\') + 1);
        $this->saveTestHtmlData($this->getTestsPath() . '/Data', lcfirst($name), $output);

        $this->assertNotEmpty($output);

        $this->outputContainsFields($form->getFields(), $output);
    }

    /**
     * Asserts that for each field in the form field definitions an HTML element with that
     * id exists in the render output.
     */
    private function outputContainsFields(array $expectedFields, string $output): void
    {
        foreach ($expectedFields as $id => $field) {
            $id = $field['id'] ?? $id;
            $this->assertStringContainsString("id=\"$id\"", $output, "id=\"$id\" not found in output");
            if (isset($field['fields'])) {
                $this->outputContainsFields($field['fields'], $output);
            }
        }
    }
}
