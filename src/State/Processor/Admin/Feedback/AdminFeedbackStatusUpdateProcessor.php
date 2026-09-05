<?php declare(strict_types=1);

namespace App\State\Processor\Admin\Feedback;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Admin\Feedback\AdminFeedbackStatusUpdate;
use App\Entity\Feedback\Feedback;
use App\Enum\Feedback\FeedbackStatus;
use App\Repository\Feedback\FeedbackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<AdminFeedbackStatusUpdate, null>
 */
readonly class AdminFeedbackStatusUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FeedbackRepository $feedbackRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $feedback = $this->feedbackRepository->findOneById((string) $uriVariables['id']);
        if (!$feedback instanceof Feedback) {
            throw new NotFoundHttpException('Retour introuvable');
        }

        // Idempotent on purpose: setting the status it already has is a no-op rather than a 400, so a
        // double click in the triage table cannot fail.
        $feedback->status = FeedbackStatus::from($data->status);
        $this->entityManager->flush();

        return null;
    }
}
