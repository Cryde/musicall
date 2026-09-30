<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Entity\User\UserProfile;
use App\Service\Geocoding\PhotonGeocoder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
    // Photon is a shared public service: one request a second keeps the backfill a polite client.
    private const int DELAY_MICROSECONDS = 1_000_000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PhotonGeocoder $geocoder,
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
                // The picker's own lookup, so a backfilled location is one a member could have picked.
                $resolved[$key] = $this->geocoder->searchCities($location, 1)[0] ?? null;
            }

            $city = $resolved[$key];
            if ($city === null) {
                $io->writeln(sprintf('<comment>Not found</comment> "%s"', $location));
                continue;
            }

            $io->writeln(sprintf('"%s" -> %s (%F, %F)', $location, $city->name, $city->latitude, $city->longitude));
            ++$geocoded;
            if (!$isDryRun) {
                $profile->latitude = $city->latitude;
                $profile->longitude = $city->longitude;
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
}
