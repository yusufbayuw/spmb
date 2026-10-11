<?php

namespace Tests\Unit;

use App\Support\YouTubeTutorial;
use PHPUnit\Framework\TestCase;

class YouTubeTutorialTest extends TestCase
{
    public function test_accepts_youtube_video_links_and_builds_safe_embeds(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ?si=abc',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube.com/live/dQw4w9WgXcQ',
        ] as $url) {
            $this->assertSame('dQw4w9WgXcQ', YouTubeTutorial::videoId($url));
            $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', YouTubeTutorial::embedUrl($url));
        }
    }

    public function test_rejects_invalid_or_untrusted_video_links(): void
    {
        foreach ([
            null,
            '',
            'http://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=wrong',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ.evil',
            'https://www.youtube.com@evil.example/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com:444/watch?v=dQw4w9WgXcQ',
        ] as $url) {
            $this->assertNull(YouTubeTutorial::videoId($url));
            $this->assertNull(YouTubeTutorial::embedUrl($url));
        }
    }
}
