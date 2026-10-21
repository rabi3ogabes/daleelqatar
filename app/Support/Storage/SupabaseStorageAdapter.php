<?php

declare(strict_types=1);

namespace App\Support\Storage;

use DateTimeInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Throwable;

/**
 * Flysystem adapter for Supabase Storage (REST API, server-side).
 *
 * Authenticates with the project's service-role key, which therefore must
 * only ever live in server environment variables. Public buckets expose
 * objects through stable public URLs; private buckets are only reachable
 * through short-lived signed URLs generated here.
 */
final class SupabaseStorageAdapter implements FilesystemAdapter
{
    private string $baseUrl;

    public function __construct(
        string $projectUrl,
        private readonly string $key,
        private readonly string $bucket,
        private readonly bool $public = true,
        private readonly int $signedUrlTtl = 600,
        private readonly int $timeout = 30,
    ) {
        $this->baseUrl = rtrim($projectUrl, '/').'/storage/v1';
    }

    public function bucket(): string
    {
        return $this->bucket;
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->key)
            ->withHeaders(['apikey' => $this->key])
            ->timeout($this->timeout)
            ->acceptJson();
    }

    private function objectPath(string $path): string
    {
        $segments = array_map('rawurlencode', explode('/', ltrim($path, '/')));

        return $this->bucket.'/'.implode('/', $segments);
    }

    public function fileExists(string $path): bool
    {
        try {
            $response = $this->http()->head($this->baseUrl.'/object/'.$this->objectPath($path));
        } catch (Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }

        if ($response->status() === 404 || $response->status() === 400) {
            return false;
        }

        if ($response->failed()) {
            throw UnableToCheckExistence::forLocation($path);
        }

        return true;
    }

    public function directoryExists(string $path): bool
    {
        return $this->listObjects(trim($path, '/'), 1) !== [];
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->upload($path, stream_get_contents($contents) ?: '', $config);
    }

    private function upload(string $path, string $contents, Config $config): void
    {
        $mime = $config->get('mimetype')
            ?? (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents)
            ?: 'application/octet-stream';

        try {
            $response = $this->http()
                ->withHeaders([
                    'x-upsert' => 'true',
                    'cache-control' => (string) $config->get('cache_control', 'max-age=31536000'),
                ])
                ->withBody($contents, $mime)
                ->post($this->baseUrl.'/object/'.$this->objectPath($path));
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }

        if ($response->failed()) {
            throw UnableToWriteFile::atLocation($path, $this->errorMessage($response));
        }
    }

    public function read(string $path): string
    {
        try {
            $response = $this->http()->get($this->baseUrl.'/object/'.$this->objectPath($path));
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }

        if ($response->failed()) {
            throw UnableToReadFile::fromLocation($path, $this->errorMessage($response));
        }

        return $response->body();
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        try {
            $response = $this->http()->send('DELETE', $this->baseUrl.'/object/'.$this->bucket, [
                'json' => ['prefixes' => [ltrim($path, '/')]],
            ]);
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }

        if ($response->failed() && $response->status() !== 404) {
            throw UnableToDeleteFile::atLocation($path, $this->errorMessage($response));
        }
    }

    public function deleteDirectory(string $path): void
    {
        $paths = array_map(
            fn (array $item) => $item['path'],
            array_filter($this->collectFiles(trim($path, '/')), fn (array $item) => $item['type'] === 'file')
        );

        foreach (array_chunk($paths, 100) as $chunk) {
            $this->http()->send('DELETE', $this->baseUrl.'/object/'.$this->bucket, ['json' => ['prefixes' => $chunk]]);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Object storage has no real directories; prefixes are created implicitly.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Visibility is defined per Supabase bucket.');
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, $this->public ? Visibility::PUBLIC : Visibility::PRIVATE);
    }

    private function metadata(string $path): FileAttributes
    {
        try {
            $response = $this->http()->head($this->baseUrl.'/object/'.$this->objectPath($path));
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::create($path, 'metadata', $e->getMessage(), $e);
        }

        if ($response->failed()) {
            throw UnableToRetrieveMetadata::create($path, 'metadata', $this->errorMessage($response));
        }

        $modified = $response->header('Last-Modified');

        return new FileAttributes(
            $path,
            ($len = $response->header('Content-Length')) !== '' ? (int) $len : null,
            $this->public ? Visibility::PUBLIC : Visibility::PRIVATE,
            $modified ? strtotime($modified) ?: null : null,
            $response->header('Content-Type') ?: null,
        );
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $items = $deep ? $this->collectFiles(trim($path, '/')) : $this->listObjects(trim($path, '/'));

        foreach ($items as $item) {
            yield $item['type'] === 'dir'
                ? new DirectoryAttributes($item['path'])
                : new FileAttributes($item['path'], $item['size'], null, $item['modified'], $item['mime']);
        }
    }

    /** @return list<array{type:string,path:string,size:?int,modified:?int,mime:?string}> */
    private function listObjects(string $prefix, int $limit = 1000): array
    {
        $response = $this->http()->post($this->baseUrl.'/object/list/'.$this->bucket, [
            'prefix' => $prefix,
            'limit' => $limit,
            'offset' => 0,
            'sortBy' => ['column' => 'name', 'order' => 'asc'],
        ]);

        if ($response->failed()) {
            return [];
        }

        $out = [];
        foreach ((array) $response->json() as $row) {
            $name = $row['name'] ?? '';
            if ($name === '' || $name === '.emptyFolderPlaceholder') {
                continue;
            }
            $full = ltrim(($prefix !== '' ? $prefix.'/' : '').$name, '/');
            $isDir = ($row['id'] ?? null) === null;
            $out[] = [
                'type' => $isDir ? 'dir' : 'file',
                'path' => $full,
                'size' => $row['metadata']['size'] ?? null,
                'modified' => isset($row['updated_at']) ? strtotime($row['updated_at']) ?: null : null,
                'mime' => $row['metadata']['mimetype'] ?? null,
            ];
        }

        return $out;
    }

    private function collectFiles(string $prefix): array
    {
        $all = [];
        foreach ($this->listObjects($prefix) as $item) {
            $all[] = $item;
            if ($item['type'] === 'dir') {
                array_push($all, ...$this->collectFiles($item['path']));
            }
        }

        return $all;
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $response = $this->http()->post($this->baseUrl.'/object/move', [
            'bucketId' => $this->bucket,
            'sourceKey' => ltrim($source, '/'),
            'destinationKey' => ltrim($destination, '/'),
        ]);

        if ($response->failed()) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $response = $this->http()->post($this->baseUrl.'/object/copy', [
            'bucketId' => $this->bucket,
            'sourceKey' => ltrim($source, '/'),
            'destinationKey' => ltrim($destination, '/'),
        ]);

        if ($response->failed()) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }
    }

    /** Used by Laravel's FilesystemAdapter::url(). */
    public function getUrl(string $path): string
    {
        if (! $this->public) {
            return $this->getTemporaryUrl($path, now()->addSeconds($this->signedUrlTtl));
        }

        return $this->baseUrl.'/object/public/'.$this->objectPath($path);
    }

    /** Used by Laravel's FilesystemAdapter::temporaryUrl(). */
    public function getTemporaryUrl(string $path, DateTimeInterface $expiration, array $options = []): string
    {
        $seconds = max(60, $expiration->getTimestamp() - time());

        $response = $this->http()->post($this->baseUrl.'/object/sign/'.$this->objectPath($path), [
            'expiresIn' => $seconds,
        ]);

        $signed = $response->json('signedURL') ?? $response->json('signedUrl');

        if ($response->failed() || ! is_string($signed)) {
            throw UnableToReadFile::fromLocation($path, 'Could not create a signed URL: '.$this->errorMessage($response));
        }

        return str_starts_with($signed, 'http') ? $signed : $this->baseUrl.$signed;
    }

    /**
     * One-time URL the browser can PUT a file to directly — large uploads never
     * pass through the application (serverless functions cap request bodies).
     */
    public function createSignedUploadUrl(string $path): string
    {
        $response = $this->http()->post($this->baseUrl.'/object/upload/sign/'.$this->objectPath($path));
        $url = $response->json('url');

        if ($response->failed() || ! is_string($url)) {
            throw UnableToWriteFile::atLocation($path, 'Could not create a signed upload URL: '.$this->errorMessage($response));
        }

        return str_starts_with($url, 'http') ? $url : $this->baseUrl.$url;
    }

    /** Streams an object to a local file without holding it in memory. */
    public function downloadTo(string $path, string $target): void
    {
        try {
            $response = $this->http()->timeout(120)->sink($target)->get($this->baseUrl.'/object/'.$this->objectPath($path));
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }

        if ($response->failed()) {
            @unlink($target);
            throw UnableToReadFile::fromLocation($path, $this->errorMessage($response));
        }
    }

    private function errorMessage(Response $response): string
    {
        return sprintf('Supabase Storage responded %d: %s', $response->status(), (string) ($response->json('message') ?? $response->json('error') ?? 'unknown error'));
    }
}
