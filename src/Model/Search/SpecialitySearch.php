<?php

declare(strict_types=1);

namespace App\Model\Search;

use App\Entity\Attribute\Style;
use App\Enum\Service\ServiceKind;

/**
 * The search of a kind filtered by a speciality: sound engineers (#1410), photographers and videographers (#1411). The
 * shared service search, a speciality of the kind and a style.
 */
class SpecialitySearch
{
    public ServiceSearch $service;
    /** A value of the kind's specialities, already validated by the operation. */
    public ?string $speciality = null;
    public ?Style $style = null;

    public function __construct(public ServiceKind $kind)
    {
        $this->service = new ServiceSearch();
    }
}
