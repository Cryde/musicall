<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceTaskActivityType;
use App\Repository\UserRepository;

/**
 * Records the @-mentions a comment write adds, and hands back the members it named for the first
 * time so the caller can notify them once its own flush has committed.
 *
 * Writing a comment and editing one mention people the same way, so both paths come through here.
 * Only what the text did not already carry counts: the members a comment already named were told
 * when that text was written, and an edit re-pinging the whole thread over a typo would be noise.
 * Handing over the content the comment held before the write is what draws that line; a creation
 * has none to hand over.
 */
readonly class TaskCommentMentionRecorder
{
    public function __construct(
        private MentionParserService $mentionParserService,
        private UserRepository $userRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
    ) {
    }

    /**
     * @return User[] the band space members this write mentions for the first time
     */
    public function recordNewMentions(
        Task $task,
        User $actor,
        string $content,
        string $previousContent = '',
    ): array {
        $newIds = array_diff($this->mentionedIds($content), $this->mentionedIds($previousContent));
        if ($newIds === []) {
            return [];
        }

        $mentionedMembers = $this->userRepository->findActiveBandSpaceMembersByIds(
            $task->bandSpace,
            array_values($newIds),
        );

        foreach ($mentionedMembers as $mentionedUser) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $task->bandSpace,
                module: BandSpaceModule::Task,
                type: BandSpaceTaskActivityType::Mention,
                resourceId: $task->id,
                actor: $actor,
                payload: [
                    'mentioned_user_id' => $mentionedUser->id,
                    'mentioned_username' => $mentionedUser->username,
                ],
            );
        }

        return $mentionedMembers;
    }

    /**
     * The mention syntax takes a uuid in either case, so the same member written two ways has to
     * compare equal here or an edit would read them as two different people.
     *
     * @return string[]
     */
    private function mentionedIds(string $content): array
    {
        return array_map(strtolower(...), $this->mentionParserService->extractMentions($content));
    }
}
