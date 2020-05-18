<?php
namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\Translations;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Severity;

class AcumulusTest extends TestCase
{
    /**
     * @var \Siel\Acumulus\ApiClient\Acumulus
     */
    protected $acumulusClient;

    protected function setUp(): void
    {
        // Using TestWebShop ensures that we are using a test HttpCommunicator.
        $container = new Container('TestWebShop', 'nl');
        $container->getTranslator()->add(new Translations());
        $this->acumulusClient = $container->getAcumulusApiClient();
    }

    public function testGetAbout()
    {
        $result = $this->acumulusClient->getAbout();

        $this->assertEquals(Severity::Success, $result->getStatus());

    }

}
