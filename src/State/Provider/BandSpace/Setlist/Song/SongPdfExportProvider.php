<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ProviderInterface;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Http\ContentDisposition;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\Song\SongPdfRenderer;
use App\Service\BandSpace\Song\SongSheetOptions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Response>
 */
readonly class SongPdfExportProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private SongRepository $songRepository,
        private SongPdfRenderer $pdfRenderer,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $song = $this->songRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$song instanceof Song) {
            throw new NotFoundHttpException('Chanson introuvable');
        }

        $parameters = $operation->getParameters();
        $options = new SongSheetOptions(
            showChords: $this->flag($parameters?->get('chords')?->getValue()),
            showSingers: $this->flag($parameters?->get('singers')?->getValue()),
            transpose: (int) $this->valueOf($parameters?->get('transpose')?->getValue(), '0'),
        );

        $response = new Response($this->pdfRenderer->render($song, $options));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', ContentDisposition::attachment($song->title . '.pdf'));

        return $response;
    }

    /** Both default to on: a printed song sheet is read with its chords and its singers. */
    private function flag(mixed $value): bool
    {
        return in_array($this->valueOf($value, '1'), ['1', 'true'], true);
    }

    private function valueOf(mixed $value, string $default): string
    {
        return $value instanceof ParameterNotFound || $value === null || $value === '' ? $default : (string) $value;
    }
}
