<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Collectors\AddressCollector;
use Siel\Acumulus\Collectors\BasicSubmitCollector;
use Siel\Acumulus\Collectors\ConnectorCollector;
use Siel\Acumulus\Collectors\ContractCollector;
use Siel\Acumulus\Collectors\CustomerCollector;
use Siel\Acumulus\Collectors\EmailAsPdfCollector;
use Siel\Acumulus\Collectors\InvoiceCollector;
use Siel\Acumulus\Collectors\StockTransactionCollector;
use Siel\Acumulus\Completors\AddressCompletor;
use Siel\Acumulus\Completors\CustomerCompletor;
use Siel\Acumulus\Completors\EmailInvoiceAsPdfCompletor;
use Siel\Acumulus\Completors\InvoiceCompletor;
use Siel\Acumulus\Completors\LineCompletor;
use Siel\Acumulus\Completors\NoopCompletor;
use Siel\Acumulus\Completors\StockTransactionCompletor;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\BasicSubmit;
use Siel\Acumulus\Data\Connector;
use Siel\Acumulus\Data\Contract;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\EmailInvoiceAsPdf;
use Siel\Acumulus\Data\EmailPackingSlipAsPdf;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Mail\CrashMail;
use Siel\Acumulus\PrestaShop\Collectors\ItemLineCollector;
use Siel\Acumulus\PrestaShop\Collectors\PaymentFeeLineCollector;
use Siel\Acumulus\PrestaShop\Collectors\ShippingLineCollector;
use Siel\Acumulus\Shop\BatchForm;
use Siel\Acumulus\Shop\InvoiceStatusForm;
use Siel\Acumulus\Shop\MessageForm;
use Siel\Acumulus\Shop\RatePluginForm;
use Siel\Acumulus\Shop\RegisterForm;
use Siel\Acumulus\TestWebShop\Config\ConfigStore;
use Siel\Acumulus\TestWebShop\Config\Environment;
use Siel\Acumulus\TestWebShop\Config\ShopCapabilities;
use Siel\Acumulus\TestWebShop\Mail\Mailer;

use function define;
use function defined;
use function dirname;

/**
 * ContainerTest tests the Acumulus {@see \Siel\Acumulus\Helpers\Container}.
 */
class ContainerTest extends TestCase
{
    private static Container $container;

    public static function setUpBeforeClass(): void
    {
        self::$container = new Container('TestWebShop');
    }

    /**
     * Tests the (almost) independent classes in the Helpers namespace.
     */
    public function testHelpersNamespace1(): void
    {
        $this->expectNotToPerformAssertions();
        $container = self::$container;
        Container::getContainer();
        $container->getLog();
        $container->getTranslator();
        $container->getUtil();
        $container->getCheckAccount();
        $container->getRequirements();
        $container->getUtil();
        $container->getCountries();
        $container->getFieldExpander();
        $container->getFieldExpanderHelp();
        $container->getEvent();
    }

    public function testConfigNamespace(): void
    {
        $container = self::$container;
        $object = $container->getConfigStore();
        $this->assertInstanceOf(ConfigStore::class, $object);
        $object = $container->getEnvironment();
        $this->assertInstanceOf(Environment::class, $object);
        $object = $container->getShopCapabilities();
        $this->assertInstanceOf(ShopCapabilities::class, $object);
        $container->getConfigUpgrade();
        $container->getConfig();
        $container->getMappings();
    }

    public function testApiClientNamespace(): void
    {
        $this->expectNotToPerformAssertions();
        $container = self::$container;
        $container->getAcumulusApiClient();
        $container->createHttpRequest([]);
        $container->createAcumulusRequest();
    }

    public static function dataNameSpaceDataProvider(): array
    {
        return [
            [DataType::Address, Address::class,],
            [DataType::BasicSubmit, BasicSubmit::class,],
            [DataType::Connector, Connector::class,],
            [DataType::Contract, Contract::class,],
            [DataType::Customer, Customer::class,],
            [DataType::EmailInvoiceAsPdf, EmailInvoiceAsPdf::class,],
            [DataType::EmailPackingSlipAsPdf, EmailPackingSlipAsPdf::class,],
            [DataType::Invoice, Invoice::class,],
            [DataType::Line, Line::class,],
            [DataType::StockTransaction, StockTransaction::class,],
        ];
    }

    /**
     * @dataProvider dataNameSpaceDataProvider
     */
    public function testDataNameSpace(string $dataType, string $dataClass): void
    {
        $container = self::$container;
        $object = $container->createAcumulusObject($dataType);
        /** @noinspection UnnecessaryAssertionInspection */
        $this->assertInstanceOf($dataClass, $object);
    }

