<?php declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Entity\User;
use App\Exception\PublicationNotFoundException;
use App\Repository\PublicationRepository;
use App\Service\Builder\Publication\PublicationBuilder;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class PublicationProvider implements ProviderInterface
{
    public function __construct(
        private PublicationRepository $publicationRepository,
        private ViewProcedure         $viewProcedure,
        private RequestStack          $requestStack,
        private Security              $security,
        private PublicationBuilder    $publicationBuilder
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        $publication = $this->publicationRepository->findOneBy(['slug' => $uriVariables['slug']]);
        if (!$publication) {
            throw new PublicationNotFoundException('Publication inexistante');
        }

        // Check access for non-published publications
        if ($publication->getStatus() !== Publication::STATUS_ONLINE) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            if (!$user || $publication->getAuthor()->getId() !== $user->getId()) {
                throw new AccessDeniedHttpException('Vous n\'avez pas accès à cette publication');
            }
        }

        if ($publication->getStatus() === Publication::STATUS_ONLINE) {
            /** @var User $user */
            $user = $this->security->getUser();
            $this->viewProcedure->process($publication, $this->requestStack->getCurrentRequest(), $user);
        }

        return $this->publicationBuilder->buildFromEntity($publication);
    }
}
