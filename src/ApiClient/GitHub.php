<?php

declare(strict_types=1);

namespace Siel\Acumulus\ApiClient;

use RuntimeException;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use stdClass;
use Throwable;

use function sprintf;

/**
 * GitHub provides an easy interface towards the GitHub API.
 *
 * For now, we will only use the releases API to look for new versions of a webshop
 * specific module itself, not the libAcumulus library used by all modules. Modules that
 * want to use this feature should add the zip file (containing the module and library
 * following the webshop specific guidelines for publishing modules) to a release on
 * GitHub. Releases should be tagged by the version number.
 *
 * The repos API does not require authentication for read actions on public projects.
 */
class GitHub
{
    protected array $gitHubApiParts;

    public function __construct(protected readonly Log $log)
    {
        $this->gitHubApiParts = [
            'domain' => 'api.github.com',
            'api' => 'repos',
        ];
    }

    /**
     * Returns an object with info about the latest relase for the given repository.
     *
     * The return value will look something like
     * ```
     * {
     *  "url": "https://api.github.com/repos/SIELOnline/Acumulus-for-PrestaShop/releases/429442848",
     *  "assets_url": "...",
     *  "upload_url": "...",
     *  "html_url": "https://github.com/SIELOnline/Acumulus-for-PrestaShop/releases/tag/8.7.0",
     *  "id": 429442848,
     *  "author": {
     *      "login": "fietserwin",
     *      "id": 1893437,
     *      "node_id": "MDQ6VXNlcjE4OTM0Mzc=",
     *      "avatar_url": "https://avatars.githubusercontent.com/u/1893437?v=4",
     *      "gravatar_id": "",
     *      "url": "https://api.github.com/users/fietserwin",
     *      "html_url": "https://github.com/fietserwin",
     *      ...
     *      "type": "User",
     *      "user_view_type": "public",
     *      "site_admin": false
     *  },
     *  "node_id": "RE_kwDOBxdKys4ZmMcg",
     *  "tag_name": "8.7.0", // Name of the tag this release points to
     *  "target_commitish": "master",
     *  "name": "8.7.0", // Name of the release, does not have to be the same as the tag.
     *  "draft": false,
     *  "immutable": false,
     *  "prerelease": false,
     *  "created_at": "2025-11-04T08:22:23Z", // Create timestmap of the tag this release refers to (is based on).
     *  "updated_at": "2026-10-30T20:11:19Z", // Update timestamp of this release.
     *  "published_at": "2026-10-30T20:11:19Z", // Published timestamp of this release.
     *  "assets": [
     *      {
     *          // Link to this asset, will return this sub-object.
     *          "url": "https://api.github.com/repos/SIELOnline/Acumulus-for-PrestaShop/releases/assets/693275090",
     *          "id": 693275090,
     *          "node_id": "RA_kwDOBxdKys4pUonS",
     *          "name": "PrestaShop-Acumulus-8.7.0.zip",
     *          "label": null,
     *          "uploader": {
     *              "login": "fietserwin",
     *              "id": 1893437,
     *              "node_id": "MDQ6VXNlcjE4OTM0Mzc=",
     *              ... (see author)
     *          },
     *          "content_type": "application/x-zip-compressed",
     *          "state": "uploaded",
     *          "size": 799877,
     *          "digest": "sha256:7b52c7b82ce19ee7d6491009e2cf18aa04827220bf374a46ae46e75d19f8f912",
     *          "download_count": 0,
     *          "created_at": "2026-10-30T20:05:46Z",
     *          "updated_at": "2026-10-30T20:05:48Z",
     *          // The link to the zipped module to download.
     *          "browser_download_url": "https://github.com/SIELOnline/Acumulus-for-PrestaShop/releases/download/8.7.0/PrestaShop-Acumulus-8.7.0.zip"
     *      }
     *  ],
     *  // The following 2 links are zips with the code of the project itself.
     *  "tarball_url": "https://api.github.com/repos/SIELOnline/Acumulus-for-PrestaShop/tarball/8.7.0",
     *  "zipball_url": "https://api.github.com/repos/SIELOnline/Acumulus-for-PrestaShop/zipball/8.7.0",
     * // A message created by the committer: we use it for a short message about this
     * // version meant to be read by the admin of the webshop.
     *  "body": "Version 8.7.0 of the Acumuls-for-PrestaShop module.\r\n\r\n- Changelog: [changelog of the libAcumulus
     * project](https://raw.githubusercontent.com/SIELOnline/libAcumulus/refs/heads/master/changelog.txt)\r\n- Compatible with PrestaShop versions:
     * 1.7 to 9.x"
     * }
     * ```
     */
    public function getLatestRelease(string $repo, string $owner = 'SIELOnline'): stdClass
    {
        return $this->callApiFunction('releases/latest', $repo, $owner);
    }

    /**
     * @return stdClass[]
     *   An array of releases, see {@see getLatestRelease()} for the structure.
     *
     * @noinspection PhpUnused
     */
    public function getReleases(string $repo, string $owner = 'SIELOnline'): array
    {
        return $this->callApiFunction('releases', $repo, $owner);
    }

    private function callApiFunction(string $apiFunction, string $repo, string $owner): array|stdClass|null
    {
        $uri = $this->constructUri($apiFunction, $repo, $owner);
        $httpRequest = new HttpRequest([CURLOPT_USERAGENT => $owner]);
        try {
            $httpResponse = $httpRequest->get($uri);
            if ($httpResponse->getHttpStatusCode() !== 200) {
                throw new RuntimeException($httpResponse->getBody(), $httpResponse->getHttpStatusCode());
            }
            $logLevel = Severity::Success;
            $response = $this->extractBody($httpResponse->getBody());
            return $response;
        } catch (Throwable $e) {
            $exception = $e;
            $logLevel = Severity::Exception;
            throw $e;
        } finally {
            /** @noinspection PhpUndefinedVariableInspection, ProperNullCoalescingOperatorUsageInspection */
            $this->log->apiCall(
                $apiFunction,
                sprintf('%s %s', $httpRequest->getMethod(), $httpRequest->getUri()),
                $exception ?? sprintf('%s %s', $httpResponse->getHttpStatusCode(), json_encode($response, JSON_THROW_ON_ERROR)),
                $logLevel
            );
        }
    }

    /**
     * Returns the API endpoint.
     *
     * @return string
     *   Something like https://api.github.com/repos/SIELOnline/Acumulus-for-PrestaShop/releases.
     */
    private function constructUri(string $apiFunction, string $repo, string $owner): string
    {
        return sprintf('https://%s/%s/%s/%s/%s', $this->gitHubApiParts['domain'], $this->gitHubApiParts['api'], $owner, $repo, $apiFunction);
    }

    /**
     * Converts a response body to a(n array of) release(s).
     *
     * @return \stdClass[]|\stdClass
     */
    private function extractBody(string $responseBody): array|stdClass
    {
        $response = json_decode($responseBody, false, flags: JSON_THROW_ON_ERROR);
        if ($response instanceof stdClass) {
            $this->cleanRelease($response);
        } else {
            foreach ((array) $response as $release) {
                $this->cleanRelease($release);
            }
        }
        return $response;
    }

    private function cleanRelease(stdClass $response): void
    {
        unset($response->author);
        foreach ($response->assets as $asset) {
            unset($asset->uploader);
        }
    }
}
