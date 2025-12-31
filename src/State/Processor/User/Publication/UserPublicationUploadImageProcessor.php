<?php declare(strict_types=1);

namespace App\State\Processor\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Publication\UserPublicationUploadImage;
use App\Entity\Image\PublicationImage;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * @implements ProcessorInterface<mixed, UserPublicationUploadImage>
 */
class UserPublicationUploadImageProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicationRepository $publicationRepository,
        private readonly RequestStack $requestStack,
        private readonly UploaderHelper $uploaderHelper,
        private readonly CacheManager $cacheManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserPublicationUploadImage
    {
        $request = $this->requestStack->getCurrentRequest();
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            throw new BadRequestHttpException('No file uploaded');
        }

        $publication = $this->publicationRepository->find($uriVariables['id']);

        $publicationImage = new PublicationImage();
        $publicationImage->setImageFile($uploadedFile);
        $publicationImage->setPublication($publication);

        $this->entityManager->persist($publicationImage);
        $this->entityManager->flush();

        $path = $this->uploaderHelper->asset($publicationImage, 'imageFile');

        $response = new UserPublicationUploadImage();
        $response->uri = $path ? $this->cacheManager->getBrowserPath($path, 'publication_image_filter') : '';

        return $response;
    }
}
