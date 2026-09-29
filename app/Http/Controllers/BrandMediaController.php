<?php

namespace App\Http\Controllers;

use App\Services\BrandMediaService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandMediaController extends Controller
{
    public function __invoke(string $path, BrandMediaService $media): StreamedResponse
    {
        return $media->response($path);
    }
}
