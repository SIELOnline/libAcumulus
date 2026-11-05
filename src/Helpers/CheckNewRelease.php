<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\ApiClient\GitHub;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;

use function sprintf;
use function strlen;

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
        protected readonly Translator $translator,
    ) {
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     *
     */
    protected function t(string $key): string
    {
        return $this->translator->get($key);
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

    public function formatMessage(): string
    {
        return sprintf(
            $this->t('newReleaseMessage'),
            $this->t('module_name'),  // Acumulus
            $this->t('module'),  // module, plugin, addon, ...
            $this->environment->get('moduleVersion'), // Something like 8.7.0
            $this->latestReleaseInfo->tag_name, // Something like 8.7.2
            $this->getReleasesUrl(), // https://github.com/SIELOnline/Acumulus-for-PrestaShop/releases
        );
    }
    protected function initialiseFromCache(): void
    {
        $this->cachedTime = $this->config->get('cachedLatestReleaseInfoTimestamp');
        $this->latestReleaseInfo = $this->config->getLatestReleaseInfo();
    }

    protected function refreshCache(): void
    {
        $this->cachedLatestReleaseInfo = $this->latestReleaseInfo;
        $this->latestReleaseInfo = $this->githubApiClient->getLatestRelease(...$this->shopCapabilities->getReleasesRepository());
        if ($this->cachedLatestReleaseInfo === null) {
            // First time ever: set cache to what we retrieved.
            $this->cachedLatestReleaseInfo = $this->latestReleaseInfo;
        }
        $this->cachedTime = time();
        $this->config->save(['cachedLatestReleaseInfo' => $this->latestReleaseInfo, 'cachedLatestReleaseInfoTimestamp' => $this->cachedTime]);
    }

    /**
     * Returns the URL that users can visit to get an overview of available releases.
     */
    private function getReleasesUrl(): string
    {
        $releasesPart = strpos($this->latestReleaseInfo->assets[0]->browser_download_url, '/releases/') + strlen('/releases');
        return substr($this->latestReleaseInfo->assets[0]->browser_download_url, 0, $releasesPart);
    }
}
