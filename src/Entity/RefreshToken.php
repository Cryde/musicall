<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * The index is on this class because the bundle maps `family` on a mapped superclass, which cannot
 * carry one. Every family lookup reads that column: a token issued in place of another inherits the
 * family of the one it replaced, so a login and each refresh descending from it share one value.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(name: 'idx_refresh_tokens_family', fields: ['family'])]
class RefreshToken extends BaseRefreshToken
{
}
