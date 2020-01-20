<?php declare(strict_types=1);

namespace Shopware\CI\Command;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowNextTagCommand extends Command
{
    public static $defaultName = 'show-next-tag';

    /**
     * @var string
     */
    private $repository;

    protected function configure(): void
    {
        $this
            ->setDescription('Show next tag for branch')
            ->addArgument('repository', InputArgument::OPTIONAL, 'Repository path')
            ->addOption('constraint', null, InputOption::VALUE_REQUIRED, 'Version constraint')
            ->addOption('minimum-stability', null, InputOption::VALUE_REQUIRED, 'Release stability')
        ;
    }

    /** @var array */
    private static $stabilities = array('stable', 'RC', 'beta', 'alpha', 'dev');

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->repository = $input->getArgument('repository') ?? getcwd();
        $tags = $this->getTags();

        $composerJson = $this->getComposerJson();

        $constraint = $input->getOption('constraint');
        if (!$constraint) {
            $constraint = $composerJson['require']['shopware/core'];
        }

        $minimumStability = $input->getOption('minimum-stability');
        if (!$minimumStability) {
            $minimumStability = $composerJson['minimum-stability'];
        }
        $minimumStability = VersionParser::normalizeStability($minimumStability);

        $allowedStabilities = array_slice(
            self::$stabilities,
            0,
            1 + array_search($minimumStability, self::$stabilities, true)
        );

        $matchingTags = $this->getMatchingTags($tags, $constraint, $allowedStabilities);
        if (empty($matchingTags)) {
            $nextVersion = [$this->getInitialTag($constraint, $minimumStability)];
        } else {
            $bestMatch = array_pop($matchingTags);
            $nextVersion = $this->getNextVersion($bestMatch, $minimumStability);
        }

        $output->writeln($nextVersion);

        return 0;
    }

    private function getTags(): array
    {
        $output = [];
        $returnCode = 0;
        exec('git -C ' . escapeshellarg($this->repository) . ' tag --list ', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to list tags');
        }

        return $output;
    }

    private function getComposerJson(): array
    {
        $returnCode = 0;
        $rootDir = exec('git -C ' . escapeshellarg($this->repository) .' rev-parse --show-toplevel', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to list tags');
        }

        $path = $rootDir . '/composer.json';

        return \json_decode(file_get_contents($path), true);
    }

    private function getMatchingTags(array $tags, string $constraint, array $allowedStabilities ): array
    {
        $versions = Semver::satisfiedBy($tags, $constraint);
        $versions = array_filter($versions, static function ($version) use ($allowedStabilities) {
            return in_array(VersionParser::parseStability($version), $allowedStabilities, true);
        });

        return Semver::sort($versions);
    }

    private function getNextVersion(string $lastVersion): string
    {
        if(!preg_match('/(v6\.\d+\.)(\d+)(-(rc|beta|alpha|dev)(\d+))?/', strtolower($lastVersion), $matches)) {
            throw new \RuntimeException('Invalid version ' . $lastVersion);
        }

        $base = $matches[1];
        $currentPatch = $matches[2];

        if (!isset($matches[3])) {
            return $base . ($currentPatch + 1);
        }

        $stability = $matches[4];
        $unstableVersion = (int)($matches[5] ?? 1);

        return $base . $currentPatch . '-' . $stability . ($unstableVersion + 1);
    }

    private function getInitialTag(string $constraint, string $minimumStability): string
    {
        if(!preg_match('/^(v6\.\d+\.)\*$/', $constraint, $matches)) {
            throw new \RuntimeException('No initial tag found!');
        }

        $suffix = '';
        if ($minimumStability !== 'stable') {
            $suffix = '-' . $minimumStability . '1';
        }

        return $matches[1] . '0' . $suffix;
    }
}
