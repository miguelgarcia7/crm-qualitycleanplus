<?php

namespace App\Http\Controllers;

use App\Domain\PropertyBible\Actions\UploadContract;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Http\Requests\Property\StoreContractRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    use LogsPropertyActivity;

    public function store(StoreContractRequest $request, Property $property, UploadContract $action): RedirectResponse
    {
        $action->handle($property, $request->validated(), $request->file('document'), $request->user());

        return back()->with('success', 'Contract uploaded.');
    }

    public function download(Property $property, Contract $contract): StreamedResponse
    {
        abort_unless($contract->property_id === $property->id, 404);
        $this->authorize('download', $contract);

        $file = $contract->file;
        abort_unless($file !== null && Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function destroy(Property $property, Contract $contract): RedirectResponse
    {
        abort_unless($contract->property_id === $property->id, 404);
        $this->authorize('delete', $contract);

        $file = $contract->file;
        if ($file !== null && Storage::disk($file->disk)->exists($file->path)) {
            Storage::disk($file->disk)->delete($file->path);
        }
        $file?->delete();
        $contract->delete();

        $this->logProperty($property, 'updated', "Deleted contract \"{$contract->name}\"");

        return back()->with('success', 'Contract deleted.');
    }
}
