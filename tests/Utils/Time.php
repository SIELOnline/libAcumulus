<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Utils;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Time contains date and time related functionalities for library and shop tests.
 */
trait Time
{
    /**
     * Returns a timing string.
     *
     * @param string $location
     *   Will be printed along with the tine, to indicate where in the code the timing was
     *   taken.
     * @param bool $doEcho
     *   Whether the resulting string should also be echoed to the output.
     *
     * @return string
     *   The time (with microseconds) and location.
     */
    protected static function eTime(string $location = '', bool $doEcho = true): string
    {
        $line = self::getTime() . ' ' . $location . PHP_EOL;
        if ($doEcho) {
            echo $line;
        }
        return $line;
    }

    /**
     * Returns the current time with microseconds.
     */
    protected static function getTime(): string
    {
        return (new DateTimeImmutable())->format('H:i:s.u');
    }

    protected static function getDiffInSeconds(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $interval = $from->diff($to);
        return (int) (new DateTimeImmutable('@0'))->add($interval)->format('U');
    }

}
