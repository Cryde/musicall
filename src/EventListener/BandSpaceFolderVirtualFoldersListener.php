<?php declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Injects a `virtualFolders` array (synthetic Tasks/Finance grouping nodes
 * with active file counts) into the BandSpaceFolder collection envelope.
 */
#[AsEventListener(event: 'kernel.response')]
final readonly class BandSpaceFolderVirtualFoldersListener
{
    private const string ROUTE_NAME = 'api_band_space_folders_get_collection';

    /** @var array<string, string> source key => label */
    private const array SOURCE_LABELS = [
        'task' => 'Tâches',
        'finance' => 'Finances',
        'note' => 'Notes',
    ];

    public function __construct(
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $membershipRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private Security $security,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->get('_route') !== self::ROUTE_NAME) {
            return;
        }

        $response = $event->getResponse();
        if ($response->getStatusCode() !== 200) {
            return;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return;
        }

        $uriVariables = $request->attributes->get('_api_uri_variables');
        $bandSpaceId = is_array($uriVariables) ? ($uriVariables['bandSpaceId'] ?? null) : null;
        if (!is_string($bandSpaceId)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $bandSpace = $this->bandSpaceRepository->find($bandSpaceId);
        if ($bandSpace === null) {
            return;
        }
        if ($this->membershipRepository->findMembership($bandSpace, $user) === null) {
            return;
        }

        $counts = $this->attachmentRepository->countActiveByBandSpaceGroupedBySource($bandSpace);

        $virtualFolders = [];
        foreach (self::SOURCE_LABELS as $source => $label) {
            $virtualFolders[] = [
                'id' => 'virtual:' . $source,
                'name' => $label,
                'source' => $source,
                'file_count' => $counts[$source] ?? 0,
            ];
        }

        $data['virtualFolders'] = $virtualFolders;

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }

        if ($response instanceof JsonResponse) {
            $response->setJson($encoded);
        } else {
            $response->setContent($encoded);
        }
    }
}
