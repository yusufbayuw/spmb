<div class="spmb-rich-content prose prose-sm max-w-none dark:prose-invert text-gray-900 dark:text-gray-100 [&_p]:my-2 [&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-lg [&_h2]:font-bold [&_h3]:mt-3 [&_h3]:mb-2 [&_h3]:text-base [&_h3]:font-semibold [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-1 [&_strong]:font-bold [&_em]:italic [&_blockquote]:border-l-4 [&_blockquote]:pl-3 [&_a]:text-primary-600 [&_a]:underline dark:[&_a]:text-primary-400" style="line-height:1.75;">
    {!! $content !!}
    @if (filled($downloadUrl ?? null))
        <a href="{{ $downloadUrl }}" class="mt-3 inline-flex items-center rounded-lg border px-3 py-2 font-medium text-primary-600 dark:text-primary-400">Unduh {{ $downloadLabel ?? 'Dokumen' }}</a>
    @endif
</div>
