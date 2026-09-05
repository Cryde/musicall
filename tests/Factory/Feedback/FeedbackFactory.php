<?php

declare(strict_types=1);

namespace App\Tests\Factory\Feedback;

use App\Entity\Feedback\Feedback;
use App\Enum\Feedback\FeedbackModule;
use App\Enum\Feedback\FeedbackStatus;
use App\Enum\Feedback\FeedbackType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Feedback>
 */
final class FeedbackFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'type' => FeedbackType::Bug,
            'module' => FeedbackModule::File,
            'message' => 'Le bouton de téléversement ne répond pas.',
            'pageUrl' => '/band/space-id/fichiers',
            'status' => FeedbackStatus::New,
            // Pinned rather than faked: the admin list orders on it, and faker ties have flipped
            // sort order in CI before.
            'creationDatetime' => new \DateTime('2026-09-01 10:00:00'),
        ];
    }

    public function asTriaged(): static
    {
        return $this->with(['status' => FeedbackStatus::Done]);
    }

    public static function class(): string
    {
        return Feedback::class;
    }
}
