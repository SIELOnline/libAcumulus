<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use Siel\Acumulus\Helpers\CheckNewRelease;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Container;

/**
 * CheckNewReleaseTest tests the {@see CheckNewRelease} class.
 */
class CheckNewReleaseTest extends TestCase
{
    protected Container $container;
    protected CheckNewRelease $releaseChecker;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('TestWebShop', 'nl');
        $this->releaseChecker = $this->container->getCheckNewRelease();
    }

    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    public function testDoCheckNoSupport(): void
    {
        $this->container->getShopCapabilities()->hasReleasesRepository = false;
        $result = $this->releaseChecker->doCheck();
        $this->assertFalse($result);
    }

    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    public function testDoCheckSupportNoCacheRefresh(): void
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $this->container->getShopCapabilities()->hasReleasesRepository = true;
        $this->container->getEnvironment()->version = '8.6.12';
        $this->releaseChecker->cachedTimeDiff = 20;
        $result = $this->releaseChecker->doCheck();
        $this->assertFalse($result);
    }

    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    public function testDoCheckSupportCacheRefreshNoNewRelease(): void
    {
        $this->container->getShopCapabilities()->hasReleasesRepository = true;
        $this->container->getEnvironment()->version = '8.6.12';
        $this->releaseChecker->cachedTimeDiff = 40;
        $result = $this->releaseChecker->doCheck();
        $this->assertFalse($result);
    }

    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    public function testDoCheckSupportCacheRefreshNewRelease(): void
    {
        $this->container->getEnvironment()->version = '8.6.12';
        $this->container->getShopCapabilities()->hasReleasesRepository = true;
        $this->releaseChecker->cachedTimeDiff = 80;
        $result = $this->releaseChecker->doCheck();
        $this->assertTrue($result);
    }
}
