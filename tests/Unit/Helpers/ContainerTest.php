<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusRequest;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\Collectors\AddressCollector;
use Siel\Acumulus\Collectors\CollectorManager;
use Siel\Acumulus\Collectors\CustomerCollector;
use Siel\Acumulus\Collectors\EmailAsPdfCollector;
use Siel\Acumulus\Collectors\InvoiceCollector;
use Siel\Acumulus\Collectors\LineCollector;
use Siel\Acumulus\Completors\CustomerCompletor;
use Siel\Acumulus\Completors\InvoiceCompletor;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ConfigUpgrade;
use Siel\Acumulus\Config\Mappings;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\EmailInvoiceAsPdf;
use Siel\Acumulus\Data\EmailPackingSlipAsPdf;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Helpers\CheckAccount;
use Siel\Acumulus\Helpers\CrashReporter;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\FieldExpanderHelp;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\FormMapper;
use Siel\Acumulus\Helpers\FormRenderer;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\CompletorInvoiceLines;
use Siel\Acumulus\Invoice\FlattenerInvoiceLines;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Invoice\Product;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\WooCommerce\Collectors\ItemLineCollector;
use Siel\Acumulus\Shop\AcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager;
use Siel\Acumulus\Shop\BatchForm;
use Siel\Acumulus\Shop\InvoiceManager;
use Siel\Acumulus\Shop\InvoiceStatusForm;
use Siel\Acumulus\Shop\MessageForm;
use Siel\Acumulus\Shop\RatePluginForm;
use Siel\Acumulus\Shop\RegisterForm;
use Siel\Acumulus\TestWebShop\Config\ConfigStore;
use Siel\Acumulus\TestWebShop\Config\Environment;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\TestWebShop\Config\ShopCapabilities;
use Siel\Acumulus\TestWebShop\Helpers\Mailer;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Requirements;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Helpers\Util;

use Siel\Acumulus\WooCommerce\Collectors\OtherLineCollector;
use Siel\Acumulus\WooCommerce\Collectors\ShippingLineCollector;

use function get_class;

/**
 * ContainerTest tests the Acumulus {@see \Siel\Acumulus\Helpers\Container}.
 */
class ContainerTest extends TestCase
{
    protected function getContainer(string $namespace): Container
    {
        return new Container($namespace, 'en');
    }

    /**
     * Tests the (almost) independent classes in the Helpers namespace.
     */
    public function testHelpersNamespace1(): void
    {
        $container = new Container('TestWebShop');
        $object = Container::getContainer();
        $this->assertInstanceOf(Container::class, $object);
        $object = $container->getLog();
        $this->assertInstanceOf(Log::class, $object);
        $object = $container->getTranslator();
        $this->assertInstanceOf(Translator::class, $object);
        $object = $container->getUtil();
        $this->assertInstanceOf(Util::class, $object);
        $object = $container->getCheckAccount();
        $this->assertInstanceOf(CheckAccount::class, $object);
        $object = $container->getRequirements();
        $this->assertInstanceOf(Requirements::class, $object);
        $object = $container->getUtil();
        $this->assertInstanceOf(Util::class, $object);
        $object = $container->getCountries();
        $this->assertInstanceOf(Countries::class, $object);
        $object = $container->getFieldExpander();
        $this->assertInstanceOf(FieldExpander::class, $object);
        $object = $container->getFieldExpanderHelp();
        $this->assertInstanceOf(FieldExpanderHelp::class, $object);
    }

    public function testConfigNamespace(): void
    {
        $container = new Container('TestWebShop');
        $object = $container->getConfigStore();
        $this->assertInstanceOf(ConfigStore::class, $object);
        $object = $container->getEnvironment();
        $this->assertInstanceOf(Environment::class, $object);
        $object = $container->getShopCapabilities();
        $this->assertInstanceOf(ShopCapabilities::class, $object);
        $object = $container->getConfigUpgrade();
        $this->assertInstanceOf(ConfigUpgrade::class, $object);
        $object = $container->getConfig();
        $this->assertInstanceOf(Config::class, $object);
        $object = $container->getMappings();
        $this->assertInstanceOf(Mappings::class, $object);
    }

    public function testApiClientNamespace(): void
    {
        $container = new Container('TestWebShop');
        $object = $container->getAcumulusApiClient();
        $this->assertInstanceOf(Acumulus::class, $object);
        $object = $container->createHttpRequest([]);
        $this->assertInstanceOf(HttpRequest::class, $object);
        $object = $container->createAcumulusRequest();
        $this->assertInstanceOf(AcumulusRequest::class, $object);
    }

    public function dataNameSpaceDataProvider(): array
    {
        return [
            [DataType::Address, Address::class,],
            [DataType::Customer, Customer::class,],
            [DataType::EmailInvoiceAsPdf, EmailInvoiceAsPdf::class,],
            [DataType::EmailPackingSlipAsPdf, EmailPackingSlipAsPdf::class,],
            [DataType::Invoice, Invoice::class,],
            [DataType::Line, Line::class,],
        ];
    }

