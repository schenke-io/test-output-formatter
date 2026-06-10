<?php

namespace SchenkeIo\TestOutputFormatter\Pest;

class Git
{
    /**
     * @return string[]
     */
    public function getChangedFiles(?string $since = null): array
    {
        $ref = $since ?? 'HEAD';
        $output = [];
        $resultCode = 0;
        /*
         * git diff --name-only <ref>
         * shows changes between <ref> and the current working tree.
         */
        exec("git diff --name-only $ref", $output, $resultCode);

        if ($resultCode !== 0) {
            return [];
        }

        return array_filter($output);
    }
}
