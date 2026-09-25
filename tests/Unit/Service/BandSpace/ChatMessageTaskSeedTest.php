<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Repository\Message\MessageAttachmentRepository;
use App\Repository\Message\MessageMentionRepository;
use App\Service\BandSpace\ChatMentionRenderer;
use App\Service\BandSpace\ChatMessageTaskSeed;
use App\Service\Message\MessagePlainTextExtractor;
use PHPUnit\Framework\TestCase;

/**
 * What a message is worth as a task (#979): the title it gets cut down to, and the description that
 * keeps the whole of it and says who wrote it.
 *
 * The extractor is stubbed to the identity, because the sanitizer it wraps is configured in yaml and
 * proving the real pipeline belongs to MessagePlainTextExtractorTest. What is exercised here is the
 * truncation and the wording, which is the part with a decision in it.
 */
class ChatMessageTaskSeedTest extends TestCase
{
    public function test_a_short_message_becomes_the_title_whole(): void
    {
        $input = $this->seed()->fromMessage($this->message('Faut penser à ramener le câble XLR'));

        self::assertSame('Faut penser à ramener le câble XLR', $input->title);
    }

    public function test_a_title_exactly_at_the_limit_is_left_alone(): void
    {
        $content = str_repeat('a', ChatMessageTaskSeed::MAX_TITLE_LENGTH);

        $input = $this->seed()->fromMessage($this->message($content));

        self::assertSame($content, $input->title);
    }

    public function test_a_long_message_is_cut_back_to_a_whole_word_and_marked(): void
    {
        $input = $this->seed()->fromMessage($this->message(trim(str_repeat('azerty ', 30))));

        self::assertSame(trim(str_repeat('azerty ', 17)) . '…', $input->title);
        self::assertLessThanOrEqual(ChatMessageTaskSeed::MAX_TITLE_LENGTH, mb_strlen($input->title));
    }

    public function test_one_endless_word_is_cut_where_it_falls_rather_than_to_nothing(): void
    {
        // A url pasted alone, the realistic version of this: backing off to the last space would
        // leave a two word title, so past half the budget the cut stands.
        $content = 'voir ' . str_repeat('x', 200);

        $input = $this->seed()->fromMessage($this->message($content));

        self::assertSame('voir ' . str_repeat('x', ChatMessageTaskSeed::MAX_TITLE_LENGTH - 5) . '…', $input->title);
    }

    public function test_a_message_that_sanitizes_down_to_nothing_still_names_the_task(): void
    {
        $input = $this->seed(extracted: '')->fromMessage($this->message('<b></b>'));

        self::assertSame('Message de la discussion', $input->title);
    }

    public function test_the_description_keeps_the_whole_message_and_names_its_author(): void
    {
        $content = "Faut penser à :\nle câble XLR\nles piles";

        $input = $this->seed()->fromMessage($this->message($content, 'chanteuse'));

        self::assertSame(
            "Message de chanteuse dans la discussion du groupe :\n\n" . $content,
            $input->description,
        );
    }

    public function test_a_deleted_author_is_not_named(): void
    {
        $message = $this->message('ramener le câble', 'chanteuse');
        $message->author->deletionDatetime = new \DateTimeImmutable('2026-01-01');

        $input = $this->seed()->fromMessage($message);

        self::assertStringStartsWith(
            'Message de ' . User::DELETED_DISPLAY_NAME . ' dans la discussion du groupe :',
            (string) $input->description,
        );
    }

    public function test_the_task_carries_the_defaults_of_a_board_created_one(): void
    {
        $input = $this->seed()->fromMessage($this->message('ramener le câble'));

        self::assertSame('todo', $input->status);
        self::assertSame('normal', $input->priority);
        self::assertNull($input->dueDate);
        self::assertNull($input->categoryId);
        self::assertNull($input->assigneeIds);
    }

