<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Dashboard;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Dashboard\ContentOverviewMetrics;
use App\Repository\Forum\ForumPostRepository;
use App\Repository\Forum\ForumTopicRepository;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Repository\PublicationRepository;

/**
 * @implements ProviderInterface<ContentOverviewMetrics>
 */
readonly class ContentOverviewMetricsProvider implements ProviderInterface
{
    public function __construct(
        private PublicationRepository $publicationRepository,
        private ForumTopicRepository $forumTopicRepository,
        private ForumPostRepository $forumPostRepository,
        private MusicianAnnounceRepository $musicianAnnounceRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ContentOverviewMetrics
    {
        $from = new \DateTimeImmutable($context['filters']['from']);
        // +1 day so the end date is inclusive
        $to = (new \DateTimeImmutable($context['filters']['to']))->modify('+1 day');

        $result = new ContentOverviewMetrics();
        $result->from = $context['filters']['from'];
        $result->to = $context['filters']['to'];
        $result->publicationsByType = $this->publicationRepository->countBySubCategoryTypeBetween($from, $to);
        $result->topContent = $this->publicationRepository->findTopPublicationsByViewsBetween($from, $to);
        $result->publicationsByFormat = $this->publicationRepository->countByFormatBetween($from, $to);
        $result->forumTopicsCount = $this->forumTopicRepository->countBetween($from, $to);
        $result->forumPostsCount = $this->forumPostRepository->countBetween($from, $to);
        $result->announcesByType = $this->musicianAnnounceRepository->countByTypeBetween($from, $to);
        $result->topInstruments = $this->musicianAnnounceRepository->findTopInstrumentsBetween($from, $to);
        $result->topStyles = $this->musicianAnnounceRepository->findTopStylesBetween($from, $to);

        return $result;
    }
}
