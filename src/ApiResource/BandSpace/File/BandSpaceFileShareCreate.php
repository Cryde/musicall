<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\BandSpaceFileShareCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'BandSpaceFileShareCreate',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{fileId}/shares',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'fileId' => new Link(fromClass: self::class, identifiers: ['fileId']),
            ],
            openapi: new Operation(tags: ['Band Space File Share']),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileShareCreated::class,
            name: 'api_band_space_file_shares_post',
            processor: BandSpaceFileShareCreateProcessor::class,
        ),
    ],
)]
class BandSpaceFileShareCreate
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $fileId;

    #[Assert\NotBlank(message: 'La date d\'expiration est obligatoire')]
    public ?string $expiryDatetime = null;

    #[Assert\Length(min: 4, max: 255, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères')]
    public ?string $password = null;
}