    public function test_a_named_member_reads_as_their_name(): void
    {
        $userId = '3f2a0000-0000-4000-8000-000000000001';
        $mentionRepository = $this->createStub(MessageMentionRepository::class);
        $mentionRepository->method('findUsernamesByMessageIds')->willReturn([
            'aaaa0000-0000-4000-8000-000000000001' => [$userId => 'batteur'],
        ]);

        $message = $this->message('@[' . $userId . '] ramène le câble');
        $message->id = 'aaaa0000-0000-4000-8000-000000000001';

        $input = $this->seed(mentionRepository: $mentionRepository)->fromMessage($message);

        self::assertSame('@batteur ramène le câble', $input->title);
    }

    public function test_a_message_of_cards_only_is_named_by_its_first_card(): void
    {
        $input = $this->seed(attachmentLabels: ['contrat.pdf', 'Réparer l\'ampli'])->fromMessage($this->message(''));

        self::assertSame('contrat.pdf', $input->title);
    }

    public function test_a_message_of_cards_only_lists_them_in_the_description(): void
    {
        $input = $this->seed(attachmentLabels: ['contrat.pdf', 'Réparer l\'ampli'])->fromMessage($this->message(''));

        self::assertSame(
            "Message de batteur dans la discussion du groupe :\n\nPièces jointes :\n- contrat.pdf\n- Réparer l'ampli",
            $input->description,
        );
    }

    public function test_a_single_card_is_announced_in_the_singular(): void
    {
        $input = $this->seed(attachmentLabels: ['contrat.pdf'])->fromMessage($this->message(''));

        self::assertSame(
            "Message de batteur dans la discussion du groupe :\n\nPièce jointe :\n- contrat.pdf",
            $input->description,
        );
    }

    public function test_a_message_with_text_ignores_its_cards_entirely(): void
    {
        // Cards alongside text change nothing: the text already says what they are about.
        $input = $this->seed(attachmentLabels: ['contrat.pdf'])->fromMessage($this->message('relis le contrat'));

        self::assertSame('relis le contrat', $input->title);
        self::assertSame("Message de batteur dans la discussion du groupe :\n\nrelis le contrat", $input->description);
    }

    /**
     * @param list<string> $attachmentLabels the cards the message carries, as the repository returns them
     */
    private function seed(
        ?string $extracted = null,
        ?MessageMentionRepository $mentionRepository = null,
        array $attachmentLabels = [],
    ): ChatMessageTaskSeed {
        $extractor = $this->createStub(MessagePlainTextExtractor::class);
        // The identity, unless the case under test is "the sanitizer left nothing".
        $extractor->method('extract')->willReturnCallback(
            static fn (string $content): string => $extracted ?? $content,
        );
        $extractor->method('extractOneLine')->willReturnCallback(
            static fn (string $content): string => $extracted
                ?? trim((string) preg_replace('/\s+/u', ' ', $content)),
        );

        $attachmentRepository = $this->createStub(MessageAttachmentRepository::class);
        $attachmentRepository->method('findByMessageIds')->willReturn($attachmentLabels === [] ? [] : [
            'aaaa0000-0000-4000-8000-000000000001' => array_map(
                static fn (string $label): array => [
                    'type' => BandSpaceSearchResultType::File,
                    'targetId' => 'bbbb0000-0000-4000-8000-000000000001',
                    'label' => $label,
                ],
                $attachmentLabels,
            ),
        ]);

        return new ChatMessageTaskSeed(
            $extractor,
            new ChatMentionRenderer(),
            $mentionRepository ?? $this->createStub(MessageMentionRepository::class),
            $attachmentRepository,
        );
    }

    private function message(string $content, string $authorUsername = 'batteur'): Message
    {
        $author = new User();
        $author->username = $authorUsername;

        $message = new Message();
        $message->id = 'aaaa0000-0000-4000-8000-000000000001';
        $message->author = $author;
        $message->thread = new MessageThread();
        $message->content = $content;

        return $message;
    }
}
