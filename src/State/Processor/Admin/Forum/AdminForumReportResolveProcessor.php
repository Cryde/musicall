<?php

declare(strict_types=1);

namespace App\State\Processor\Admin\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Forum\ForumPostReport;
use App\Entity\User;
use App\Repository\Forum\ForumPostReportRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class AdminForumReportResolveProcessor implements ProcessorInterface
{
    public function __construct(
        private ForumPostReportRepository $forumPostReportRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $report = $this->forumPostReportRepository->findOneById((string) $uriVariables['id']);
        if (!$report instanceof ForumPostReport) {
            throw new NotFoundHttpException('Signalement inexistant');
        }

        if ($report->isResolved()) {
            throw new ConflictHttpException('Ce signalement est déjà résolu');
        }

        $moderator = $this->security->getUser();
        if (!$moderator instanceof User) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }

        $report->resolvedDatetime = new DateTime();
        $report->resolvedBy = $moderator;

        $this->entityManager->flush();

        return null;
    }
}
