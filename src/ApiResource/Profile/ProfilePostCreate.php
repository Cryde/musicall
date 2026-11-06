<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Serializer\Encoder\MultipartDecoder;
use App\Service\BandSpace\Chat\ChatImageConverter;
use App\State\Processor\Profile\ProfilePostCreateProcessor;
use App\Validator\Musician\SupportedMediaUrl;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/** A new post on your own wall (#1316): a text, up to 4 photos or one media link, never photos and a link together. */
#[Post(
    uriTemplate: '/user/profile-posts',
    inputFormats: [
        'jsonld' => ['application/ld+json'],
        'json' => ['application/json'],
        'multipart' => ['multipart/form-data'],
    ],
    openapi: new Operation(tags: ['Profile Wall']),
    security: 'is_granted("ROLE_USER")',
    denormalizationContext: [MultipartDecoder::RAW_FIELDS => ['content', 'media_url']],
    output: ProfilePostResource::class,
    name: 'api_profile_posts_post',
    processor: ProfilePostCreateProcessor::class,
)]
class ProfilePostCreate
{
    public const int MAX_PHOTOS = 4;

    #[Assert\When(
        expression: 'this.photos == [] and this.mediaUrl in [null, ""]',
        constraints: [new Assert\NotBlank(message: 'profile.wall.post_empty', normalizer: 'trim')],
    )]
    #[Assert\Length(max: 2000, maxMessage: 'profile.wall.content_too_long')]
    public string $content = '';

    #[Assert\When(
        expression: 'this.photos != []',
        constraints: [new Assert\Blank(message: 'profile.wall.photos_or_link')],
    )]
    #[Assert\Length(max: 500, maxMessage: 'profile.wall.link_too_long')]
    // The parsers match a host anywhere in the string: the scheme is checked here, as the link is stored as sent.
    #[Assert\Url(message: 'profile.wall.link_invalid', requireTld: true)]
    #[SupportedMediaUrl]
    public ?string $mediaUrl = null;

    /**
     * Converted like the chat images: EXIF and its location stripped, WebP, 1600 px at most.
     *
     * @var File[]
     */
    #[Assert\Sequentially([
        new Assert\Count(max: self::MAX_PHOTOS, maxMessage: 'profile.wall.too_many_photos'),
        new Assert\All([
            new Assert\Image(
                maxSize: ChatImageConverter::MAX_UPLOAD_SIZE,
                mimeTypes: ChatImageConverter::ACCEPTED_MIME_TYPES,
                maxPixels: ChatImageConverter::MAX_PIXELS,
                maxSizeMessage: 'band_space.chat.image_too_large',
                mimeTypesMessage: 'band_space.chat.image_type_invalid',
                maxPixelsMessage: 'band_space.chat.image_too_many_pixels',
            ),
        ]),
    ])]
    public array $photos = [];
}
