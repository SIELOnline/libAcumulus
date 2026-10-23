<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Helpers;

use Siel\Acumulus\Helpers\Log as BaseLog;

use Siel\Acumulus\Helpers\Severity;
use Siel\Whmcs\Acumulus\Acumulus;

use Throwable;

use WHMCS\Config\Setting;

use function is_string;
use function logActivity;
use function sprintf;

/**
 * Extends the base log class to log any library logging to the WHMCS activity log.
 */
class Log extends BaseLog
{
    /**
     * {@inheritdoc}
     *
     * This override logs to the WHMCS module log. It does so unconditionally as a WHMCS
     * setting determines whether this log is enaled or disabled. So this might be a
     * no-op. Therefore, an exception, as response, is also logged separately to the
     * activity log.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function apiCall(string $service, string $request, Throwable|string $response, int $logLevel): array
    {
        $moduleLoggingStatus = Setting::getValue('ModuleDebugMode');
        if ($moduleLoggingStatus === 'on') {
            $module = sprintf('%s %s', Acumulus::Name, Acumulus::Version);
            $responseData = is_string($response) ? $response : $this->exceptionToMessage($response, true);
            logModuleCall($module, $service, $request, $responseData, '', []);
            if ($response instanceof Throwable) {
                return [$this->exception($response)];
            }
        } else {
            // Log a shortened version to the activity log, depending on our log level.
            if (is_string($response)) {
                if ($logLevel === Severity::Success) {
                    $message = "$service: success";
                } else {
                    $message = "$service: error: " . substr($response, 0, 80);
                }
                return [$this->log($logLevel, $message)];
            } else {
                return [$this->exception($response)];
            }
        }
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * This override logs to the WHMCS activity log.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function logContextCompletedMessage(string $message): void
    {
        logActivity($message);
    }
}
