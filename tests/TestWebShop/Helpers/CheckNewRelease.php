<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Helpers;

use Siel\Acumulus\Helpers\CheckNewRelease as BaseCheckNewRelease;

use function dirname;

/**
 * CheckNewRelease checks for a new release on a GitHub based releases repository.
 */
class CheckNewRelease extends BaseCheckNewRelease
{
    /**
     * Number of hours that the cache was set.
     */
    public int $cachedTimeDiff = 20;

    protected function initialiseFromCache(): void
    {
        $this->cachedTime = time() - $this->cachedTimeDiff * 60 * 60;
        $this->latestReleaseInfo = json_decode(file_get_contents( dirname(__FILE__, 3) . '/Data/PreviousRelease.json'), false);
    }

    protected function refreshCache(): void
    {
        $this->cachedLatestReleaseInfo = $this->latestReleaseInfo;
        $releaseInfo = $this->cachedTimeDiff <= 40 ? 'PreviousRelease.json' : 'LatestRelease.json';
        $this->latestReleaseInfo = json_decode(file_get_contents( dirname(__FILE__, 3) . '/Data/' . $releaseInfo), false);
        $this->cachedTime = time();
    }
}
