<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

#[Signature('portal:fetch-assets {--force : Replace existing portal assets}')]
#[Description('Download verified portal images from approved HTTPS hosts.')]
class FetchPortalAssetsCommand extends Command
{
    public function handle(): int
    {
        $rows = [];
        $failed = false;

        foreach (config('portal.assets', []) as $asset) {
            $key = is_array($asset) ? (string) ($asset['key'] ?? 'unknown') : 'unknown';

            try {
                $result = $this->fetchAsset($asset);
            } catch (Throwable $exception) {
                $result = [
                    'status' => 'failed',
                    'size' => '-',
                    'message' => $exception->getMessage(),
                ];
            }

            $rows[] = [
                'key' => $key,
                'status' => $result['status'],
                'size' => $result['size'],
                'message' => $result['message'],
            ];

            $failed = $failed || $result['status'] === 'failed';
        }

        $this->table(['Key', 'Status', 'Size (bytes)', 'Message'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $asset
     * @return array{status: string, size: int|string, message: string}
     */
    private function fetchAsset(array $asset): array
    {
        $path = $asset['path'] ?? null;

        if (! is_string($path) || $path === '' || basename($path) !== $path || str_contains($path, '\\')) {
            return $this->failed('Asset path must be a file name inside the portal asset directory.');
        }

        $destination = rtrim(config('portal.asset_directory'), '\\/').DIRECTORY_SEPARATOR.$path;

        if (File::exists($destination) && ! $this->option('force')) {
            return [
                'status' => 'skipped',
                'size' => File::size($destination),
                'message' => 'File already exists.',
            ];
        }

        $url = $asset['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return [
                'status' => 'skipped',
                'size' => '-',
                'message' => 'No verified source URL; place the official asset manually.',
            ];
        }

        $urlParts = parse_url($url);
        $host = is_array($urlParts) ? strtolower((string) ($urlParts['host'] ?? '')) : '';
        $scheme = is_array($urlParts) ? ($urlParts['scheme'] ?? null) : null;
        $allowedHosts = array_map('strtolower', config('portal.asset_hosts', []));
        $hasCredentials = is_array($urlParts) && (isset($urlParts['user']) || isset($urlParts['pass']));
        $hasUnapprovedPort = is_array($urlParts) && isset($urlParts['port']) && $urlParts['port'] !== 443;

        if ($scheme !== 'https' || ! in_array($host, $allowedHosts, true) || $hasCredentials || $hasUnapprovedPort) {
            return $this->failed('URL must use HTTPS and an approved asset host.');
        }

        $maxBytes = filter_var($asset['max_bytes'] ?? null, FILTER_VALIDATE_INT);

        if ($maxBytes === false || $maxBytes < 1) {
            return $this->failed('Asset max_bytes must be a positive integer.');
        }

        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'HC-Dashboard/1.0'])
            ->withOptions(['allow_redirects' => false])
            ->retry(2, 200)
            ->get($url);

        if ($response->status() !== 200) {
            return $this->failed('HTTP request returned status '.$response->status().'.');
        }

        return $this->storeImageResponse($response, $asset, $destination, $maxBytes);
    }

    /**
     * @param  array<string, mixed>  $asset
     * @return array{status: string, size: int|string, message: string}
     */
    private function storeImageResponse(Response $response, array $asset, string $destination, int $maxBytes): array
    {
        $contentType = strtolower(trim(explode(';', $response->header('Content-Type') ?? '')[0]));
        $allowedContentTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (! in_array($contentType, $allowedContentTypes, true)) {
            return $this->failed('Unsupported Content-Type: '.($contentType ?: 'missing').'.');
        }

        $contents = $response->body();
        $size = strlen($contents);

        if ($size > $maxBytes) {
            return $this->failed("Image exceeds max_bytes ({$size} > {$maxBytes}).");
        }

        $imageInfo = @getimagesizefromstring($contents);

        if ($imageInfo === false || ! in_array($imageInfo['mime'] ?? null, $allowedContentTypes, true)) {
            return $this->failed('Response body is not a valid JPEG, PNG, or WebP image.');
        }

        if ($imageInfo['mime'] !== $contentType) {
            return $this->failed('Image format does not match the declared Content-Type.');
        }

        File::ensureDirectoryExists(dirname($destination));

        if (File::put($destination, $contents) === false) {
            throw new RuntimeException('Unable to write image to '.$destination.'.');
        }

        return [
            'status' => 'downloaded',
            'size' => $size,
            'message' => 'Downloaded from '.($asset['source'] ?? 'configured source').'.',
        ];
    }

    /**
     * @return array{status: string, size: string, message: string}
     */
    private function failed(string $message): array
    {
        return [
            'status' => 'failed',
            'size' => '-',
            'message' => $message,
        ];
    }
}
