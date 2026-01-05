<?php declare(strict_types=1);

namespace App\Command\ElasticSearch;

use App\Contracts\ElasticSearch\Indexes;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\IndexBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'es:index:manage', description: 'This command will help you manage your indexes (ex: list, create, remove etc.)')]
class ElasticSearchIndexManageCommand extends Command
{
    public const AVAILABLE_METHODS = ['create', 'list', 'delete'];

    public function __construct(
        private readonly IndexBuilder $indexBuilder,
        private readonly Client       $client
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('method', InputArgument::REQUIRED, 'Method you want to apply to the index ("create": create and mark live the index)')
            ->addArgument('name', InputArgument::OPTIONAL, 'Name of the index (valid: ' . implode(', ', Indexes::AVAILABLE_INDEXES) . ')');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexName = $input->getArgument('name');
        $method = $input->getArgument('method');
        if ($method === 'list') {
            $this->displayIndexList($output);

            return Command::SUCCESS;
        }
        if (!$this->isValidMethod($method)) {
            $output->writeln('<error>This method is not valid</error>');

            return Command::FAILURE;
        }
        if ($method === 'create') {
            $index = $this->indexBuilder->createIndex($indexName);
            $this->indexBuilder->markAsLive($index, $indexName);
        }
        if ($method === 'delete') {
            $this->deleteIndex($indexName);
            $output->writeln('<info>Index deleted</info>');
        }

        return Command::SUCCESS;
    }


    private function isValidMethod(string $method): bool
    {
        return in_array($method, self::AVAILABLE_METHODS, true);
    }

    private function displayIndexList(OutputInterface $output): void
    {
        /** @var array<int, array<string, mixed>> $data */
        $data = $this->client->request('_cat/indices/?format=json', 'GET')->getData();
        if (!$data) {
            return;
        }
        $table = new Table($output);
        $table
            ->setHeaders(array_keys($data[0]))
            ->setRows($data);
        $table->render();
    }

    private function deleteIndex(string $indexName): void
    {
        $this->client->request($indexName, 'DELETE')->getData();
    }
}
