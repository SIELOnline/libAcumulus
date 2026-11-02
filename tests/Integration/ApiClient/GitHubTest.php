<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\GitHub;
use Siel\Acumulus\Helpers\Container;
use stdClass;

use function count;

/**
 * GitHubTest tests the {@see GitHub} class.
 */
class GitHubTest extends TestCase
{
    protected Container $container;
    protected GitHub $githubApiClient;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('TestWebShop', 'nl');
        $this->githubApiClient = $this->container->getGitHubApiClient();
    }

    public function testGetReleases(): void
    {
        $repo = $this->container->getShopCapabilities()->getReleasesRepository();
        static::assertNotNull($repo);
        $githubApiClient = $this->container->getGitHubApiClient();
        $releases = $githubApiClient->getReleases(...$repo);
        $release = $releases[0];
        $this->test1Release($release);
    }

    public function testGetLatestRelease(): void
    {
        $repo = $this->container->getShopCapabilities()->getReleasesRepository();
        static::assertNotNull($repo);
        $githubApiClient = $this->container->getGitHubApiClient();
        $release = $githubApiClient->getLatestRelease(...$repo);
        $this->test1Release($release);
    }

    private function test1Release(stdClass $release): void
    {
        static::assertNotEmpty($release->url);
        static::assertIsString($release->url);
        static::assertNotEmpty($release->tag_name);
        static::assertIsString($release->tag_name);
        static::assertNotEmpty($release->name);
        static::assertIsString($release->name);
        static::assertNotEmpty($release->id);
        static::assertIsInt($release->id);
        static::assertIsArray($release->assets);
        static::assertGreaterThan(0, count($release->assets));
        $asset = $release->assets[0];
        static::assertInstanceOf(stdClass::class, $asset);
        static::assertNotEmpty($asset->url);
        static::assertIsString($asset->url);
        static::assertNotEmpty($asset->id);
        static::assertIsInt($asset->id);
        static::assertIsString($asset->name);
        static::assertStringEndsWith('.zip', $asset->name);
        static::assertIsString($asset->browser_download_url);
        static::assertStringEndsWith($asset->name, $asset->browser_download_url);
        static::assertNotEmpty($release->body);
        static::assertIsString($release->body);
    }
}
