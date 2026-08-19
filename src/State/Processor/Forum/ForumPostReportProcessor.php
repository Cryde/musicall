<?php

declare(strict_types=1);

namespace App\State\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Forum\ForumPostReportInput;
use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumPostReport;
use App\Entity\User;
use App\Repository\Forum\ForumPostReportRepository;
use App\Repository\Forum\ForumPostRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<ForumPostReportInput, null>
 */
readonly class ForumPostReportProcessor implements ProcessorInterface
{
    public function __construct(
        private ForumPostRepository $forumPostRepository,
        private ForumPostReportRepository $forumPostReportRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $post = $this->forumPostRepository->findOneById((string) $uriVariables['id']);
        if (!$post instanceof ForumPost) {
            throw new NotFoundHttpException('Message de forum inexistant');
        }

        $reporter = $this->security->getUser();
        if (!$reporter instanceof User) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }

        if ($this->forumPostReportRepository->findOneByPostAndReporter($post, $reporter) instanceof ForumPostReport) {
            throw new ConflictHttpException('Vous avez déjà signalé ce message');
        }

        $report = new ForumPostReport();
        $report->post = $post;
        $report->reporter = $reporter;
        $report->reason = trim($data->reason);

        $this->entityManager->persist($report);

        // The check above is what produces a readable error; this is what makes the rule true. Two
        // concurrent reports both pass that check, and only the unique index stops the second insert.
        // Uncaught, the violation is not in api_platform.yaml's exception_to_status and surfaces as a
        // 500, so a double-clicked button answers with a crash instead of the conflict it already knows
        // how to describe.
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Vous avez déjà signalé ce message');
        }

        return null;
    }
}
