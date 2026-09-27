<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Entity\User\UserProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * One shot, best effort backfill of the coordinates of the profile locations typed as free text
 * before the city picker (#1079). Self contained so it can be deleted once it has run in production.
 * The text is kept as the member wrote it; a location Photon cannot place stays without coordinates.
 */
#[AsCommand(
    name: 'app:user-profile:geocode-locations',
    description: 'Geocode the free text profile locations that have no coordinates yet'
)]
class GeocodeProfileLocationsCommand extends Command
{
    private const string PHOTON_URL = 'https://photon.komoot.io/api/';
    // The places the city picker offers, so a backfilled location matches what a member could pick.
    private const array CITY_TAGS = ['place:city', 'place:town', 'place:village', 'place:municipality'];
    // Photon is a shared public service: one request a second keeps the backfill a polite client.
    private const int DELAY_MICROSECONDS = 1_000_000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be stored without saving it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = (bool) $input->getOption('dry-run');

        /** @var UserProfile[] $profiles */
        $profiles = $this->entityManager->createQuery(
            "SELECT profile FROM App\Entity\User\UserProfile profile
             WHERE profile.location IS NOT NULL AND TRIM(profile.location) <> '' AND profile.latitude IS NULL"
        )->getResult();

        // The same town is typed by many members: ask Photon once per spelling.
        $resolved = [];
        $geocoded = 0;
        foreach ($profiles as $profile) {
            $location = trim((string) $profile->location);
            $key = mb_strtolower($location);
            if (!array_key_exists($key, $resolved)) {
                if ($resolved !== []) {
                    usleep(self::DELAY_MICROSECONDS);
                }
                $resolved[$key] = $this->findCoordinates($location);
            }

            $coordinates = $resolved[$key];
            if ($coordinates === null) {
                $io->writeln(sprintf('<comment>Not found</comment> "%s"', $location));
                continue;
            }

            $io->writeln(sprintf('"%s" -> %s (%F, %F)', $location, $coordinates['name'], $coordinates['latitude'], $coordinates['longitude']));
            ++$geocoded;
            if (!$isDryRun) {
                $profile->latitude = $coordinates['latitude'];
                $profile->longitude = $coordinates['longitude'];
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d of %d profile locations geocoded%s.',
            $geocoded,
            count($profiles),
            $isDryRun ? ' (dry run, nothing saved)' : ''
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array{name: string, latitude: float, longitude: float}|null
     */
    private function findCoordinates(string $location): ?array
    {
        // Photon wants osm_tag repeated, which an array in `query` would send as osm_tag[0].
        $tags = implode('&', array_map(static fn (string $tag): string => 'osm_tag=' . urlencode($tag), self::CITY_TAGS));
        $url = self::PHOTON_URL . '?' . http_build_query(['q' => $location, 'limit' => 1, 'lang' => 'fr']) . '&' . $tags;

        try {
            $data = $this->httpClient->request('GET', $url, ['timeout' => 5])->toArray();
        } catch (\Throwable) {
            return null;
        }

        $feature = $data['features'][0] ?? null;
        $name = $feature['properties']['name'] ?? null;
        $point = $feature['geometry']['coordinates'] ?? null;
        if (!is_string($name) || !is_array($point) || !is_numeric($point[0] ?? null) || !is_numeric($point[1] ?? null)) {
            return null;
        }

        return ['name' => $name, 'latitude' => (float) $point[1], 'longitude' => (float) $point[0]];
    }
}
