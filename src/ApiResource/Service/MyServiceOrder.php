<?php

declare(strict_types=1);

namespace App\ApiResource\Service;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Service\MyServiceReorderProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** The new order of a member's services, which their profile follows (#1415): every id, each once. */
#[Post(
    uriTemplate: '/user/services/reorder',
    openapi: new Operation(tags: ['Service']),
    status: 204,
    security: 'is_granted("ROLE_USER")',
    output: false,
    read: false,
    name: 'api_user_services_reorder',
    processor: MyServiceReorderProcessor::class,
)]
class MyServiceOrder
{
    /** A member has one service per kind: far fewer than this, which only refuses an oversized body. */
    private const int MAX_IDS = 20;

    /** @var array<string> */
    #[Assert\Count(max: self::MAX_IDS)]
    #[Assert\All([new Assert\Type('string')])]
    public array $ids = [];
}
