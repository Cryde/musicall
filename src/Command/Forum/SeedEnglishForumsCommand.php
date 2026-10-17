<?php

declare(strict_types=1);

namespace App\Command\Forum;

use App\Entity\Forum\Forum;
use App\Entity\Forum\ForumCategory;
use App\Entity\Forum\ForumSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-shot (#1194): creates the English forums, mirroring the French tree, before English goes live.
 * Delete this command once it has run in production.
 */
#[AsCommand(name: 'app:forum:seed-english', description: 'Create the English forum categories and forums (one-shot)')]
class SeedEnglishForumsCommand extends Command
{
    private const string SOURCE_SLUG = 'root';
    private const string LOCALE = 'en';

    /** Category title => forums as [title, slug, description]. Slugs are unique across languages. */
    private const array TREE = [
        'General' => [
            ['Off-topic', 'off-topic', 'Anything goes!'],
            ['General discussion', 'general-discussion', 'Talk about anything and everything'],
            ['Introductions', 'introductions', 'New to the forum? Come and introduce yourself!'],
        ],
        'Help' => [
            ['Drums', 'drums', 'Help with anything about drums (hardware, tuning...)'],
            ['Tips and tricks', 'tips-and-tricks', 'Share your tips and tricks for every instrument! (one post = one tip per instrument)'],
            ['Bass', 'bass', 'Help with anything about bass (amps, setup...)'],
            ['Guitar', 'guitar', 'Help with anything about guitar (amps, setup...)'],
        ],
        'Ads and promotion' => [
            ['Other ads', 'other-ads', 'Post the ads that fit no other category here.'],
            ['Buy and sell', 'buy-and-sell', 'Post your ads to buy or sell gear here.'],
            ['Promotion', 'promotion', 'A blog or a website to promote? A service for musicians? This is the place!'],
            ['Your gigs', 'your-gigs', 'Announce your own gigs.'],
            ['Your projects and creations', 'your-projects-and-creations', 'Show your music projects: classical, rock, video game music, rap...'],
        ],
        'Media sharing' => [
            ['Masterclasses', 'masterclasses', 'Post your masterclasses here.'],
            ['Music videos', 'music-videos', 'Post your favourite music videos.'],
            ['Live videos', 'live-videos', 'Live excerpts or full gigs.'],
        ],
        'About the site' => [
            ['Bug reports', 'bug-reports', 'Report any bug you run into here, we will fix it as soon as we can.'],
            ['Forum suggestions', 'forum-suggestions', 'Post your suggestions for the forum only.'],
            ['Site suggestions', 'site-suggestions', 'Post your suggestions for the site only.'],
        ],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be created without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $source = $this->entityManager->getRepository(ForumSource::class)->findOneBy(['slug' => self::SOURCE_SLUG]);
        if (!$source instanceof ForumSource) {
            $io->error(sprintf('No forum source "%s".', self::SOURCE_SLUG));

            return Command::FAILURE;
        }

        // Refuses to run twice or over a slug already taken, rather than half-seed.
        if ($this->entityManager->getRepository(ForumCategory::class)->count(['forumSource' => $source, 'locale' => self::LOCALE]) > 0) {
            $io->error('English forum categories already exist, nothing done.');

            return Command::FAILURE;
        }
        $slugs = array_merge(...array_map(static fn (array $forums): array => array_column($forums, 1), array_values(self::TREE)));
        $taken = array_values(array_filter(
            $slugs,
            fn (string $slug): bool => $this->entityManager->getRepository(Forum::class)->count(['slug' => $slug]) > 0,
        ));
        if ($taken !== []) {
            $io->error('These forum slugs are already taken: ' . implode(', ', $taken));

            return Command::FAILURE;
        }

        $categoryPosition = 1;
        foreach (self::TREE as $categoryTitle => $forums) {
            $io->section(sprintf('%d. %s', $categoryPosition, $categoryTitle));
            $category = new ForumCategory();
            $category->title = $categoryTitle;
            $category->position = $categoryPosition++;
            $category->locale = self::LOCALE;
            $category->forumSource = $source;
            $this->entityManager->persist($category);

            foreach ($forums as $forumPosition => [$title, $slug, $description]) {
                $io->writeln(sprintf('  %d. %s (/en/forums/%s): %s', $forumPosition + 1, $title, $slug, $description));
                $forum = new Forum();
                $forum->title = $title;
                $forum->slug = $slug;
                $forum->description = $description;
                $forum->position = $forumPosition + 1;
                $forum->topicNumber = 0;
                $forum->postNumber = 0;
                $forum->forumCategory = $category;
                $this->entityManager->persist($forum);
            }
        }

        if ($dryRun) {
            $io->note('Dry run: nothing written.');

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d English categories and %d forums created.', count(self::TREE), count($slugs)));

        return Command::SUCCESS;
    }
}
