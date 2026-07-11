<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use Aws\S3\S3Client;
use RuntimeException;

final class StorageService
{
    private ?S3Client $client = null;

    public function upload(int $firmId, string $path, string $contents, string $mimeType): string
    {
        $key = $this->key($firmId, $path);
        $this->client()->putObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'Body' => $contents,
            'ContentType' => $mimeType,
        ]);

        return $key;
    }

    /** @return resource */
    public function download(string $s3Key)
    {
        $result = $this->client()->getObject([
            'Bucket' => $this->bucket(),
            'Key' => $this->cleanKey($s3Key),
        ]);
        $stream = $result['Body']->detach();
        if (!is_resource($stream)) {
            throw new RuntimeException('No fue posible abrir el objeto S3.');
        }

        return $stream;
    }

    public function presignedUrl(string $s3Key, int $ttlSeconds = 900): string
    {
        $command = $this->client()->getCommand('GetObject', [
            'Bucket' => $this->bucket(),
            'Key' => $this->cleanKey($s3Key),
        ]);

        return (string) $this->client()->createPresignedRequest($command, '+' . max(60, $ttlSeconds) . ' seconds')->getUri();
    }

    public function delete(string $s3Key): void
    {
        $this->client()->deleteObject([
            'Bucket' => $this->bucket(),
            'Key' => $this->cleanKey($s3Key),
        ]);
    }

    public function healthCheck(): bool
    {
        $this->client()->headBucket(['Bucket' => $this->bucket()]);

        return true;
    }

    private function client(): S3Client
    {
        if ($this->client instanceof S3Client) {
            return $this->client;
        }
        if (!class_exists(S3Client::class)) {
            throw new RuntimeException('Instale aws/aws-sdk-php para usar StorageService.');
        }

        $endpoint = (string) Config::env('S3_ENDPOINT', '');
        $config = [
            'version' => 'latest',
            'region' => (string) Config::env('S3_REGION', 'us-east-1'),
            'credentials' => [
                'key' => (string) Config::env('S3_KEY', ''),
                'secret' => (string) Config::env('S3_SECRET', ''),
            ],
        ];
        if ($endpoint !== '') {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = true;
        }

        return $this->client = new S3Client($config);
    }

    private function bucket(): string
    {
        $bucket = trim((string) Config::env('S3_BUCKET', ''));
        if ($bucket === '') {
            throw new RuntimeException('Falta la variable S3_BUCKET.');
        }

        return $bucket;
    }

    private function key(int $firmId, string $path): string
    {
        return $firmId . '/' . $this->cleanKey($path);
    }

    private function cleanKey(string $key): string
    {
        $key = str_replace('\\', '/', trim($key));
        $parts = [];
        foreach (explode('/', $key) as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $parts[] = preg_replace('/[^A-Za-z0-9._=-]+/', '-', $part) ?: 'file';
        }

        return implode('/', $parts);
    }
}
