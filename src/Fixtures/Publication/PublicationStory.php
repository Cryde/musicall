<?php declare(strict_types=1);

namespace App\Fixtures\Publication;

use App\Fixtures\Factory\Comment\CommentFactory;
use App\Fixtures\Factory\Comment\CommentThreadFactory;
use App\Fixtures\Factory\Metric\VoteCacheFactory;
use App\Fixtures\Factory\Metric\VoteFactory;
use App\Fixtures\Factory\Publication\PublicationFactory;
use App\Fixtures\User\UserStory;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class PublicationStory extends Story
{
    public function build(): void
    {
        $publications = PublicationFactory::new()
            ->with(
                static function () {
                    $commentNumber = random_int(0, 5);
                    $thread = CommentThreadFactory::new(['commentNumber' => $commentNumber])->create();
                    CommentFactory::new([
                        'thread' => $thread,
                        'author' => UserStory::getRandom(UserStory::POOL_USERS),
                    ])->many($commentNumber)->create();

                    return [
                        'subCategory' => PublicationCategoryStory::getRandom(PublicationCategoryStory::WRITEABLE_CATEGORIES),
                        'author'      => UserStory::getRandom(UserStory::POOL_USERS),
                        'thread'      => $thread,
                    ];
                }
            )
            ->many(200)
            ->applyStateMethod('asBaseTextPublicationOnline')->create();

        $this->createVotes($publications);
    }

    /** @param list<mixed> $publications */
    private function createVotes(array $publications): void
    {
        $users = UserStory::getPool(UserStory::POOL_USERS);

        foreach ($users as $user) {
            $voteCount = random_int(3, 15);
            $votedPublications = (array) array_rand($publications, min($voteCount, count($publications)));

            foreach ($votedPublications as $index) {
                $publication = $publications[$index]->_real();
                $voteCache = $publication->getVoteCache();
                if (!$voteCache) {
                    $voteCache = VoteCacheFactory::new()->create()->_real();
                    $publication->setVoteCache($voteCache);
                }

                $value = random_int(0, 1) === 1 ? 1 : -1;
                VoteFactory::new([
                    'voteCache' => $voteCache,
                    'user' => $user,
                    'value' => $value,
                    'entityType' => 'app_publication',
                    'entityId' => (string) $publication->getId(),
                ])->create();

                if ($value === 1) {
                    $voteCache->setUpvoteCount($voteCache->getUpvoteCount() + 1);
                } else {
                    $voteCache->setDownvoteCount($voteCache->getDownvoteCount() + 1);
                }
            }
        }
    }
}
