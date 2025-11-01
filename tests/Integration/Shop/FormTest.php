<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\Shop;

use PHPUnit\Framework\TestCase;
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

/**
 * FormTest tests the creation and rendering of the forms.
 */
class FormTest extends TestCase
{
    use AcumulusTestUtils;

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function setUpBeforeClass(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'get';
    }

    private function getInvoiceSource(): Source
    {
        $objects = (new GetTestData())->getJson();
        $order = $objects->order;
        return static::getContainer()->createSource(Source::Order, $order);
    }

    private function getForm(string $type): Form
    {
        return static::getContainer()->getForm($type);
    }

    private function getRenderer(): FormRenderer
    {
        return static::getContainer()->getFormRenderer();
    }

    public static function formTypesProvider(): array
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
     * Tests the form-creation and rendering process.
     *
     * @dataProvider formTypesProvider
     */
    public function testCreate(string $type, string $class): void
    {
        $form = $this->getForm($type);
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf($class, $form);

        if ($type === 'invoice') {
            /** @noinspection PhpPossiblePolymorphicInvocationInspection  will be an InvoiceStatusForm */
            $form->setSource($this->getInvoiceSource());
        }
        $renderer = $this->getRenderer();
        $output = $renderer->render($form);
        $name = substr($class, strrpos($class, '\\') + 1);
        $this->saveFormHtml(lcfirst($name), $output);

        static::assertNotEmpty($output);

        $this->formContainsFields($form->getFields(), $output);
    }
}
