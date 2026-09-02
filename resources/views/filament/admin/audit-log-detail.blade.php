<div class="space-y-6 text-sm">
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Pelaku</div>
            <div class="mt-1 font-medium text-gray-950 dark:text-white">{{ $record->user?->name ?? 'System' }}</div>
            @if ($record->user?->email)
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $record->user->email }}</div>
            @endif
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Waktu</div>
            <div class="mt-1 text-gray-950 dark:text-white">{{ $record->created_at?->format('d M Y H:i:s') }}</div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">IP Address</div>
            <div class="mt-1 text-gray-950 dark:text-white">{{ $record->ip_address ?? '-' }}</div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Request ID</div>
            <div class="mt-1 break-all font-mono text-xs text-gray-950 dark:text-white">{{ $record->request_id ?? '-' }}</div>
        </div>
    </div>

    @if ($record->description)
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Keterangan</div>
            <div class="mt-1 text-gray-950 dark:text-white">{{ $record->description }}</div>
        </div>
    @endif

    <div>
        <div class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Nilai Sebelum</div>
        <pre class="max-h-72 overflow-auto rounded-xl bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($record->old_values ?: new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>

    <div>
        <div class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Nilai Sesudah</div>
        <pre class="max-h-72 overflow-auto rounded-xl bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($record->new_values ?: new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>

    <div>
        <div class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Metadata</div>
        <pre class="max-h-72 overflow-auto rounded-xl bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($record->metadata ?: new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>

    @if ($record->user_agent)
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">User Agent</div>
            <div class="mt-1 break-all text-xs text-gray-700 dark:text-gray-300">{{ $record->user_agent }}</div>
        </div>
    @endif
</div>
