<?php

namespace App\Http\Controllers;

use App\Services\BrandMediaService;
use Illuminate\Http\Response;

class BrandMediaController extends Controller
{
    public function __invoke(string $path, BrandMediaService $media): Response
    {
        return $media->response($path);
    }
}
