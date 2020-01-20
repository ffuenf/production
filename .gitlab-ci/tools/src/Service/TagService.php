<?php


namespace Shopware\CI\Service;


class TagService
{
    public function getTags(string $repositoryDir): array
    {
        $output = [];
        $returnCode = 0;
        exec('git -C ' . escapeshellarg($repositoryDir) . ' tag --list ', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to list tags');
        }

        return $output;
    }
}