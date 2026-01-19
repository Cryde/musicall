<?php declare(strict_types=1);

namespace App\State\Processor\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Publication\UserPublicationUploadImage;
use App\ApiResource\User\Publication\UserPublicationUploadImageOutput;
use App\Entity\Image\PublicationImage;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * @implements ProcessorInterface<UserPublicationUploadImage, UserPublicationUploadImageOutput>
 */
readonly class UserPublicationUploadImageProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicationRepository $publicationRepository,
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserPublicationUploadImageOutput
    {
        /** @var UserPublicationUploadImage $data */
        /** @var Publication $publication */
        $publication = $this->publicationRepository->find($uriVariables['id']);

        $publicationImage = new PublicationImage();
        $publicationImage->setImageFile($data->imageFile);
        $publicationImage->setPublication($publication);

        $this->entityManager->persist($publicationImage);
        $this->entityManager->flush();

        $path = $this->uploaderHelper->asset($publicationImage, 'imageFile');

        $response = new UserPublicationUploadImageOutput();
        $response->uri = $path ? $this->cacheManager->getBrowserPath($path, 'publication_image_filter') : '';

        return $response;
    }
}
