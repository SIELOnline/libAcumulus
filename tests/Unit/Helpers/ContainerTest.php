<?php /** @noinspection EfferentObjectCouplingInspection */

/**
 * @noinspection PhpMissingDocCommentInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusRequest;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ConfigUpgrade;
use Siel\Acumulus\Helpers\CrashReporter;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\FormMapper;
use Siel\Acumulus\Helpers\FormRenderer;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Shop\AcumulusEntry;
use Siel\Acumulus\Shop\AdvancedConfigForm;
use Siel\Acumulus\Shop\ConfigForm;
use Siel\Acumulus\Shop\RatePluginForm;
use Siel\Acumulus\Shop\RegisterForm;
use Siel\Acumulus\Tests\TestWebShop\Config\ConfigStore;
use Siel\Acumulus\Tests\TestWebShop\Config\Environment;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Tests\TestWebShop\Config\ShopCapabilities;
use Siel\Acumulus\Tests\TestWebShop\Helpers\Mailer;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Requirements;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Helpers\Util;
use Siel\Acumulus\Helpers\Token;

use function get_class;

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
        $container = new Container('WooCommerce');
        $object = $container->getLog();
        $this->assertInstanceOf(Log::class, $object);
        $object = $container->getTranslator();
        $this->assertInstanceOf(Translator::class, $object);
        $object = $container->getRequirements();
        $this->assertInstanceOf(Requirements::class, $object);
        $object = $container->getUtil();
        $this->assertInstanceOf(Util::class, $object);
        $object = $container->getToken();
        $this->assertInstanceOf(Token::class, $object);
        $object = $container->getCountries();
        $this->assertInstanceOf(Countries::class, $object);
    }

    public function testConfigNamespace(): void
    {
        $container = new Container('Tests\TestWebShop');
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
    }

    public function testApiClientNamespace(): void
    {
        $container = new Container('Tests\TestWebShop');
        $object = $container->getAcumulusApiClient();
        $this->assertInstanceOf(Acumulus::class, $object);
        $object = $container->createHttpRequest([]);
        $this->assertInstanceOf(HttpRequest::class, $object);
        $object = $container->createAcumulusRequest();
        $this->assertInstanceOf(AcumulusRequest::class, $object);
    }
    /**
     * Tests the dependent classes in the Helpers namespace.
     */
    public function testHelpersNamespace2(): void
    {
        $container = new Container('Tests\TestWebShop');
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
        $container = new Container('Tests\TestWebShop');
        $object = $container->getForm('config');
        $this->assertInstanceOf(ConfigForm::class, $object);
        $object = $container->getForm('advanced');
        $this->assertInstanceOf(AdvancedConfigForm::class, $object);
        // Error : Cannot instantiate abstract class Siel\Acumulus\Shop\InvoiceManager
//        $object = $container->getForm('batch');
//        $this->assertInstanceOf(BatchForm::class, $object);
        $object = $container->getForm('register');
        $this->assertInstanceOf(RegisterForm::class, $object);
//        $object = $container->getForm('invoice');
//        $this->assertInstanceOf(InvoiceStatusForm::class, $object);
        $object = $container->getForm('rate');
        $this->assertInstanceOf(RatePluginForm::class, $object);
    }

    public function testInvoiceNamespace(): void
    {
        $container = new Container('Tests\TestWebShop');
//        $object = $container->createSource(Source::Order, []);
//        $this->assertInstanceOf(Creator::class, $object);
//        $object = $container->getCreator();
//        $this->assertInstanceOf(Creator::class, $object);
        $object = $container->getCompletor();
        $this->assertInstanceOf(Completor::class, $object);
        $object = $container->createInvoiceAddResult('');
        $this->assertInstanceOf(InvoiceAddResult::class, $object);
    }

    public function testShopNamespace(): void
    {
        $container = new Container('Tests\TestWebShop');
//        $object = $container->getAcumulusEntryManager();
//        $this->assertInstanceOf(AcumulusEntryManager::class, $object);
        $object = $container->createAcumulusEntry([]);
        $this->assertSame(AcumulusEntry::class, get_class($object));
//        $object = $container->getInvoiceManager();
//        $this->assertInstanceOf(InvoiceManager::class, $object);
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
