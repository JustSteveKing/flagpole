<?php

declare(strict_types=1);

namespace App\Flags\Ofrep;

use App\Models\Environment;
use App\Models\Flag;
use App\Models\FlagSetting;
use Illuminate\Support\Collection;

/**
 * Loads what an evaluation needs, in as few queries as it can: the environment's flags that are
 * not archived, each with its settings there. Nothing is cached in v1 (RFC-0002).
 */
final class EnvironmentFlags
{
    /**
     * @return Collection<int, array{0: Flag, 1: FlagSetting}>
     */
    public function all(Environment $environment): Collection
    {
        return $this->pairs($environment, Flag::query()->where('project_id', $environment->project_id)->whereNull('archived_at')->orderBy('key')->get());
    }

    /**
     * @return array{0: Flag, 1: FlagSetting}|null
     */
    public function one(Environment $environment, string $key): ?array
    {
        $flag = Flag::query()->where('project_id', $environment->project_id)->whereNull('archived_at')->where('key', $key)->first();

        return null === $flag ? null : $this->pairs($environment, collect([$flag]))->first();
    }

    /**
     * @param  Collection<int, Flag>  $flags
     * @return Collection<int, array{0: Flag, 1: FlagSetting}>
     */
    private function pairs(Environment $environment, Collection $flags): Collection
    {
        $settings = FlagSetting::query()
            ->where('environment_id', $environment->id)
            ->whereIn('flag_id', $flags->pluck('id')->all())
            ->get()
            ->keyBy('flag_id');

        $pairs = [];

        foreach ($flags as $flag) {
            $setting = $settings->get($flag->id);

            if ($setting instanceof FlagSetting) {
                $pairs[] = [$flag, $setting];
            }
        }

        return collect($pairs);
    }
}
