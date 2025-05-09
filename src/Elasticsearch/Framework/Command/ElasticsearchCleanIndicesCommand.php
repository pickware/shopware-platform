<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use OpenSearch\Client;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'es:index:cleanup',
    description: 'Clean outdated indices',
)]
#[Package('framework')]
class ElasticsearchCleanIndicesCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly ElasticsearchOutdatedIndexDetector $outdatedIndexDetector
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this
                ->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $indices = $this->outdatedIndexDetector->get();

        if (empty($indices)) {
            $io->writeln('No indices to be deleted.');

            return self::SUCCESS;
        }

        $io->table(['Indices to be deleted:'], array_map(static fn (string $name) => [$name], $indices));

        if (Feature::isActive('v6.8.0.0') || !$input->getOption('force')) {
            $confirm = $io->confirm(\sprintf('Delete these %d indices?', \count($indices)));

            if (!$confirm) {
                $io->caution('Deletion aborted.');

                return self::SUCCESS;
            }
        }

        foreach ($indices as $index) {
            $this->client->indices()->delete(['index' => $index]);
        }

        $io->writeln('Indices deleted.');

        return self::SUCCESS;
    }
}
