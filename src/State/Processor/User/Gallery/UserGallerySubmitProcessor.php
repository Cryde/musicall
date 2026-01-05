<?php declare(strict_types=1);

namespace App\State\Processor\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Gallery\UserGallerySubmit;
use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Service\Builder\User\Gallery\UserGalleryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<UserGallerySubmit, mixed>
 */
readonly class UserGallerySubmitProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryRepository $galleryRepository,
        private ValidatorInterface $validator,
        private UserGalleryBuilder $userGalleryBuilder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var UserGallerySubmit $data */
        $gallery = $this->galleryRepository->find($uriVariables['id']);

        // Validate for publish
        $violations = $this->validator->validate($gallery, null, ['publish']);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }
            throw new UnprocessableEntityHttpException(implode(', ', $errors));
        }

        $gallery->setStatus(Gallery::STATUS_PENDING);
        $gallery->setUpdateDatetime(new \DateTime());

        $this->entityManager->flush();

        return $this->userGalleryBuilder->buildFromEntity($gallery);
    }
}
