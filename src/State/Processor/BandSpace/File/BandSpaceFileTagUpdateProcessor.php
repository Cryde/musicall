<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileTagResource;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileTagRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\BandSpaceFileTagBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFileTagResource, BandSpaceFileTagResource>
 */
readonly class BandSpaceFileTagUpdateProcessor implements ProcessorInterface
{
    private const string COLOR_PATTERN = '/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileTagRepository $tagRepository,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileTagBuilder $tagBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileTagResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $tag = $this->tagRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$tag instanceof \App\Entity\BandSpace\BandSpaceFileTag) {
            throw new NotFoundHttpException('Tag introuvable');
        }

        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        if (array_key_exists('name', $payload)) {
            $newName = trim((string) $payload['name']);
            if ($newName === '') {
                throw new BadRequestHttpException('Le nom ne peut pas être vide');
            }
            if (mb_strtolower($newName) !== mb_strtolower($tag->name)
                && $this->tagRepository->nameExists($bandSpace, $newName, $tag)
            ) {
                throw new UnprocessableEntityHttpException('Un tag avec ce nom existe déjà dans ce Band Space');
            }
            $tag->name = $newName;
        }

        if (array_key_exists('color_hex', $payload) || array_key_exists('colorHex', $payload)) {
            $rawColor = $payload['color_hex'] ?? $payload['colorHex'] ?? null;
            if ($rawColor === null || $rawColor === '') {
                $tag->colorHex = null;
            } else {
                $rawColor = (string) $rawColor;
                if (preg_match(self::COLOR_PATTERN, $rawColor) !== 1) {
                    throw new UnprocessableEntityHttpException('La couleur doit être au format hexadécimal (#RGB ou #RRGGBB)');
                }
                $tag->colorHex = $rawColor;
            }
        }

        $this->entityManager->flush();

        $fileCounts = $this->fileRepository->countByTagIds([(string) $tag->id]);

        return $this->tagBuilder->buildItem($tag, $fileCounts[(string) $tag->id] ?? 0);
    }
}
