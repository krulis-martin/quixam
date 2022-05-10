<?php

declare(strict_types=1);

namespace App\Helpers;

use Nette;
use DateTime;
use DateTimeZone;

class LatteDateFilter
{
    use Nette\SmartObject;

    /**
     *
     */
    public function __invoke(?DateTime $date, $format = 'j. n. Y (H:i)', $empty = '-'): string
    {
        if (!$date) {
            return $empty;
        }

        $date = clone $date;
        $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $date->format($format);
    }
}
