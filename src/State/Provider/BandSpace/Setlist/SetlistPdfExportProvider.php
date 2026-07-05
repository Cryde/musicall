<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Setlist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\BandSpace\Setlist;
use App\Entity\User;
use App\Repository\BandSpace\SetlistRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Setlist\SetlistPdfOptionsBuilder;
use App\Service\Setlist\SetlistPdfRenderer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @implements ProviderInterface<Response>
 */
readonly class SetlistPdfExportProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private SetlistRepository $setlistRepository,
        private SetlistPdfRenderer $pdfRenderer,
        private SetlistPdfOptionsBuilder $optionsBuilder,
        private Security $security,
        private RequestStack $requestStack,
        private SluggerInterface $slugger,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        // Archived setlists are intentionally still exportable, matching the
        // policy of GET /setlists/{id} (archived setlists stay readable for
        // restore/audit flows).
        $setlist = $this->setlistRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$setlist instanceof Setlist) {
            throw new NotFoundHttpException('Setlist introuvable');
        }

        $options = $this->optionsBuilder->fromRequest($this->requestStack->getCurrentRequest());
        $stats = $this->setlistRepository->durationStats($setlist);

        $pdfBinary = $this->pdfRenderer->render($setlist, $options, $stats['total'], $stats['missing']);

        // makeDisposition() rejects "/" and "\" in the filename and requires an
        // ASCII-only fallback, so sanitise the display name and derive an ASCII
        // slug. Modern browsers still get the accented name via RFC 5987 filename*.
        $displayFilename = str_replace(['/', '\\'], '-', $setlist->name) . '.pdf';
        $asciiSlug = $this->slugger->slug($setlist->name)->toString();
        $fallbackFilename = ('' !== $asciiSlug ? $asciiSlug : 'setlist') . '.pdf';

        $response = new Response($pdfBinary);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $displayFilename, $fallbackFilename),
        );

        return $response;
    }
}