    public static function collectorNameSpaceDataProvider(): array
    {
        return [
            [DataType::Address, AddressType::Invoice, AddressCollector::class,],
            [DataType::Address, AddressType::Shipping, AddressCollector::class,],
            [DataType::BasicSubmit, null, BasicSubmitCollector::class,],
            [DataType::Connector, null, ConnectorCollector::class,],
            [DataType::Contract, null, ContractCollector::class,],
            [DataType::Customer, null, CustomerCollector::class,],
            [DataType::EmailAsPdf, EmailAsPdfType::Invoice, EmailAsPdfCollector::class,],
            [DataType::EmailAsPdf, EmailAsPdfType::PackingSlip, EmailAsPdfCollector::class,],
            [DataType::Invoice, null, InvoiceCollector::class,],
            [DataType::Line, LineType::Item, ItemLineCollector::class,],
            [DataType::Line, LineType::Shipping, ShippingLineCollector::class,],
            [DataType::Line, LineType::PaymentFee, PaymentFeeLineCollector::class,],
            [DataType::StockTransaction, null, StockTransactionCollector::class,],
        ];
    }

    /**
     * @dataProvider collectorNameSpaceDataProvider
     */
    public function testCollectorsNameSpace(string $dataType, ?string $subType, string $collectorClass): void
    {
//        $container = self::$container;
        $container = new Container('PrestaShop');
        if (!defined('_PS_ROOT_DIR_')) {
            define ('_PS_ROOT_DIR_', dirname(__FILE__, 5));
        }
        $object = $container->getCollector($dataType, $subType);
        /** @noinspection UnnecessaryAssertionInspection  we check for a subtype of the specified return type */
        $this->assertInstanceOf($collectorClass, $object);
    }

    /**
     * Tests the dependent classes in the Helpers namespace.
     */
    public function testHelpersNamespace2(): void
    {
        $this->expectNotToPerformAssertions();
        $container = self::$container;
        $container->getCrashReporter();
        $container->getFormHelper();
        $container->getFormMapper();
        $container->getFormRenderer();
    }

    public function testGetForms(): void
    {
        $container = self::$container;
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

    /**
     * Tests the dependent classes in the Helpers namespace.
     */
    public function testMailNamespace(): void
    {
        $container = self::$container;
        $object = $container->getMailer();
        $this->assertInstanceOf(Mailer::class, $object);
        $object = $container->getMail('CrashMail', 'Mail');
        $this->assertInstanceOf(CrashMail::class, $object);
    }

    public function testInvoiceNamespace(): void
    {
        $container = self::$container;
        $source = $container->createSource(Source::Order, 1);
        $item = $container->createItem(2, $source);
        $container->createProduct(3, $item);
        $object = $container->getCompletor();
        $this->assertInstanceOf(Completor::class, $object);
        $container->createInvoiceAddResult('ContainerTest::testInvoiceNamespace()');
        $container->getCompletorInvoiceLines();
        $container->getFlattenerInvoiceLines();
    }

    public function testShopNamespace(): void
    {
        $this->expectNotToPerformAssertions();
        $container = self::$container;
        $container->getAcumulusEntryManager();
        $container->createAcumulusEntry([]);
        $container->getInvoiceManager();
    }

    public static function completorsNameSpaceDataProvider(): array
    {
        return [
            ['', '', Completor::class,],
            [DataType::Address, AddressType::Invoice, AddressCompletor::class,],
            [DataType::Address, AddressType::Shipping, AddressCompletor::class,],
            [DataType::BasicSubmit, '', NoopCompletor::class,],
            [DataType::Connector, '', NoopCompletor::class,],
            [DataType::Contract, '', NoopCompletor::class,],
            [DataType::Customer, '', CustomerCompletor::class,],
            [DataType::EmailAsPdf, EmailAsPdfType::Invoice, EmailInvoiceAsPdfCompletor::class,],
            [DataType::EmailAsPdf, EmailAsPdfType::PackingSlip, NoopCompletor::class,],
            [DataType::Invoice, '', InvoiceCompletor::class,],
            [DataType::Line, LineType::Item, LineCompletor::class,],
            [DataType::Line, LineType::Shipping, LineCompletor::class,],
            [DataType::StockTransaction, '', StockTransactionCompletor::class,],
        ];
    }

    /**
     * @dataProvider completorsNameSpaceDataProvider
     */
    public function testCompletorsNameSpace(string $dataType, string $subType, string $completorClass): void
    {
        $container = self::$container;
        $object = $container->getCompletor($dataType, $subType);
        $this->assertInstanceOf($completorClass, $object);
    }
}
