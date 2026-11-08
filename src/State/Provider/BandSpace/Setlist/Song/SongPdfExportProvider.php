<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Http\ContentDisposition;
use App\Repository\BandSpace\LyricsRepository;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\Song\SongPdfRenderer;
use App\Service\BandSpace\Song\SongSheetOptions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProviderInterface<Response>
 */
readonly class SongPdfExportProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private SongRepository $songRepository,
        private LyricsRepository $lyricsRepository,
        private SongPdfRenderer $pdfRenderer,
        private Security $security,
        #[Target('song_pdf')]
        private RateLimiterFactoryInterface $pdfLimiter,
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
            throw new NotFoundHttpException('band_space.song.not_found');
        }

        // Charged once the export is known to be real: an outsider or a wrong id spends nothing.
        $this->pdfLimiter->create($user->id)->consume()->ensureAccepted();

        $options = SongSheetOptions::fromParameters($operation->getParameters());
        $response = new Response($this->pdfRenderer->render($song, $this->lyricsRepository->findOneBySong($song), $options));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', ContentDisposition::attachment($song->title . '.pdf'));

        return $response;
    }
}
