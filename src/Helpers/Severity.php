<?php
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
     * Unknown severity: a {@see Message} should always have a severity, but a
     * {@see MessageCollection} can have no messages and thus no severity yet.
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
