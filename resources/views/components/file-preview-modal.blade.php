@once
<div
    id="file-preview-modal"
    class="fixed inset-0 z-[9999] hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="file-preview-title"
    aria-hidden="true"
>
    <div data-file-preview-backdrop class="absolute inset-0 bg-gray-950/70 backdrop-blur-sm"></div>

    <div class="relative flex min-h-full items-center justify-center p-4 sm:p-6">
        <div class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 dark:border-white/10 sm:px-6">
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Pratinjau File Privat</p>
                    <h2 id="file-preview-title" class="truncate text-sm font-semibold text-gray-950 dark:text-white sm:text-base">File</h2>
                </div>

                <button type="button" data-file-preview-close class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-950 dark:hover:bg-white/10 dark:hover:text-white" aria-label="Tutup pratinjau">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div data-file-preview-content class="min-h-0 flex-1 overflow-auto bg-gray-100 p-3 dark:bg-gray-950 sm:p-5"></div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-4 py-3 dark:border-white/10 sm:px-6">
                <p data-file-preview-meta class="min-w-0 truncate text-xs text-gray-500 dark:text-gray-400"></p>

                <div class="flex items-center gap-2">
                    <a data-file-preview-download href="#" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">
                        Unduh File
                    </a>
                    <button type="button" data-file-preview-close class="inline-flex min-h-9 items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const modal = document.getElementById('file-preview-modal');

    if (!modal || modal.dataset.bound === 'true') {
        return;
    }

    modal.dataset.bound = 'true';

    const content = modal.querySelector('[data-file-preview-content]');
    const title = modal.querySelector('#file-preview-title');
    const meta = modal.querySelector('[data-file-preview-meta]');
    const download = modal.querySelector('[data-file-preview-download]');
    const closeButtons = modal.querySelectorAll('[data-file-preview-close]');
    const backdrop = modal.querySelector('[data-file-preview-backdrop]');

    const imageExtensions = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif']);
    const videoExtensions = new Set(['mp4', 'webm', 'mov', 'm4v', 'ogv']);
    const audioExtensions = new Set(['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac']);
    const textExtensions = new Set(['txt', 'csv', 'log', 'json', 'xml', 'md']);
    const officeExtensions = new Set(['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf']);

    const getFileName = (url, fallback = 'File') => {
        try {
            const pathname = new URL(url, window.location.href).pathname;
            const lastSegment = pathname.split('/').filter(Boolean).pop();
            return decodeURIComponent(lastSegment || fallback);
        } catch (_) {
            return fallback;
        }
    };

    const getExtension = (fileName) => {
        const parts = String(fileName || '').toLowerCase().split('.');
        return parts.length > 1 ? parts.pop() : '';
    };

    const emptyContent = () => {
        while (content.firstChild) {
            content.removeChild(content.firstChild);
        }
    };

    const secureDownloadUrl = (url) => {
        const parsed = new URL(url, window.location.href);

        if (parsed.pathname.includes('/files/applicant/')) {
            parsed.searchParams.set('download', '1');
        }

        return parsed.href;
    };

    const makeFallback = (fileName, extension, isOffice = false) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'flex min-h-[28rem] items-center justify-center';

        const card = document.createElement('div');
        card.className = 'max-w-lg rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-white/10 dark:bg-gray-900';

        const icon = document.createElement('div');
        icon.className = 'mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/10';
        icon.innerHTML = '<svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5"/></svg>';

        const heading = document.createElement('p');
        heading.className = 'mt-5 break-words text-base font-semibold text-gray-950 dark:text-white';
        heading.textContent = fileName;

        const message = document.createElement('p');
        message.className = 'mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400';
        message.textContent = isOffice
            ? 'Dokumen Office tidak dikirim ke Microsoft/Google Viewer agar data pendaftar tetap privat. Gunakan Unduh File untuk membukanya di aplikasi Office.'
            : 'Format ini tidak dirender oleh browser. File tetap terlindungi dan hanya dapat diunduh oleh akun yang berwenang.';

        const badge = document.createElement('span');
        badge.className = 'mt-4 inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium uppercase text-gray-600 dark:bg-white/10 dark:text-gray-300';
        badge.textContent = extension || 'FILE';

        card.append(icon, heading, message, badge);
        wrapper.appendChild(card);

        return wrapper;
    };

    const openPreview = (url, displayName = null) => {
        const fileName = displayName || getFileName(url);
        const extension = getExtension(fileName) || getExtension(getFileName(url));

        emptyContent();
        title.textContent = fileName;
        meta.textContent = `${extension ? `Format: ${extension.toUpperCase()} · ` : ''}akses terautentikasi`;
        download.href = secureDownloadUrl(url);

        if (imageExtensions.has(extension)) {
            const image = document.createElement('img');
            image.src = url;
            image.alt = fileName;
            image.className = 'mx-auto max-h-[72vh] max-w-full rounded-xl object-contain shadow-sm';
            content.appendChild(image);
        } else if (extension === 'pdf') {
            const frame = document.createElement('iframe');
            frame.src = url;
            frame.title = fileName;
            frame.className = 'h-[72vh] w-full rounded-xl bg-white shadow-sm';
            content.appendChild(frame);
        } else if (videoExtensions.has(extension)) {
            const video = document.createElement('video');
            video.src = url;
            video.controls = true;
            video.preload = 'metadata';
            video.className = 'mx-auto max-h-[72vh] max-w-full rounded-xl bg-black shadow-sm';
            content.appendChild(video);
        } else if (audioExtensions.has(extension)) {
            const wrapper = document.createElement('div');
            wrapper.className = 'flex min-h-[28rem] items-center justify-center';
            const audio = document.createElement('audio');
            audio.src = url;
            audio.controls = true;
            audio.preload = 'metadata';
            audio.className = 'w-full max-w-2xl';
            wrapper.appendChild(audio);
            content.appendChild(wrapper);
        } else if (textExtensions.has(extension)) {
            const frame = document.createElement('iframe');
            frame.src = url;
            frame.title = fileName;
            frame.className = 'h-[72vh] w-full rounded-xl bg-white shadow-sm';
            content.appendChild(frame);
        } else {
            content.appendChild(makeFallback(fileName, extension, officeExtensions.has(extension)));
        }

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('overflow-hidden');
        modal.querySelector('[data-file-preview-close]')?.focus();
    };

    const closePreview = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('overflow-hidden');
        emptyContent();
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link || link.hasAttribute('download')) {
            return;
        }

        let parsedUrl;

        try {
            parsedUrl = new URL(link.href, window.location.href);
        } catch (_) {
            return;
        }

        const isStorageFile = parsedUrl.pathname.includes('/storage/');
        const isPrivateApplicantFile = parsedUrl.pathname.includes('/files/applicant/');
        const isExplicitPreview = link.hasAttribute('data-file-preview');

        if (!isStorageFile && !isPrivateApplicantFile && !isExplicitPreview) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const label = link.getAttribute('data-file-name') || link.textContent?.trim() || getFileName(parsedUrl.href);
        openPreview(parsedUrl.href, label);
    }, true);

    closeButtons.forEach((button) => button.addEventListener('click', closePreview));
    backdrop?.addEventListener('click', closePreview);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closePreview();
        }
    });

    window.addEventListener('file-preview:open', (event) => {
        if (event.detail?.url) {
            openPreview(event.detail.url, event.detail.name || null);
        }
    });
})();
</script>
@endonce
