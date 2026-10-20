<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use JustSteveKing\Toolbox\ConditionalRequests\Concerns\HasModelValidators;

/**
 * ETags from a revision column that every write increments, as api-toolbox advises for optimistic
 * concurrency: one built from updated_at is only as fine as the database's timestamps, and MySQL
 * keeps whole seconds, so two writes in the same second would share an ETag.
 *
 * @phpstan-require-extends Model
 */
trait HasRevision
{
    use HasModelValidators;

    public static function bootHasRevision(): void
    {
        // "updating" only fires when something changed, so a save that changes nothing keeps the
        // revision, and with it the ETag.
        static::updating(static function (Model $model): void {
            $revision = $model->getAttribute('revision');

            $model->setAttribute('revision', (is_int($revision) ? $revision : 0) + 1);
        });
    }

    protected function entityTagVersion(): ?string
    {
        $revision = $this->getAttribute('revision');

        return is_int($revision) ? (string) $revision : null;
    }
}
