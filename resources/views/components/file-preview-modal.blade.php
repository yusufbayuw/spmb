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
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Pratinjau File</p>
                    <h2 id="file-preview-title" class="truncate text-sm font-semibold text-gray-950 dark:text-white sm:text-base">File</h2>
                </div>

                <button
                    type="button"
                    data-file-preview-close
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-950 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:hover:bg-white/10 dark:hover:text-white"
                    aria-label="Tutup pratinjau"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div data-file-preview-content class="min-h-0 flex-1 overflow-auto bg-gray-100 p-3 dark:bg-gray-950 sm:p-5"></div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-4 py-3 dark:border-white/10 sm:px-6">
                <p data-file-preview-meta class="min-w-0 truncate text-xs text-gray-500 dark:text-gray-400"></p>

                <div class="flex items-center gap-2">
                    <a
                        data-file-preview-download
                        href="#"
                        download
                        class="inline-flex min-h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                    >
                        Unduh File
                    </a>
                    <button
                        type="button"
                        data-file-preview-close
                        class="inline-flex min-h-9 items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                    >
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

    const imageExtensions = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'avif']);
    const videoExtensions = new Set(['mp4', 'webm', 'mov', 'm4v', 'ogv']);
    const audioExtensions = new Set(['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac']);
    const textExtensions = new Set(['txt', 'csv', 'log', 'json', 'xml', 'md']);
    const microsoftOfficeExtensions = new Set(['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
    const googleDocumentExtensions = new Set(['odt', 'ods', 'odp', 'rtf']);

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
        const parts = fileName.toLowerCase().split('.');
        return parts.length > 1 ? parts.pop() : '';
    };

    const isPrivateHostname = (hostname) => {
        const normalized = hostname.toLowerCase();

        if (
            normalized === 'localhost' ||
            normalized === '127.0.0.1' ||
            normalized === '::1' ||
            normalized.endsWith('.test') ||
            normalized.endsWith('.local') ||
            normalized.endsWith('.localhost')
        ) {
            return true;
        }

        const ipv4 = normalized.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
        if (!ipv4) {
            return false;
        }

        const octets = ipv4.slice(1).map(Number);
        return octets[0] === 10 ||
            octets[0] === 127 ||
            (octets[0] === 192 && octets[1] === 168) ||
            (octets[0] === 172 && octets[1] >= 16 && octets[1] <= 31);
    };

    const canUseExternalViewer = (url) => {
        try {
            const parsed = new URL(url, window.location.href);
            return ['http:', 'https:'].includes(parsed.protocol) && !isPrivateHostname(parsed.hostname);
        } catch (_) {
            return false;
        }
    };

    const microsoftViewerUrl = (url) =>
        `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(url)}`;

    const googleViewerUrl = (url) =>
        `https://docs.google.com/gview?embedded=true&url=${encodeURIComponent(url)}`;

    const emptyContent = () => {
        while (content.firstChild) {
            content.removeChild(content.firstChild);
        }
    };

    const makeFallback = (fileName, extension, messageText = null) => {
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
        message.textContent = messageText || 'Format ini tidak dapat dirender langsung oleh browser. File tetap dapat diunduh melalui tombol di bawah.';

        const type = document.createElement('span');
        type.className = 'mt-4 inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium uppercase text-gray-600 dark:bg-white/10 dark:text-gray-300';
        type.textContent = extension || 'FILE';

        card.append(icon, heading, message, type);
        wrapper.appendChild(card);
        return wrapper;
    };

    const makeOfficeViewer = (url, fileName, extension, defaultViewer = 'microsoft') => {
        if (!canUseExternalViewer(url)) {
            meta.textContent = `Format: ${extension.toUpperCase()} · Viewer eksternal membutuhkan URL publik`;
            return makeFallback(
                fileName,
                extension,
                'Microsoft Office Viewer dan Google Docs Viewer harus dapat mengakses file melalui URL publik. Pada domain lokal/private, gunakan tombol Unduh File. Preview akan bekerja saat aplikasi memakai domain publik.'
            );
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'space-y-3';

        const toolbar = document.createElement('div');
        toolbar.className = 'flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-white/10 dark:bg-gray-900';

        const description = document.createElement('p');
        description.className = 'text-xs text-gray-500 dark:text-gray-400';
        description.textContent = 'Viewer dokumen eksternal';

        const buttons = document.createElement('div');
        buttons.className = 'flex items-center gap-2';

        const microsoftButton = document.createElement('button');
        microsoftButton.type = 'button';
        microsoftButton.textContent = 'Microsoft Office';
        microsoftButton.className = 'rounded-lg px-3 py-1.5 text-xs font-semibold transition';

        const googleButton = document.createElement('button');
        googleButton.type = 'button';
        googleButton.textContent = 'Google Docs';
        googleButton.className = 'rounded-lg px-3 py-1.5 text-xs font-semibold transition';

        const frame = document.createElement('iframe');
        frame.title = fileName;
        frame.className = 'h-[72vh] w-full rounded-xl bg-white shadow-sm';
        frame.setAttribute('loading', 'lazy');
        frame.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');

        const setViewer = (viewer) => {
            const microsoftActive = viewer === 'microsoft';

            frame.src = microsoftActive ? microsoftViewerUrl(url) : googleViewerUrl(url);
            meta.textContent = `Format: ${extension.toUpperCase()} · Viewer: ${microsoftActive ? 'Microsoft Office' : 'Google Docs'}`;

            microsoftButton.className = microsoftActive
                ? 'rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition'
                : 'rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/15';

            googleButton.className = !microsoftActive
                ? 'rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition'
                : 'rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/15';
        };

        microsoftButton.addEventListener('click', () => setViewer('microsoft'));
        googleButton.addEventListener('click', () => setViewer('google'));

        buttons.append(microsoftButton, googleButton);
        toolbar.append(description, buttons);
        wrapper.append(toolbar, frame);

        setViewer(defaultViewer);

        return wrapper;
    };

    const openPreview = (url, displayName = null) => {
        const fileName = displayName || getFileName(url);
        const extension = getExtension(getFileName(url, fileName));

        emptyContent();
        title.textContent = fileName;
        meta.textContent = extension ? `Format: ${extension.toUpperCase()}` : 'File';
        download.href = url;
        download.setAttribute('download', fileName);

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
        } else if (microsoftOfficeExtensions.has(extension)) {
            content.appendChild(makeOfficeViewer(url, fileName, extension, 'microsoft'));
        } else if (googleDocumentExtensions.has(extension)) {
            content.appendChild(makeOfficeViewer(url, fileName, extension, 'google'));
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
            content.appendChild(makeFallback(fileName, extension));
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
        const isExplicitPreview = link.hasAttribute('data-file-preview');

        if (!isStorageFile && !isExplicitPreview) {
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
