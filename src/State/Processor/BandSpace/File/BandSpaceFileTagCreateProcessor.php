<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileTagCreate;
use App\ApiResource\BandSpace\File\BandSpaceFileTagResource;
use App\Entity\BandSpace\BandSpaceFileTag;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileTagRepository;
use App\Service\Builder\BandSpace\File\BandSpaceFileTagBuilder;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFileTagCreate, BandSpaceFileTagResource>
 */
readonly class BandSpaceFileTagCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileTagRepository $tagRepository,
        private BandSpaceFileTagBuilder $tagBuilder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileTagResource
    {
        /** @var BandSpaceFileTagCreate $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $name = trim($data->name);
        if ($this->tagRepository->nameExists($bandSpace, $name)) {
            throw new UnprocessableEntityHttpException('Un tag avec ce nom existe déjà dans ce Band Space');
        }

        $tag = new BandSpaceFileTag();
        $tag->bandSpace = $bandSpace;
        $tag->name = $name;
        $tag->colorHex = $data->colorHex;

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $this->tagBuilder->buildItem($tag, 0);
    }
}
