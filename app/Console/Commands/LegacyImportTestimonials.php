<?php

namespace App\Console\Commands;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use App\Domain\Marketing\Actions\UpdateTestimonialPhoto;
use App\Domain\Marketing\Enums\TestimonialSource;
use App\Domain\Marketing\Models\Testimonial;
use Illuminate\Console\Command;
use Illuminate\Http\File as LocalFile;
use Illuminate\Support\Facades\DB;

/**
 * Copies the legacy Quality Cleaning Plus CMS testimonials into this app
 * (marketing-site-audit.md, D2) over the read-only `legacy_qcp` connection.
 *
 * Idempotent via legacy_id_map (entity `qcp_testimonial`): a re-run refreshes
 * the text, rating and visibility from legacy — overwriting edits made here —
 * and adds any new rows. A photo is copied once; after that this app owns it.
 *
 * Legacy photos are site-relative paths (`/media/2024/08/x.jpg`) under the
 * legacy app's `public/` directory, so `--media-root` points at a copy of it.
 * A photo that can't be found is reported and the testimonial imported without.
 */
class LegacyImportTestimonials extends Command
{
    protected $signature = 'legacy:import-testimonials
        {--media-root= : The legacy site\'s public/ directory, where /media/... photo paths resolve}';

    protected $description = 'Import testimonials from the legacy Quality Cleaning Plus database';

    private const ENTITY = 'qcp_testimonial';

    public function handle(LegacyIdMap $map, UpdateTestimonialPhoto $photos): int
    {
        $mediaRoot = $this->option('media-root');
        $mediaRoot = is_string($mediaRoot) && $mediaRoot !== '' ? rtrim($mediaRoot, '/') : null;

        $rows = DB::connection('legacy_qcp')->table('testimonials')->orderBy('id')->get();
        $created = $updated = $copied = 0;
        $missing = [];

        foreach ($rows as $row) {
            $existingId = $map->newId(self::ENTITY, (int) $row->id);
            $testimonial = ($existingId !== null ? Testimonial::query()->find($existingId) : null) ?? new Testimonial;
            $isNew = ! $testimonial->exists;

            $testimonial->fill([
                'name' => trim((string) $row->name),
                'quote' => trim((string) $row->quote),
                'source' => (TestimonialSource::tryFrom(strtolower(trim((string) $row->social_media))) ?? TestimonialSource::Google)->value,
                'rating' => max(1, min(5, (int) $row->rating)),
                'is_active' => (int) $row->status === 1,
            ]);
            // Keep legacy dates: the site lists newest first, as legacy did.
            $testimonial->created_at = $row->created_at;
            $testimonial->updated_at = $row->updated_at ?? $row->created_at;
            $testimonial->save();

            $map->remember(self::ENTITY, (int) $row->id, $testimonial->id);
            $isNew ? $created++ : $updated++;

            $photo = trim((string) $row->photo);
            if ($photo === '' || $testimonial->photo_file_id !== null) {
                continue;
            }

            $path = $mediaRoot === null ? null : $mediaRoot.'/'.ltrim($photo, '/');
            if ($path === null || ! is_file($path)) {
                $missing[] = "#{$row->id} {$row->name}: {$photo}";

                continue;
            }

            $photos->handle($testimonial, new LocalFile($path));
            $copied++;
        }

        $this->info("Testimonials: {$created} created, {$updated} updated, {$copied} photos copied.");

        if ($missing !== []) {
            $this->warn(count($missing).' photo(s) not found'.($mediaRoot === null ? ' (no --media-root given)' : " under {$mediaRoot}").':');
            foreach ($missing as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }
}
