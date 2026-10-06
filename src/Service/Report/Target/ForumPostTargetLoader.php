<?php declare(strict_types=1);

namespace App\Service\Report\Target;

use App\ApiResource\Forum\ForumPostResource;
use App\Entity\Forum\ForumPost;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\Forum\ForumPostRepository;
use App\Service\Report\ReportTarget;
use Ramsey\Uuid\Uuid;

/** The forum is public, so any existing post can be reported. Replaces #903. */
readonly class ForumPostTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(private ForumPostRepository $postRepository)
    {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::ForumPost;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        $post = Uuid::isValid($id) ? $this->postRepository->find($id) : null;
        if (!$post instanceof ForumPost) {
            return null;
        }

        return new ReportTarget($post->creator, ReportTarget::text($post->content), [
            'topic_slug' => $post->topic->slug,
            'topic_title' => $post->topic->title,
            // Where the post sits, since a topic is paginated: the moderator's link lands on it.
            'topic_page' => (int) ceil($this->postRepository->findPositionInTopic($post) / ForumPostResource::POSTS_PER_PAGE),
        ]);
    }
}
