<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Notifications\PositionChangedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Adds or changes a position in the global catalog (Property Bible). A position
 * name is shared by every property, work order and invoice line, so each
 * change goes to the Audit Log in plain words, and when someone other than an
 * Admin adds or renames one, the Admins are told.
 */
class SavePosition
{
    /** Roles that run the catalog themselves: their changes aren't announced. */
    private const ADMIN_ROLES = ['admin', 'super_admin'];

    /**
     * @param  array{name: string, notes: string|null, is_active: bool}  $data
     */
    public function handle(?Position $position, array $data, Person $by): Position
    {
        if ($position === null) {
            $position = Position::create([...$data, 'slug' => $this->uniqueSlug($data['name'])]);
            $this->log($position, $by, 'created', "Added the position \"{$position->name}\"");
            $this->tellAdmins($position, $by, null);

            return $position;
        }

        $oldName = $position->name;
        // Slug stays stable across renames — it's an internal identifier only.
        $position->update($data);

        $changes = [];
        if ($position->wasChanged('name')) {
            $changes[] = "Renamed the position \"{$oldName}\" to \"{$position->name}\"";
        }
        if ($position->wasChanged('is_active')) {
            $changes[] = ($position->is_active ? 'Made active again' : 'Made inactive').": \"{$position->name}\"";
        }
        if ($position->wasChanged('notes')) {
            $changes[] = "Changed the notes on \"{$position->name}\"";
        }

        if ($changes !== []) {
            $this->log($position, $by, 'updated', implode('; ', $changes));
        }
        if ($position->wasChanged('name')) {
            $this->tellAdmins($position, $by, $oldName);
        }

        return $position;
    }

    private function log(Position $position, Person $by, string $event, string $description): void
    {
        activity('property_bible')->performedOn($position)->causedBy($by)->event($event)->log($description);
    }

    private function tellAdmins(Position $position, Person $by, ?string $oldName): void
    {
        if ($by->hasAnyRole(self::ADMIN_ROLES)) {
            return;
        }

        $admins = Person::query()->role(self::ADMIN_ROLES)->whereKeyNot($by->id)->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new PositionChangedNotification($position, $by, $oldName));
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Position::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
