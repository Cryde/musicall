<?php declare(strict_types=1);

namespace App\State\Processor\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Gallery\UserGalleryCreate;
use App\Entity\Gallery;
use App\Entity\User;
use App\Service\Builder\User\Gallery\UserGalleryBuilder;
use App\Service\Publication\GallerySlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<UserGalleryCreate, mixed>
 */
readonly class UserGalleryCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private UserGalleryBuilder $userGalleryBuilder,
        private GallerySlug $gallerySlug,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var UserGalleryCreate $data */
        /** @var User $user */
        $user = $this->security->getUser();

        $gallery = new Gallery();
        $gallery->title = $data->title;
        $gallery->author = $user;
        $gallery->status = Gallery::STATUS_DRAFT;
        $gallery->slug = $this->gallerySlug->create($data->title . ' ' .uniqid('', true));

        $this->entityManager->persist($gallery);
        $this->entityManager->flush();

        return $this->userGalleryBuilder->buildFromEntity($gallery);
    }
}
