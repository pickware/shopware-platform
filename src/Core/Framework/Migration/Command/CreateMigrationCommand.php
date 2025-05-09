<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'database:create-migration',
    description: 'Creates a new migration file',
)]
#[Package('framework')]
class CreateMigrationCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly KernelPluginCollection $kernelPluginCollection,
        private readonly string $coreDir,
        private readonly string $shopwareVersion
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::OPTIONAL)
            ->addArgument('namespace', InputArgument::OPTIONAL)
            ->addOption('plugin', 'p', InputOption::VALUE_REQUIRED)
            ->addOption('package', '', InputArgument::OPTIONAL, 'The package name for the migration')
            ->addOption(
                'name',
                '',
                InputOption::VALUE_REQUIRED,
                'An optional descriptive name for the migration which will be used as a suffix for the filename.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Creating migration...');
        $directory = (string) $input->getArgument('directory');
        $namespace = (string) $input->getArgument('namespace');
        $name = $input->getOption('name') ?? '';
        $package = $input->getOption('package') ?? 'framework';

        if (!preg_match('/^[a-zA-Z0-9\_]*$/', (string) $name)) {
            throw MigrationException::invalidArgument('Migration name contains forbidden characters!');
        }

        if ($directory && !$namespace) {
            throw MigrationException::invalidArgument('Please specify both dir and namespace or none.');
        }

        $timestamp = (new \DateTime())->getTimestamp();

        // Both dir and namespace were given
        if ($directory) {
            $this->createMigrationFile($output, (string) realpath($directory), \dirname(__DIR__) . '/Template/MigrationTemplate.txt', [
                '%%timestamp%%' => $timestamp,
                '%%name%%' => $name,
                '%%namespace%%' => $namespace,
                '%%package%%' => $package,
            ]);

            return self::SUCCESS;
        }

        $pluginName = $input->getOption('plugin');
        if ($pluginName) {
            $this->createPluginMigration($output, $pluginName, $timestamp, $name);

            return self::SUCCESS;
        }

        // We create a core-migration in case no directory or plugin was given
        [, $major] = explode('.', $this->shopwareVersion);
        $directory = $this->coreDir . '/Migration/V6_' . $major;
        $namespace = 'Shopware\\Core\\Migration\\V6_' . $major;
        $params = [
            '%%timestamp%%' => $timestamp,
            '%%name%%' => $name,
            '%%namespace%%' => $namespace,
            '%%package%%' => $package,
        ];

        $output->writeln('Creating core-migration ...');

        $this->createMigrationFile(
            $output,
            $directory,
            \dirname(__DIR__) . '/Template/MigrationTemplate.txt',
            $params
        );

        return self::SUCCESS;
    }

    private function createPluginMigration(OutputInterface $output, string $pluginName, int $timestamp, string $name): void
    {
        $pluginBundles = array_filter($this->kernelPluginCollection->all(), static fn (Plugin $value) => mb_strpos($value->getName(), $pluginName) === 0);

        if (\count($pluginBundles) === 0) {
            throw MigrationException::pluginNotFound($pluginName);
        }

        if (\count($pluginBundles) > 1) {
            $pluginBundles = array_filter($pluginBundles, static fn (Plugin $value) => $pluginName === $value->getName());

            if (\count($pluginBundles) > 1) {
                throw MigrationException::moreThanOnePluginFound($pluginName, array_keys($pluginBundles));
            }
        }

        $pluginBundle = array_values($pluginBundles)[0];

        $directory = $pluginBundle->getMigrationPath();

        (new Filesystem())->mkdir($directory);

        $namespace = $pluginBundle->getMigrationNamespace();

        $output->writeln(\sprintf('Creating plugin-migration with namespace %s in path %s...', $namespace, $directory));

        $this->createMigrationFile(
            $output,
            $directory,
            \dirname(__DIR__) . '/Template/MigrationTemplatePlugin.txt',
            [
                '%%timestamp%%' => $timestamp,
                '%%name%%' => $name,
                '%%namespace%%' => $namespace,
            ]
        );
    }

    /**
     * @param array{"%%timestamp%%": int, "%%name%%": string, "%%namespace%%": string, "%%package%%"?: string} $params
     */
    private function createMigrationFile(OutputInterface $output, string $directory, string $templatePatch, array $params): void
    {
        $path = rtrim($directory, '/') . '/Migration' . $params['%%timestamp%%'] . $params['%%name%%'] . '.php';
        $file = fopen($path, 'w');
        if ($file === false) {
            return;
        }

        $template = file_get_contents($templatePatch);
        if ($template === false) {
            return;
        }

        $params['%%timestamp%%'] = (string) $params['%%timestamp%%'];

        fwrite($file, str_replace(array_keys($params), array_values($params), $template));
        fclose($file);

        $output->writeln('Migration created: "' . $path . '"');
    }
}
