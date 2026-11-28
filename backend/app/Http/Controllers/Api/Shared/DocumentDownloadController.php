<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a private KYC document after policy authorisation; every access is audited. */
class DocumentDownloadController extends Controller
{
    public function __invoke(CompanyDocument $document): StreamedResponse
    {
        Gate::authorize('download', $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        AuditLog::record('kyc.document_downloaded', $document->company, [], ['document_id' => $document->id]);

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
