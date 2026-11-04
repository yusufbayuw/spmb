<?php

namespace App\Services;

use App\Filament\Admin\Resources\DocumentResource;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class DocumentUploadOverview
{
    /**
     * Only applicants with an active upload visible to the current staff member.
     * Reuse the document resource's operational and unit visibility boundaries.
     */
    public function query(): Builder
    {
        return Registration::query()
            ->whereIn('id', DocumentResource::getEloquentQuery()->select('registration_id'));
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    /** @return LengthAwarePaginator<Registration> */
    public function applicants(string $search = '', int $page = 1): LengthAwarePaginator
    {
        return $this->query()
            ->with(['configuration', 'unit', 'documents'])
            ->when(trim($search) !== '', function (Builder $query) use ($search): void {
                $term = trim($search);
                $query->where(function (Builder $matched) use ($term): void {
                    $matched->where('full_name', 'like', '%'.$term.'%')
                        ->orWhere('registration_number', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate(20, ['*'], 'uploadPage', max(1, $page));
    }

    /** @return array{uploaded:int,total:int,rejected:int,pending:int,verified:int} */
    public function summary(Registration $registration): array
    {
        $definitions = $registration->documentRequirements();
        $allowed = array_fill_keys(array_column($definitions, 'key'), true);
        $documents = $registration->documents;

        $uploaded = [];
        $rejected = 0;
        $pending = 0;
        $verified = 0;

        foreach ($documents as $document) {
            $key = $document->requirement_key ?: $document->type;
            if (! isset($allowed[$key])) {
                continue;
            }

            $uploaded[$key] = true;
            if ($document->is_verified) {
                $verified++;
            } elseif (filled($document->rejection_reason)) {
                $rejected++;
            } else {
                $pending++;
            }
        }

        return [
            'uploaded' => count($uploaded),
            'total' => count($definitions),
            'rejected' => $rejected,
            'pending' => $pending,
            'verified' => $verified,
        ];
    }
}
