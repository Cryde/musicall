<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Chat\ChatMessageWindow;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\ChatMessageBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ChatMessageWindow>
 */
readonly class ChatMessageWindowProvider implements ProviderInterface
{
    private const array ANCHORS = ['around', 'before', 'after'];

    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageThreadRepository $messageThreadRepository,
        private MessageRepository $messageRepository,
        private ChatMessageBuilder $chatMessageBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ChatMessageWindow
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        [$bandSpace, $membership] = $this->memberChecker->checkMember($bandSpaceId, $user);

        $channel = $this->messageThreadRepository->findChannelForBandSpace($bandSpace);
        if (!$channel instanceof MessageThread) {
            throw new NotFoundHttpException('Ce Band Space n\'a pas de conversation');
        }

        // Already a uuid when present: the operation's Assert\Uuid refused anything else with a 422.
        $filters = $context['filters'] ?? [];
        $given = array_filter(
            array_intersect_key($filters, array_flip(self::ANCHORS)),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        );
        if (count($given) !== 1) {
            throw new BadRequestHttpException('Indiquez un seul message de référence : around, before ou after');
        }
        $direction = (string) array_key_first($given);

        $anchor = $this->messageRepository->findOneInThread((string) $given[$direction], $channel);
        if (!$anchor instanceof Message) {
            throw new NotFoundHttpException('Message introuvable');
        }

        return match ($direction) {
            'before' => $this->window($bandSpaceId, $membership, $channel, $anchor, older: ChatMessageWindow::PAGE_SIZE, newer: 0, includeAnchor: false),
            'after' => $this->window($bandSpaceId, $membership, $channel, $anchor, older: 0, newer: ChatMessageWindow::PAGE_SIZE, includeAnchor: false),
            default => $this->window($bandSpaceId, $membership, $channel, $anchor, older: ChatMessageWindow::AROUND_EACH_SIDE, newer: ChatMessageWindow::AROUND_EACH_SIDE, includeAnchor: true),
        };
    }

    /**
     * One more row than asked for on each side that is read, which is how each side learns whether
     * the thread goes on past the window without a count of its own. A side that is not read answers
     * true, and truthfully: `before` and `after` never include the anchor, so the anchor itself is
     * there on the side they skip.
     */
    private function window(
        string $bandSpaceId,
        BandSpaceMembership $membership,
        MessageThread $channel,
        Message $anchor,
        int $older,
        int $newer,
        bool $includeAnchor,
    ): ChatMessageWindow {
        $olderRows = [];
        $hasOlder = true;
        if ($older > 0) {
            $wanted = $older + ($includeAnchor ? 1 : 0);
            $olderRows = $this->messageRepository->findOlderInThread($channel, $anchor, $wanted + 1, inclusive: $includeAnchor);
            $hasOlder = count($olderRows) > $wanted;
            $olderRows = array_reverse(array_slice($olderRows, 0, $wanted));
        }

        $newerRows = [];
        $hasNewer = true;
        if ($newer > 0) {
            $newerRows = $this->messageRepository->findNewerInThread($channel, $anchor, $newer + 1);
            $hasNewer = count($newerRows) > $newer;
            $newerRows = array_slice($newerRows, 0, $newer);
        }

        $window = new ChatMessageWindow();
        $window->bandSpaceId = $bandSpaceId;
        $window->messages = $this->chatMessageBuilder->buildFromProjection(
            [...$olderRows, ...$newerRows],
            $bandSpaceId,
            $membership,
            $channel,
        );
        $window->hasOlder = $hasOlder;
        $window->hasNewer = $hasNewer;
        $window->totalItems = $this->messageRepository->countForThread($channel);

        return $window;
    }
}
