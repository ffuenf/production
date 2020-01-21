<?php declare(strict_types=1);

namespace Shopware\CI\Command;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateReleaseBranchCommand extends Command
{
    public static $defaultName = 'create-release-branch';

    /**
     * @var string
     */
    private $repository;

    /**
     * @var array
     */
    private static $stabilities = array('stable', 'RC', 'beta', 'alpha', 'dev');

    /**
     * @var VersionParser
     */
    private $versionParser;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $this->versionParser = new VersionParser();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create release branch')
            ->addArgument('repository', InputArgument::OPTIONAL, 'Repository path')
            ->addOption('constraint', null, InputOption::VALUE_REQUIRED, 'Version constraint')
            ->addOption('minimum-stability', null, InputOption::VALUE_REQUIRED, 'Release stability')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}
