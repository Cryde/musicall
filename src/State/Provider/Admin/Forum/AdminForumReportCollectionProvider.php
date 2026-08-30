<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Forum\AdminForumReport;
use App\ApiResource\Forum\ForumPostResource;
use App\Entity\Forum\ForumPostReport;
use App\Repository\Forum\ForumPostReportRepository;
use App\Repository\Forum\ForumPostRepository;
use App\Service\Forum\ForumPostExcerpt;

/**
 * @implements ProviderInterface<AdminForumReport>
 */
readonly class AdminForumReportCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ForumPostReportRepository $forumPostReportRepository,
        private ForumPostRepository $forumPostRepository,
        private ForumPostExcerpt $forumPostExcerpt,
    ) {
    }

    /**
     * @return AdminForumReport[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            $this->buildResource(...),
            $this->forumPostReportRepository->findPending(),
        );
    }

    private function buildResource(ForumPostReport $report): AdminForumReport
    {
        $post = $report->post;

        $resource = new AdminForumReport();
        $resource->id = (string) $report->id;
        $resource->reason = $report->reason;
        $resource->creationDatetime = $report->creationDatetime;
        $resource->reporterUsername = $report->reporter->username;
        $resource->postId = (string) $post->id;
        $resource->postExcerpt = $this->forumPostExcerpt->create($post->content);
        $resource->postAuthorUsername = $post->creator->username;
        $resource->topicSlug = $post->topic->slug;
        $resource->topicTitle = $post->topic->title;
        $resource->topicPage = (int) ceil(
            $this->forumPostRepository->findPositionInTopic($post) / ForumPostResource::POSTS_PER_PAGE
        );

        return $resource;
    }
}
