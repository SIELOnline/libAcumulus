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
     * Unknown severity: an individual {@see Message} will always have a
     * severity, but a {@see MessageCollection} can have no messages and thus no
     * severity yet.
     */
    public const Unknown = 0;
    public const Log = 1;
    public const Success = 2;
    public const Info = 4;
    public const Notice = 8;
    public const Warning = 16;
    public const Error = 32;
    public const Exception = 64;

    public const ErrorOrWorse = Severity::Error | Severity::Exception;
    public const WarningOrWorse = Severity::Warning | Severity::ErrorOrWorse;
    public const InfoOrWorse = Severity::Info | Severity::Notice | Severity::WarningOrWorse;

    /**
     * Combination of Log, Success, Info, Notice, Warning, Error, and Exception.
     */
    public const All = 255;
    /**
     * Combination of Success, Info, Notice, Warning, Error, and Exception. The
     * so-called real messages, all but the debug ones.
     */
    public const RealMessages = self::All & ~self::Log;
}
