<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Work;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Service\View\ServiceWorkView;
use App\State\Processor\Service\Work\MyServiceWorkCreateProcessor;
use App\State\Processor\Service\Work\MyServiceWorkDeleteProcessor;
use App\State\Processor\Service\Work\MyServiceWorkUpdateProcessor;
use App\State\Provider\Service\Work\MyServiceWorkProvider;
use App\Validator\Musician\SupportedMediaUrl;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A work of the member's own service (#1407). The PATCH has URI variables, so the provider fills every writable field
 * and one left out keeps its value (#1327).
 */
#[ApiResource(
    shortName: 'MyServiceWork',
    operations: [
        new Post(
            uriTemplate: '/user/services/{serviceId}/works',
            uriVariables: ['serviceId' => new Link(fromClass: self::class, identifiers: ['serviceId'])],
            read: false,
            name: 'api_user_services_works_create',
            processor: MyServiceWorkCreateProcessor::class,
        ),
        // Not served, only the address a create answers with.
        new NotExposed(),
        new Patch(
            name: 'api_user_services_works_patch',
            provider: MyServiceWorkProvider::class,
            processor: MyServiceWorkUpdateProcessor::class,
        ),
        new Delete(
            read: false,
            name: 'api_user_services_works_delete',
            processor: MyServiceWorkDeleteProcessor::class,
        ),
    ],
    uriTemplate: '/user/services/{serviceId}/works/{id}',
    uriVariables: [
        'serviceId' => new Link(fromClass: self::class, identifiers: ['serviceId']),
        'id' => new Link(fromClass: self::class, identifiers: ['id']),
    ],
    requirements: ['serviceId' => '[0-9a-fA-F\-]{36}', 'id' => '[0-9a-fA-F\-]{36}'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    security: 'is_granted("ROLE_USER")',
)]
class MyServiceWork extends ServiceWorkView
{
    #[ApiProperty(identifier: true, readable: false, writable: false)]
    public string $serviceId;

    #[ApiProperty(identifier: true, writable: false)]
    public string $id;

    #[Assert\NotBlank(message: 'service.work.title_required', normalizer: 'trim')]
    #[Assert\Length(max: 120)]
    public string $title = '';

    #[Assert\Length(max: 160)]
    public ?string $credit = null;

    #[Assert\Range(notInRangeMessage: 'service.work.year_invalid', min: 1900, max: 2100)]
    public ?int $year = null;

    /** `https` and `http` only: the page opens it with `rel="nofollow ugc noopener"`. */
    #[Assert\Length(max: 500)]
    #[Assert\Url(message: 'service.work.link_invalid', protocols: ['http', 'https'], requireTld: true)]
    public ?string $linkUrl = null;

    /** Read back as `media`. */
    #[ApiProperty(readable: false)]
    #[Assert\Length(max: 500)]
    #[SupportedMediaUrl]
    public ?string $mediaUrl = null;
}
