<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Customer;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Severity;

/**
 * CompleteEmail validates or fills the e-mail address.
 */
class CompleteEmail extends BaseCompletorTask
{
    /**
     * Validates or fills the e-mail address.
     *
     * Not allowed:
     * - Multiple addresses.
     * - Friendly name (My Name <my.name@example.com>).
     *
     * If no e-mail address is provided a fallback address will be used.
     *
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        // Check email address.
        if (empty($acumulusObject->email)) {
            $acumulusObject->email = $this->configGet('emailIfAbsent');
            // @todo: add warning to Result?
            $message = Message::create($this->t('message_warning_no_email'), Severity::Warning, 801);
            $acumulusObject->addWarning($message);
        } else {
            $email = $acumulusObject->email;
            // Look for multiple addresses, but do not mistake a comma in a
            // friendly name for a separator, so look behind the first @.
            /** @noinspection DuplicatedCode */
            $at = strpos($email, '@');
            if ($at !== false) {
                // Comma (,) used as separator?
                $comma = strpos($email, ',', $at);
                if ($comma !== false && $at < $comma) {
                    // Multiple addresses, keep the first.
                    $email = trim(substr($email, 0, $comma));
                }
                // Semicolon (;) used as separator?
                $semicolon = strpos($email, ';', $at);
                if ($semicolon !== false && $at < $semicolon) {
                    // Multiple addresses, keep the first.
                    $email = trim(substr($email, 0, $semicolon));
                }
            }

            // Display name used in single remaining address?
            if (preg_match('/^(.+?)<([^>]+)>$/', $email, $matches)) {
                $email = trim($matches[2]);
            }
            $acumulusObject->email = $email;
        }
    }
}
