<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\BandSpace\TechRider;
use App\Entity\User;
use App\Http\ContentDisposition;
use App\Repository\BandSpace\TechRiderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\TechRider\TechRiderPdfRenderer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Response>
 */
readonly class TechRiderPdfExportProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderRepository $techRiderRepository,
        private TechRiderPdfRenderer $pdfRenderer,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // checkMember rather than checkMemberForWrite: reads and downloads stay open while a space is
        // pending deletion, which is when a band most needs to get its documents out.
        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        // Archived riders are intentionally still exportable, matching GET /tech_riders/{id} and the
        // setlist policy: last year's rider is a thing you send when asked what you used.
        $techRider = $this->techRiderRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$techRider instanceof TechRider) {
            throw new NotFoundHttpException('Tech rider introuvable');
        }

        $response = new Response($this->pdfRenderer->render($techRider));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', ContentDisposition::attachment($techRider->name . '.pdf'));

        return $response;
    }
}
