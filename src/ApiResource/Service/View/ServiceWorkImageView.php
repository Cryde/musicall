<?php

declare(strict_types=1);

namespace App\ApiResource\Service\View;

/** An image of a work (#1407): `url` streams it to whoever may read the service's page. */
class ServiceWorkImageView
{
    public string $id;

    public string $url;
}
