<?php declare(strict_types=1);

namespace App\State\Provider\Admin\Feedback;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Feedback\AdminFeedback;
use App\Entity\Feedback\Feedback;
use App\Repository\Feedback\FeedbackRepository;
use App\Service\Builder\Admin\Feedback\AdminFeedbackBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<AdminFeedback>
 */
readonly class AdminFeedbackItemProvider implements ProviderInterface
{
    public function __construct(
        private FeedbackRepository $feedbackRepository,
        private AdminFeedbackBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminFeedback
    {
        $feedback = $this->feedbackRepository->findOneById((string) $uriVariables['id']);
        if (!$feedback instanceof Feedback) {
            throw new NotFoundHttpException('Retour introuvable');
        }

        return $this->builder->buildFromEntity($feedback);
    }
}
