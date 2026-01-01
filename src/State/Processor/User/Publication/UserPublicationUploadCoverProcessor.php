<?php declare(strict_types=1);

namespace App\State\Processor\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Publication\UserPublicationUploadCover;
use App\ApiResource\User\Publication\UserPublicationUploadCoverOutput;
use App\Entity\Image\PublicationCover;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * @implements ProcessorInterface<UserPublicationUploadCover, UserPublicationUploadCoverOutput>
 */
readonly class UserPublicationUploadCoverProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicationRepository $publicationRepository,
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserPublicationUploadCoverOutput
    {
        /** @var UserPublicationUploadCover $data */
        $publication = $this->publicationRepository->find($uriVariables['id']);

        // Remove old cover if exists (flush first to avoid unique constraint violation)
        $oldCover = $publication->getCover();
        if ($oldCover) {
            $publication->setCover(null);
            $this->entityManager->remove($oldCover);
            $this->entityManager->flush();
        }

        $publicationCover = new PublicationCover();
        $publicationCover->setImageFile($data->imageFile);
        $publicationCover->setPublication($publication);

        $publication->setCover($publicationCover);

        $this->entityManager->persist($publicationCover);
        $this->entityManager->flush();

        $path = $this->uploaderHelper->asset($publicationCover, 'imageFile');

        $response = new UserPublicationUploadCoverOutput();
        $response->uri = $path ? $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300') : '';

        return $response;
    }
}
