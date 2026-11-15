<?php

declare(strict_types=1);

namespace App\ApiResource\Service;

use App\Enum\Service\ServiceKind;
use App\Validator\Service\ServiceKindNotOffered;
use Symfony\Component\Validator\Constraints as Assert;

class MyServiceInput
{
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'service.kind.required'),
        new Assert\Choice(callback: [ServiceKind::class, 'values'], message: 'service.kind.invalid'),
        new ServiceKindNotOffered(),
    ])]
    public ?string $kind = null;
}
