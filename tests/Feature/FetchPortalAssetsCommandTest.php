<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase as BaseTestCase;

class FetchPortalAssetsCommandTest extends BaseTestCase
{
    private string $validPng;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jVgAAAABJRU5ErkJggg==',
            true,
        );

        Storage::fake('portal-assets');
        config([
            'portal.asset_hosts' => ['images.unsplash.com'],
            'portal.asset_directory' => Storage::disk('portal-assets')->path('portal'),
            'portal.assets' => [$this->asset()],
        ]);
    }

    public function test_command_downloads_a_valid_image_to_the_configured_asset_directory(): void
    {
        Http::fake([
            'images.unsplash.com/*' => Http::response($this->validPng, 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('portal:fetch-assets')
            ->expectsOutputToContain('downloaded')
            ->assertExitCode(0);

        Storage::disk('portal-assets')->assertExists('portal/hero-building.png');
        $this->assertSame($this->validPng, Storage::disk('portal-assets')->get('portal/hero-building.png'));
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent', 'HC-Dashboard/1.0'));
    }

    public function test_command_rejects_hosts_that_are_not_allowlisted(): void
    {
        Http::fake();
        config(['portal.assets' => [$this->asset(url: 'https://untrusted.example/image.png')]]);

        $this->artisan('portal:fetch-assets')
            ->expectsOutputToContain('failed')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_command_rejects_non_https_urls_before_sending_a_request(): void
    {
        Http::fake();
        config(['portal.assets' => [$this->asset(url: 'http://images.unsplash.com/image.png')]]);

        $this->artisan('portal:fetch-assets')
            ->expectsOutputToContain('HTTPS')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_command_rejects_paths_outside_the_portal_asset_directory(): void
    {
        Http::fake();
        config(['portal.assets' => [$this->asset(path: '..\\outside.png')]]);

        $this->artisan('portal:fetch-assets')
            ->expectsOutputToContain('file name inside')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_command_rejects_a_non_image_content_type(): void
    {
        Http::fake([
            'images.unsplash.com/*' => Http::response('not an image', 200, ['Content-Type' => 'text/plain']),
        ]);

        $this->artisan('portal:fetch-assets')
            ->expectsOutputToContain('Unsupported Content-Type')
            ->assertExitCode(1);

        Storage::disk('portal-assets')->assertMissing('portal/hero-building.png');
    }

    public function test_command_rejects_an_image_larger_than_its_configured_limit(): void
    {
        Http::fake([
            'images.unsplash.com/*' => Http::response($this->validPng, 200, ['Content-Type' => 'image/png']),
        ]);
        config(['portal.assets' => [$this->asset(maxBytes: strlen($this->validPng) - 1)]]);

        $this->artisan('portal:fetch-assets')
            ->expectsOutputToContain('exceeds max_bytes')
            ->assertExitCode(1);

        Storage::disk('portal-assets')->assertMissing('portal/hero-building.png');
    }

    public function test_command_rejects_a_response_that_is_not_a_valid_image(): void
    {
        Http::fake([
            'images.unsplash.com/*' => Http::response('invalid png bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('portal:fetch-assets')
            ->expectsOutputToContain('not a valid')
            ->assertExitCode(1);

        Storage::disk('portal-assets')->assertMissing('portal/hero-building.png');
    }

    public function test_existing_files_are_skipped_without_force(): void
    {
        Storage::disk('portal-assets')->put('portal/hero-building.png', 'existing file');
        Http::fake();

        $this->artisan('portal:fetch-assets')
            ->expectsOutputToContain('skipped')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame('existing file', Storage::disk('portal-assets')->get('portal/hero-building.png'));
    }

    public function test_force_replaces_an_existing_file(): void
    {
        Storage::disk('portal-assets')->put('portal/hero-building.png', 'existing file');
        Http::fake([
            'images.unsplash.com/*' => Http::response($this->validPng, 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('portal:fetch-assets --force')->assertExitCode(0);

        $this->assertSame($this->validPng, Storage::disk('portal-assets')->get('portal/hero-building.png'));
    }

    public function test_one_failed_asset_does_not_prevent_other_assets_from_downloading(): void
    {
        config([
            'portal.assets' => [
                $this->asset(key: 'bad-image', path: 'bad.png', url: 'https://images.unsplash.com/bad.png'),
                $this->asset(key: 'good-image', path: 'good.png', url: 'https://images.unsplash.com/good.png'),
            ],
        ]);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/bad.png')) {
                return Http::response('not an image', 200, ['Content-Type' => 'text/plain']);
            }

            return Http::response($this->validPng, 200, ['Content-Type' => 'image/png']);
        });

        $this->artisan('portal:fetch-assets')
            ->expectsOutputToContain('bad-image')
            ->expectsOutputToContain('good-image')
            ->assertExitCode(1);

        Storage::disk('portal-assets')->assertMissing('portal/bad.png');
        Storage::disk('portal-assets')->assertExists('portal/good.png');
    }

    /**
     * @return array<string, mixed>
     */
    private function asset(
        string $key = 'hero-building',
        string $path = 'hero-building.png',
        string $url = 'https://images.unsplash.com/test-image.png',
        int $maxBytes = 50_000,
    ): array {
        return [
            'key' => $key,
            'url' => $url,
            'path' => $path,
            'max_bytes' => $maxBytes,
            'source' => 'Unsplash',
            'license' => 'Unsplash License',
            'attribution' => null,
        ];
    }
}
