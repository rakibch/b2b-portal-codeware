<?php

namespace App\Services\Kyc;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores KYC files on a PRIVATE disk with server-generated names (client filename is never used
 * as a path), extension derived from the detected MIME type, and a sha256 checksum.
 */
class KycDocumentStorage
{
    /** @var list<array{disk: string, path: string}> files written in the current unit of work */
    private array $written = [];

    public function store(Company $company, UploadedFile $file, DocumentType $type, ?User $uploader): CompanyDocument
    {
        $disk = (string) config('b2b.kyc.disk');
        $extension = $file->guessExtension() ?: 'bin';
        $directory = "kyc/{$company->public_id}";
        $name = Str::uuid()->toString().'.'.$extension;

        $path = Storage::disk($disk)->putFileAs($directory, $file, $name);

        if ($path === false) {
            throw new \RuntimeException('Failed to store KYC document.');
        }

        $this->written[] = ['disk' => $disk, 'path' => $path];

        $document = new CompanyDocument([
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $this->safeName($file->getClientOriginalName(), $extension),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => (int) $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $uploader?->id,
        ]);
        $document->forceFill(['status' => DocumentStatus::Pending]);
        $company->documents()->save($document);

        return $document;
    }

    /** Removes files written so far (call when the surrounding DB transaction fails). */
    public function rollback(): void
    {
        foreach ($this->written as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }

        $this->written = [];
    }

    public function commit(): void
    {
        $this->written = [];
    }

    private function safeName(string $original, string $extension): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $base = preg_replace('/[^\pL\pN._ -]+/u', '', $base) ?: 'document';

        return mb_substr(trim($base), 0, 150).'.'.$extension;
    }
}
