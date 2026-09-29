<div class="registration-consent-shell max-h-[55vh] overflow-y-auto rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 sm:p-5">
    <div class="registration-consent-content">
        {!! $content !!}
    </div>
</div>

<style>
    .registration-consent-content {
        color: rgb(55 65 81);
        font-size: .875rem;
        line-height: 1.75rem;
    }

    .registration-consent-content > :first-child {
        margin-top: 0;
    }

    .registration-consent-content > :last-child {
        margin-bottom: 0;
    }

    .registration-consent-content p {
        margin: 0 0 .9rem;
    }

    .registration-consent-content h2 {
        margin: 1.25rem 0 .65rem;
        color: rgb(17 24 39);
        font-size: 1.125rem;
        font-weight: 700;
        line-height: 1.5;
    }

    .registration-consent-content h3 {
        margin: 1rem 0 .5rem;
        color: rgb(17 24 39);
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.5;
    }

    .registration-consent-content ol,
    .registration-consent-content ul {
        margin: 0 0 1rem;
        padding-left: 1.5rem;
    }

    .registration-consent-content ol {
        list-style: decimal;
    }

    .registration-consent-content ul {
        list-style: disc;
    }

    .registration-consent-content li {
        margin-bottom: .5rem;
        padding-left: .15rem;
    }

    .registration-consent-content blockquote {
        margin: 1rem 0;
        border-left: 4px solid rgb(209 213 219);
        padding-left: 1rem;
        color: rgb(75 85 99);
    }

    .registration-consent-content a {
        color: rgb(37 99 235);
        font-weight: 500;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .registration-consent-content strong,
    .registration-consent-content b {
        font-weight: 700;
    }

    .registration-consent-content em,
    .registration-consent-content i {
        font-style: italic;
    }

    .registration-consent-content u {
        text-decoration: underline;
    }

    .dark .registration-consent-content {
        color: rgb(229 231 235);
    }

    .dark .registration-consent-content h2,
    .dark .registration-consent-content h3 {
        color: white;
    }

    .dark .registration-consent-content blockquote {
        border-left-color: rgb(75 85 99);
        color: rgb(209 213 219);
    }

    .dark .registration-consent-content a {
        color: rgb(96 165 250);
    }
</style>
