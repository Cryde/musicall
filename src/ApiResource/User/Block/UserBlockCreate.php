<?php declare(strict_types=1);

namespace App\ApiResource\User\Block;

use Symfony\Component\Validator\Constraints as Assert;

class UserBlockCreate
{
    #[Assert\NotBlank(message: 'user.block.user_required')]
    #[Assert\Length(max: 36, maxMessage: 'common.invalid_identifier')]
    public string $userId = '';
}
