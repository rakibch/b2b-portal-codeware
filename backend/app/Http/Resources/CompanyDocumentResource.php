<?php

namespace App\Http\Resources;

use App\Models\CompanyDocument;
use Illuminate\Http\Request;

/** @mixin CompanyDocument */
class CompanyDocumentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->enum($this->type),
            'status' => $this->enum($this->status),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'uploaded_at' => $this->created_at?->toIso8601String(),
            // Authorised, authenticated endpoint — never a public storage URL.
            'download_path' => '/api/v1/documents/'.$this->id.'/download',
        ];
    }
}
