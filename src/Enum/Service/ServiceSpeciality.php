<?php

declare(strict_types=1);

namespace App\Enum\Service;

use Symfony\Contracts\Translation\TranslatableInterface;

/** What a kind's specialities share, its main search filter: a stored value and a name (#1410, #1411). */
interface ServiceSpeciality extends \BackedEnum, TranslatableInterface
{
}