    /**
     * @dataProvider dataNameSpaceDataProvider
     */
    public function testDataNameSpace(string $dataType, string $dataClass): void
    {
        $container = new Container('TestWebShop');
        $object = $container->createAcumulusObject($dataType);
        /** @noinspection UnnecessaryAssertionInspection */
        $this->assertInstanceOf($dataClass, $object);
    }

    public function collectorNameSpaceDataProvider(): array
    {
        return [
            [DataType::Address, AddressType::Invoice, AddressCollector::class,],
            [DataType::Address, AddressType::Shipping, AddressCollector::class,],
            [DataType::Customer, null, CustomerCollector::class,],
            [DataType::EmailAsPdf, EmailAsPdfType::Invoice, EmailAsPdfCollector::class,],
            [DataType::EmailAsPdf, EmailAsPdfType::PackingSlip, EmailAsPdfCollector::class,],
            [DataType::Invoice, null, InvoiceCollector::class,],
            [DataType::Line, LineType::Item , ItemLineCollector::class,],
            [DataType::Line, LineType::Shipping , ShippingLineCollector::class,],
            [DataType::Line, LineType::Other , OtherLineCollector::class,],
            [DataType::Line, LineType::PaymentFee , LineCollector::class,],
        ];
    }

    /**
     * @dataProvider collectorNameSpaceDataProvider
     */
    public function testCollectorsNameSpace(string $dataType, ?string $subType, string $collectorClass): void
    {
        $container = new Container('WooCommerce');
        $object = $container->getCollector($dataType, $subType);
        /** @noinspection UnnecessaryAssertionInspection  we check for a subtype of the specified return type */
        $this->assertInstanceOf($collectorClass, $object);
    }

    /**
     * Tests the dependent classes in the Helpers namespace.
     */
    public function testHelpersNamespace2(): void
    {
        $container = new Container('TestWebShop');
        $object = $container->getMailer();
        $this->assertInstanceOf(Mailer::class, $object);
        $object = $container->getCrashReporter();
        $this->assertInstanceOf(CrashReporter::class, $object);

        $object = $container->getFormHelper();
        $this->assertInstanceOf(FormHelper::class, $object);
        $object = $container->getFormMapper();
        $this->assertInstanceOf(FormMapper::class, $object);
        $object = $container->getFormRenderer();
        $this->assertInstanceOf(FormRenderer::class, $object);
    }

    public function testGetForms(): void
    {
        $container = new Container('TestWebShop');
        $object = $container->getForm('batch');
        $this->assertInstanceOf(BatchForm::class, $object);
        $object = $container->getForm('register');
        $this->assertInstanceOf(RegisterForm::class, $object);
        $object = $container->getForm('invoice');
        $this->assertInstanceOf(InvoiceStatusForm::class, $object);
        $object = $container->getForm('rate');
        $this->assertInstanceOf(RatePluginForm::class, $object);
        $object = $container->getForm('message');
        $this->assertInstanceOf(MessageForm::class, $object);
    }

    public function testInvoiceNamespace(): void
    {
        $container = new Container('TestWebShop');
        $object = $container->createSource(Source::Order, 1);
        $this->assertInstanceOf(Source::class, $object);
        $object = $container->createItem(1, $object);
        $this->assertInstanceOf(Item::class, $object);
        $object = $container->createProduct(1, $object);
        $this->assertInstanceOf(Product::class, $object);
        $object = $container->getCompletor();
        $this->assertInstanceOf(Completor::class, $object);
        $object = $container->createInvoiceAddResult('ContainerTest::testInvoiceNamespace()');
        $this->assertInstanceOf(InvoiceAddResult::class, $object);
        $object = $container->getCompletorInvoiceLines();
        $this->assertInstanceOf(CompletorInvoiceLines::class, $object);
        $object = $container->getFlattenerInvoiceLines();
        $this->assertInstanceOf(FlattenerInvoiceLines::class, $object);
    }

    public function testShopNamespace(): void
    {
        $container = new Container('TestWebShop');
        $object = $container->getAcumulusEntryManager();
        $this->assertInstanceOf(AcumulusEntryManager::class, $object);
        $object = $container->createAcumulusEntry([]);
        $this->assertSame(AcumulusEntry::class, get_class($object));
        $object = $container->getInvoiceManager();
        $this->assertInstanceOf(InvoiceManager::class, $object);
    }

    public function testCompletorsNameSpace(): void
    {
        $container = new Container('TestWebShop');
        $completorTypes = [
            DataType::Customer => CustomerCompletor::class,
            DataType::Invoice => InvoiceCompletor::class,
        ];
        foreach ($completorTypes as $dataType => $completorType) {
            $object = $container->getCompletor($dataType);
            $this->assertInstanceOf($completorType, $object);
        }
        $object = $container->getCollectorManager();
        $this->assertInstanceOf(CollectorManager::class, $object);
    }

//    public function testGetInstance()
//    {
//    }
//
//    public function testSetLanguage()
//    {
//    }
//
//    public function testGetLanguage()
//    {
//    }
//
//    public function testSetCustomNamespace()
//    {
//    }
//
//    public function testAddTranslations()
//    {
//    }
}
