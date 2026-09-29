<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class RepositoryBrandNeutralityTest extends TestCase
{
    public function test_repository_source_contains_no_deployment_specific_branding(): void
    {
        $roots = [
            base_path('app'),
            base_path('config'),
            base_path('database'),
            base_path('resources'),
            base_path('routes'),
            base_path('tests'),
        ];

        $files = [
            base_path('README.md'),
            base_path('.env.example'),
            base_path('public/manifest.webmanifest'),
        ];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $forbidden = [
            '/taruna\s+bakti/i',
            '/tarunabakti/i',
            '/\bTBU\b/',
            '/tbu\.ac\.id/i',
            '/martadinata\s+no\.\s*52/i',
        ];

        $violations = [];

        foreach (array_unique($files) as $file) {
            if ($file === __FILE__) {
                continue;
            }

            $content = @file_get_contents($file);

            if ($content === false || str_contains($content, "\0")) {
                continue;
            }

            foreach ($forbidden as $pattern) {
                if (preg_match($pattern, $content) === 1) {
                    $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' matches '.$pattern;
                }
            }
        }

        $this->assertSame([], $violations, "Deployment-specific branding remains in repository source:\n".implode("\n", $violations));
    }
}
