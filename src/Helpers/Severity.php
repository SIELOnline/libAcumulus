<?php
/**
 * Note: As long as we want to check for a minimal PHP version via the
 * Requirements checking process provided by the classes below, we should not
 * use PHP7 language constructs in the following classes:
 * - {@see Container}: creates instances of the below classes.
 * - {@see Requirements}: executes the checks.
 * - {@see \Siel\Acumulus\Config\ConfigUpgrade}: initiates the check.
 * - {@see \Siel\Acumulus\Helpers\Severity}: part of a failed check.
 * - {@see \Siel\Acumulus\Helpers\Message}: represents a failed check.
 * - {@see \Siel\Acumulus\Helpers\MessageCollection}: represents failed checks.
 * - {@see Log}: Logs failed checks.
 *
 * The PHP7 language constructs we suppress the warnings for:
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpMissingVisibilityInspection
 */

namespace Siel\Acumulus\Helpers;

/**
 * Defines message severity levels.
 *
 * - For a {@see Message} it defines the severity of the message.
 * - For a {@see MessageCollection} it defines the message with the highest
 *   severity.
 */
interface Severity
{
    /**
     * Unknown severity: an individual {@see Message} will always have a
     * severity, but a {@see MessageCollection} can have no messages and thus no
     * severity yet.
     */
    const Unknown = 0;
    const Log = 1;
    const Success = 2;
    const Info = 4;
    const Notice = 8;
    const Warning = 16;
    const Error = 32;
    const Exception = 64;

    const ErrorOrWorse = Severity::Error | Severity::Exception;
    const WarningOrWorse = Severity::Warning | Severity::ErrorOrWorse;
    const InfoOrWorse = Severity::Info | Severity::Notice | Severity::WarningOrWorse;

    /**
     * Combination of Log, Success, Info, Notice, Warning, Error, and Exception.
     */
    const All = 255;
    /**
     * Combination of Success, Info, Notice, Warning, Error, and Exception. The
     * so-called real messages, all but the debug ones.
     */
    const RealMessages = self::All & ~self::Log;
}
