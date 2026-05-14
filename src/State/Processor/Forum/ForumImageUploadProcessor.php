<?php

declare(strict_types=1);

namespace App\State\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Forum\ForumImageUpload;
use App\ApiResource\Forum\ForumImageUploadOutput;
use App\Entity\Image\ForumImage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\SecurityBundle\Security;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * @implements ProcessorInterface<ForumImageUpload, ForumImageUploadOutput>
 */
readonly class ForumImageUploadProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UploaderHelper         $uploaderHelper,
        private CacheManager           $cacheManager,
        private Security               $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ForumImageUploadOutput
    {
        /** @var ForumImageUpload $data */
        /** @var User $user */
        $user = $this->security->getUser();

        $forumImage = new ForumImage();
        $forumImage->setImageFile($data->imageFile);
        $forumImage->creator = $user;

        $this->entityManager->persist($forumImage);
        $this->entityManager->flush();

        $path = $this->uploaderHelper->asset($forumImage, 'imageFile');

        $response = new ForumImageUploadOutput();
        $response->uri = $path ? $this->cacheManager->getBrowserPath($path, 'publication_image_filter') : '';

        return $response;
    }
}
