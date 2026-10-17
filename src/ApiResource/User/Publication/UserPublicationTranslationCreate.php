<?php declare(strict_types=1);

namespace App\ApiResource\User\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Publication\UserPublicationTranslationCreateProcessor;
use App\State\Provider\User\Publication\UserPublicationTranslationCreateProvider;
use App\Validator\User\SupportedLocale;
use Symfony\Component\Validator\Constraints as Assert;

/** A new draft translating the publication `{id}`, in a language its translation group does not have yet. */
#[Post(
    uriTemplate: '/user/publications/{id}/translations',
    openapi: new Operation(tags: ['User Publications']),
    security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
    output: UserPublicationEdit::class,
    name: 'api_user_publications_translation_create',
    provider: UserPublicationTranslationCreateProvider::class,
    processor: UserPublicationTranslationCreateProcessor::class,
)]
class UserPublicationTranslationCreate
{
    /** The publication being translated. */
    #[ApiProperty(identifier: true, writable: false)]
    public int $id;

    #[Assert\NotBlank(message: 'publication.title_required')]
    #[Assert\Length(min: 3, minMessage: 'publication.title_too_short')]
    public string $title;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'publication.language_required'),
        new SupportedLocale(),
        new Assert\Expression('value not in this.takenLanguages', message: 'publication.translation.language_taken'),
    ])]
    public string $language;

    /**
     * The languages of the publication and its translations, filled by the provider.
     *
     * @var string[]
     */
    #[ApiProperty(readable: false, writable: false)]
    public array $takenLanguages = [];
}
