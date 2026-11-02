<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\ApiClient\GitHub;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;

/**
 * CheckNewRelease checks for a new release on a GitHub based repository with releases.
 */
class CheckNewRelease
{
    protected int $cachedTime;
    protected ?object $cachedLatestReleaseInfo;
    protected ?object $latestReleaseInfo;

    public function __construct(
        protected readonly GitHub $githubApiClient,
        protected readonly ShopCapabilities $shopCapabilities,
        protected readonly Config $config,
        protected readonly Environment $environment,
    ) {
    }

    /**
     * Checks for a new Release.
     *
     * Checking is done as follows:
     * - Only webshops that have a GitHub repo for new releases do this.
     * - If the cached version of the latest release info is expired (1 day) the GitHub
     *   repo is queried for its latest release info which is cached along with the time
     *   the query was executed.
     * - The version is compared with the current version in the cache and of the
     *   module/plugin.
     *     - If newer than cached: reset display message time.
     *     - If newer than current and displaying message is not preventd by an earlier
     *      'later' or 'hide' click from the user: show message form.
     *
     * @return bool
     *   Whether to show a message about a new release.
     */
    public function doCheck(): bool
    {
        if ($this->shopCapabilities->getReleasesRepository() !== null) {
            $this->initialiseFromCache();

            // Should we refresh the cache?
            if ($this->cachedTime + 24 * 60 * 60 <= time()) {
                $this->refreshCache();
                if (version_compare($this->latestReleaseInfo->tag_name, $this->cachedLatestReleaseInfo->tag_name, '>')) {
                    $this->config->save(['showNewReleaseMessage' => 0]);
                }
            }

            if (version_compare($this->latestReleaseInfo->tag_name, $this->environment->get('moduleVersion'), '>')) {
                // A newer version has been released.
                if ($this->config->get('showNewReleaseMessage') <= time()) {
                    // Showing message has not been prevented by the user: show it.
                    return true;
                }
            }
        }
        return false;
    }

    protected function initialiseFromCache(): void
    {
        $this->cachedTime = $this->config->get('cachedLatestReleaseInfoTimestamp');
        $this->latestReleaseInfo = $this->config->get('cachedLatestReleaseInfo');
    }

    protected function refreshCache(): void
    {
        $this->cachedLatestReleaseInfo = $this->latestReleaseInfo;
        $this->latestReleaseInfo = $this->githubApiClient->getLatestRelease(...$this->shopCapabilities->getReleasesRepository());
        $this->cachedTime = time();
        $this->config->save(['cachedLatestReleaseInfo' => $this->latestReleaseInfo, 'cachedLatestReleaseInfoTimestamp' => $this->cachedTime]);
    }
}
