<?php

namespace App\Services\Tenancy;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TenantIdentityStorage
{
    /**
     * @return array{
     *     identity_document_path: string,
     *     identity_document_original_name: string,
     *     identity_document_mime: string|null,
     *     identity_document_size: int,
     *     identity_document_uploaded_at: mixed
     * }
     */
    public function store(UploadedFile $document): array
    {
        $path = $document->store('tenant-identities/'.now()->format('Y/m'), 'local');

        if ($path === false) {
            throw new \RuntimeException('The identity document could not be stored.');
        }

        return [
            'identity_document_path' => $path,
            'identity_document_original_name' => $document->getClientOriginalName(),
            'identity_document_mime' => $document->getMimeType(),
            'identity_document_size' => $document->getSize(),
            'identity_document_uploaded_at' => now(),
        ];
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }
}
