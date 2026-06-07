<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Http\UploadedFile;

class UploadContract
{
    use LogsPropertyActivity;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Property $property, array $data, UploadedFile $document, ?Person $uploader): Contract
    {
        $path = $document->store('contracts', 'local');

        $contract = $property->contracts()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'effective_date' => $data['effective_date'] ?? null,
            'expiration_date' => $data['expiration_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'uploaded_by' => $uploader?->id,
        ]);

        $contract->file()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => $document->getClientOriginalName(),
            'mime_type' => $document->getClientMimeType(),
            'size' => $document->getSize(),
            'uploaded_by' => $uploader?->id,
        ]);

        $this->logProperty($property, 'updated', "Uploaded contract \"{$contract->name}\"");

        return $contract;
    }
}
