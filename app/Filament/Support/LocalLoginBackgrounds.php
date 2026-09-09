<?php

namespace App\Filament\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Swis\Filament\Backgrounds\Contracts\ProvidesImages;
use Swis\Filament\Backgrounds\Image;

class LocalLoginBackgrounds implements ProvidesImages
{
    public function __construct(
        private readonly string $directory,
    ) {
    }

    public static function make(string $directory): self
    {
        return new self($directory);
    }

    public function getImage(): Image
    {
        $path = public_path($this->directory);

        if (! is_dir($path)) {
            return $this->fallback();
        }

        $images = collect(app(Filesystem::class)->files($path))
            ->filter(fn ($file): bool => in_array(
                strtolower($file->getExtension()),
                ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'svg'],
                true,
            ))
            ->values();

        if ($images->isEmpty()) {
            return $this->fallback();
        }

        $image = $images->random();
        $relativePath = Str::of($image->getPathname())
            ->replaceStart(public_path(), '')
            ->replace(DIRECTORY_SEPARATOR, '/')
            ->ltrim('/')
            ->toString();

        return new Image('url("'.asset($relativePath).'")');
    }

    private function fallback(): Image
    {
        return new Image(
            'linear-gradient(135deg, rgb(15 23 42), rgb(30 64 175))'
        );
    }
}
