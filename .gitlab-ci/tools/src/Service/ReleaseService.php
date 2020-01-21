<?php


namespace Shopware\CI\Service;


use GuzzleHttp\Client;
use function Symfony\Component\VarDumper\Dumper\esc;

class ReleaseService
{
    /**
     * @var GitlabApiClient
     */
    private $gitlabApiClient;

    public function __construct()
    {
        $parameters = $this->getParameters();

        $this->gitlabApiClient = new GitlabApiClient(
            new Client(['base_uri' => $parameters['gitlabBaseUri']]),
            $parameters['apiToken']
        );
    }

    public function getParameters(): array
    {
        return [
            'projectId' => $_SERVER['CI_PROJECT_ID'] ?? 184,
            'gitlabBaseUri' => $_SERVER['CI_API_V4_URL'] ?? 'https://gitlab.shopware.com/api/v4',
            'gitlabApiToken' => $_SERVER['BOT_API_TOKEN'],
            'gitlabRemoteUrl' => $_SERVER['CI_REPOSITORY_URL'],
            'tag' => $_SERVER['TAG'] ?? 'v6.2.0-alpha1',
            'targetBranch' => $_SERVER['TARGET_BRANCH'] ?? '6.2',
        ];
    }

    public function release(): void
    {
        $repos = [
            'core' => [
                'path' => 'repos/core',
                'remoteUrl' => '' //TODO
            ]
        ];


        // tag push many repos


        $parameters = $this->getParameters();
        $tag = $parameters['tag'];

        // TODO
        $root = '';

        $this->tagAndPushRepos($tag, $repos);

        $this->updateComposerLock(
            $root . '/composer.lock',
            $tag,
            $repos
        );

        $this->createReleaseBranch(
            $parameters['repository'],
            $tag,
            $parameters['gitlabRemoteUrl']
        );

        $this->gitlabApiClient->openMergeRequest(
            $parameters['projectId'],
            'release/' . $tag,
            $parameters['targetBranch'],
            'Release ' . $tag
        );
    }

    private function tagAndPushRepos(string $tag, array $repos): void
    {
        foreach ($repos as $repo => $repoData) {
            $path = escapeshellarg($repoData['path']);
            $commitMsg = escapeshellarg('Release ' . $tag);
            $remote = escapeshellarg($repoData['remoteUrl']);
            $tag = escapeshellarg($tag);

            $shellCode = <<<CODE
    git -C $path -a -m $commitMsg || true
    git -C $path remote add release  $remote
    git -C $path push release refs/tags/$tag
CODE;

            system($shellCode);
            // TODO: error handling
        }
    }

    private function updateComposerLock(string $composerLockPath, string $tag, array $repos): void
    {
        $max = 10;
        for($i = 0; $i < $max; ++$i) {
            sleep(15);

            system('composer update shopware/* --ignore-platform-reqs --no-interaction --no-scripts');

            $composerLock = json_decode(file_get_contents($composerLockPath));

            foreach ($repos as $repo => $repoData) {
                $package = $this->getPackageFromComposerLock($composerLock, 'shopware/' . $repo);

                // retry top loop
                if ($package['version'] !== $tag) {
                    continue 2;
                }

                $this->validatePackage($package, $tag, $repoData);
            }
        }

        if ($i >= $max) {
            throw new \RuntimeException('Failed to update composer.lock');
        }
    }

    private function getPackageFromComposerLock(array $composerLock, string $packageName): ?array
    {
        foreach ($composerLock['packages'] as $package) {
            if ($package['name'] === $packageName) {
                return $package;
            }
        }

        return null;
    }

    private function validatePackage(array $packageData, string $tag, array $repoData): void
    {
        $packageName = $packageData['name'];
        if ($packageData['dist']['type'] === 'path') {
            throw new \LogicException('dist type path should not be possible for ' . $packageName);
        }

        $reference = $packageData['dist']['reference'];
        $repoPath = $repoData['path'];
        $commitSha = exec('git -C ' . escapeshellarg($repoPath) . ' rev-parse HEAD');

        if (strtolower($reference) !== $commitSha) {
            throw new \LogicException("commit sha of $repoPath $commitSha should be the sames as $packageName.dist.reference $reference");
        }
    }

    private function createReleaseBranch(string $repository, string $tag, string $gitRemoteUrl): void
    {
        $repository = escapeshellarg($repository);
        $commitMsg = escapeshellarg('Release ' . $tag);
        $tag = escapeshellarg($tag);
        $gitRemoteUrl = escapeshellarg($gitRemoteUrl);

        $shellCode = <<<CODE
set -e
git -C $repository add composer.lock
git -C $repository commit -m $commitMsg
git -C $repository tag $tag -a -m $commitMsg
git -C $repository remote add release $gitRemoteUrl
git -C $repository push --tags release
CODE;

        system($shellCode, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to create release branch');
        }
    }



    public function getUpdateApiStability(string $tag): string
    {
    }
}