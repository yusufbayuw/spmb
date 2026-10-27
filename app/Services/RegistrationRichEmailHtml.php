<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class RegistrationRichEmailHtml
{
    public function sanitize(string $input): string
    {
        // Legacy callers supplied plain text. Preserve their line breaks safely.
        if (! preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $input)) {
            $input = '<p>'.nl2br(e($input), false).'</p>';
        }

        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        // Match the actual RichEditor toolbar. Never allow images, scripts,
        // arbitrary style attributes, embeds, iframes, or tracking pixels.
        foreach ([
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
            'h2', 'h3', 'blockquote', 'ul', 'ol', 'li', 'code', 'pre',
        ] as $element) {
            $config = $config->allowElement($element);
        }

        $config = $config->allowElement('a', ['href', 'title']);

        return (new HtmlSanitizer($config))->sanitize($input);
    }

    public function render(string $body, string $portalName): string
    {
        return view('emails.registration-rich-message', [
            'content' => $this->sanitize($body),
            'portalName' => $portalName,
        ])->render();
    }
}
