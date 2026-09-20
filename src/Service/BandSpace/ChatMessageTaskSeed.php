<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\ApiResource\BandSpace\Task\TaskCreate;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Repository\Message\MessageAttachmentRepository;
use App\Repository\Message\MessageMentionRepository;
use App\Service\Message\MessagePlainTextExtractor;

/**
 * What a chat message is worth as a task, before anything is written (#979).
 *
 * Built from the **stored** text, never from what the API sends the client: `content` there is
 * sanitized and has its mentions already turned into spans, so a title seeded from it would have
 * markup in it, and `editable_content`, which is the stored shape, only ever reaches the message's
 * author while anybody may turn a message into a task. The plain text therefore has to be worked out
 * here, on the entity.
 */
readonly class ChatMessageTaskSeed
{
    /**
     * The `title` column holds 255, so that is the ceiling and not the target: a task title is the
     * label on a board card, and 120 characters is roughly what one can show. Nothing is lost by
     * cutting there, the whole message goes into the description.
     */
    public const int MAX_TITLE_LENGTH = 120;

    /** Says the title is an excerpt rather than the whole of what was said. */
    private const string ELLIPSIS = '…';

    /** A message made only of markup sanitizes down to nothing, and a task still needs a name. */
    private const string EMPTY_TITLE_FALLBACK = 'Message de la discussion';

    public function __construct(
        private MessagePlainTextExtractor $plainTextExtractor,
        private ChatMentionRenderer $chatMentionRenderer,
        private MessageMentionRepository $messageMentionRepository,
        private MessageAttachmentRepository $messageAttachmentRepository,
    ) {
    }

    public function fromMessage(Message $message): TaskCreate
    {
        $messageId = (string) $message->id;
        // Resolved so a task reads « @batteur ramène le câble » rather than « @[3f2a...] ».
        $content = $this->chatMentionRenderer->renderPlain(
            $message->content,
            $this->messageMentionRepository->findUsernamesByMessageIds([$messageId])[$messageId] ?? [],
        );

        // A message can carry only cards since #971, and then its cards are all it says. Read before
        // the new task's own card is written, so the task never lists itself.
        $labels = array_map(
            static fn (array $attachment): string => $attachment['label'],
            $this->messageAttachmentRepository->findByMessageIds([$messageId])[$messageId] ?? [],
        );
        $oneLine = $this->plainTextExtractor->extractOneLine($content);
        $fullText = $this->plainTextExtractor->extract($content);

        $input = new TaskCreate();
        $input->title = $this->toTitle($oneLine !== '' ? $oneLine : ($labels[0] ?? ''));
        $input->description = $this->toDescription(
            $fullText !== '' ? $fullText : $this->toAttachmentList($labels),
            $message->author,
        );

        return $input;
    }

    /**
     * Only for a message with no text: when there is text it already says what the cards are about,
     * and a message with text seeds exactly the task it did before cards could stand alone.
     *
     * @param list<string> $labels the snapshotted labels, in the order the cards are shown
     */
    private function toAttachmentList(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        $heading = count($labels) === 1 ? 'Pièce jointe :' : 'Pièces jointes :';

        return $heading . "\n" . implode("\n", array_map(static fn (string $label): string => '- ' . $label, $labels));
    }

    /**
     * Cut back to the last whole word when there is a sensible one to cut back to, and marked with an
     * ellipsis either way: a truncation nothing on screen admits to reads as a member who could not
     * be bothered to finish their sentence.
     */
    private function toTitle(string $oneLine): string
    {
        if ($oneLine === '') {
            return self::EMPTY_TITLE_FALLBACK;
        }

        if (mb_strlen($oneLine) <= self::MAX_TITLE_LENGTH) {
            return $oneLine;
        }

        $cut = mb_substr($oneLine, 0, self::MAX_TITLE_LENGTH);
        $lastSpace = mb_strrpos($cut, ' ');
        // Only past half the budget: a single very long word must not leave a three letter title.
        if ($lastSpace !== false && $lastSpace > intdiv(self::MAX_TITLE_LENGTH, 2)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut) . self::ELLIPSIS;
    }

    /**
     * The author is named here because they are usually not the member clicking: « ramène le câble »
     * is an instruction whose owner matters, and the title has no room for it.
     */
    private function toDescription(string $fullText, User $author): string
    {
        return sprintf(
            "Message de %s dans la discussion du groupe :\n\n%s",
            $author->isDeleted() ? User::DELETED_DISPLAY_NAME : $author->username,
            $fullText,
        );
    }
}
