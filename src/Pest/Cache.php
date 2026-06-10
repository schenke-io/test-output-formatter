<?php

namespace SchenkeIo\TestOutputFormatter\Pest;

class Cache
{
    public function __construct(private readonly mixed $cacheDir)
    {
        if (! is_dir((string) $this->cacheDir)) {
            mkdir((string) $this->cacheDir, 0777, true);
        }
    }

    public function getStamp(): string
    {
        $files = [
            'composer.lock',
            'phpunit.xml',
            'tests/Pest.php',
        ];

        $mtimes = [];
        foreach ($files as $file) {
            $path = getcwd().DIRECTORY_SEPARATOR.$file;
            if (file_exists($path)) {
                $mtimes[$file] = filemtime($path);
            }
        }

        return md5(serialize($mtimes));
    }

    /**
     * @param  array<mixed>  $data
     */
    public function write(string $filename, array $data): void
    {
        $payload = [
            'stamp' => $this->getStamp(),
            'data' => $data,
        ];

        file_put_contents($this->cacheDir.DIRECTORY_SEPARATOR.$filename, json_encode($payload));
    }

    /**
     * @return array<mixed>|null
     */
    public function read(string $filename): ?array
    {
        try {
            $path = (string) $this->cacheDir.DIRECTORY_SEPARATOR.$filename;
            $content = @file_get_contents($path);
            $payload = json_decode((string) $content, true);
            if (! is_array($payload) || ! isset($payload['stamp']) || ! isset($payload['data'])) {
                return null;
            }

            if ($payload['stamp'] !== $this->getStamp()) {
                return null;
            }

            return $payload['data'];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  string  $prefix  e.g. "failures-"
     * @param  string  $target  e.g. "failed-files.json"
     */
    public function mergeShards(string $prefix, string $target, bool $unique = false): void
    {
        try {
            $mergedData = $this->read($target) ?? [];
            $files = glob($this->cacheDir.DIRECTORY_SEPARATOR.$prefix.'*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    try {
                        $content = @file_get_contents($file);
                        $payload = json_decode((string) $content, true);
                        $mergedData = array_merge($mergedData, $payload['data']);
                        @unlink($file);
                    } catch (\Throwable) {
                        continue;
                    }
                }
            }

            if (! empty($mergedData)) {
                if ($unique) {
                    $mergedData = array_values(array_unique($mergedData));
                }
                $this->write($target, $mergedData);
            }
        } catch (\Throwable) {
            // fail open
        }
    }
}
