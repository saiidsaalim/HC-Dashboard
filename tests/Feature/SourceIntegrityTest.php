<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SourceIntegrityTest extends TestCase
{
    public function test_source_files_do_not_contain_git_conflict_markers(): void
    {
        $sourcePaths = [
            app_path(),
            base_path('bootstrap'),
            config_path(),
            database_path(),
            resource_path(),
            base_path('routes'),
            base_path('tests'),
        ];
        $sourceExtensions = ['css', 'js', 'json', 'md', 'php', 'xml'];

        foreach ($sourcePaths as $sourcePath) {
            foreach (File::allFiles($sourcePath) as $file) {
                if (! in_array($file->getExtension(), $sourceExtensions, true)) {
                    continue;
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/^(<<<<<<<|=======|>>>>>>>)/m',
                    File::get($file->getPathname()),
                    "Git conflict marker ditemukan pada {$file->getPathname()}.",
                );
            }
        }
    }
}
