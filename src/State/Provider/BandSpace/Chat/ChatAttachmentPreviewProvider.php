<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Chat\ChatAttachmentPreview;
use App\Entity\User;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Message\MessageAttachmentResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ChatAttachmentPreview>
 */
readonly class ChatAttachmentPreviewProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageAttachmentResolver $messageAttachmentResolver,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ChatAttachmentPreview
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        $identifier = (string) $uriVariables['id'];
        [, $membership] = $this->memberChecker->checkMember($bandSpaceId, $user);

        // The same resolution the message's own validation runs, so the chip appears exactly when the
        // send would accept it: another space's object, one this member cannot see, or one that no
        // longer exists all read as not found.
        $target = $this->messageAttachmentResolver->resolveIdentifiers([$identifier], $bandSpaceId, $membership)[0] ?? null;
        if ($target === null) {
            throw new NotFoundHttpException('Élément introuvable');
        }

        $preview = new ChatAttachmentPreview();
        $preview->id = $target['type']->value . '-' . $target['targetId'];
        $preview->bandSpaceId = $bandSpaceId;
        $preview->type = $target['type']->value;
        $preview->resourceId = $target['targetId'];
        $preview->title = $target['label'];

        return $preview;
    }
}
