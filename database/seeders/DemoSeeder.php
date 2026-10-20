<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Flags\FlagType;
use App\Flags\FlagWriter;
use App\Flags\SdkKeys;
use App\Models\Flag;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * The run-locally demo (RFC-0001, RFC-0005): an owner, a project with both environments, a flag of
 * each type with rules and a split in development, and an SDK key for each environment.
 */
final class DemoSeeder extends Seeder
{
    public function run(FlagWriter $flags, SdkKeys $keys): void
    {
        if (app()->isProduction()) {
            throw new LogicException('The demo seeder does not run in production.');
        }

        $owner = User::factory()->create([
            'name' => 'Demo Owner',
            'email' => 'demo@flagpole.test',
            'password' => 'password',
        ]);

        $project = $owner->projects()->create(['key' => 'checkout', 'name' => 'Checkout']);

        foreach (Project::STARTING_ENVIRONMENTS as $key => $name) {
            $project->environments()->create(['key' => $key, 'name' => $name]);
        }

        $newCheckout = $flags->create($project, 'new-checkout', 'New checkout', 'The redesigned checkout flow.', FlagType::Boolean, []);
        $this->configure($flags, $newCheckout, [
            'enabled' => true,
            'off_variant' => 'off',
            'default' => ['split' => [['variant' => 'on', 'weight' => 2_500], ['variant' => 'off', 'weight' => 7_500]]],
            'rules' => [[
                'description' => 'Staff see it first',
                'conditions' => [['attribute' => 'email', 'operator' => 'ends_with', 'values' => ['@flagpole.test']]],
                'serve' => ['variant' => 'on'],
            ]],
        ]);

        $theme = $flags->create($project, 'theme', 'Theme colour', null, FlagType::String, [
            ['name' => 'blue', 'value' => '#0055ff'],
            ['name' => 'green', 'value' => '#00aa55'],
        ]);
        $this->configure($flags, $theme, [
            'enabled' => true,
            'off_variant' => 'blue',
            'default' => ['variant' => 'blue'],
            'rules' => [[
                'description' => 'Customers in the UK',
                'conditions' => [['attribute' => 'country', 'operator' => 'in', 'values' => ['GB']]],
                'serve' => ['variant' => 'green'],
            ]],
        ]);

        $basket = $flags->create($project, 'basket-limit', 'Basket limit', 'Most items in one basket.', FlagType::Integer, [
            ['name' => 'standard', 'value' => 20],
            ['name' => 'trade', 'value' => 200],
        ]);
        $this->configure($flags, $basket, [
            'enabled' => true,
            'off_variant' => 'standard',
            'default' => ['variant' => 'standard'],
            'rules' => [[
                'description' => 'Trade accounts',
                'conditions' => [['attribute' => 'plan', 'operator' => 'in', 'values' => ['trade']]],
                'serve' => ['variant' => 'trade'],
            ]],
        ]);

        $discount = $flags->create($project, 'discount-rate', 'Discount rate', null, FlagType::Float, [
            ['name' => 'none', 'value' => 0.0],
            ['name' => 'loyal', 'value' => 0.1],
        ]);
        $this->configure($flags, $discount, [
            'enabled' => true,
            'off_variant' => 'none',
            'default' => ['variant' => 'none'],
            'rules' => [[
                'description' => 'Ten orders or more',
                'conditions' => [['attribute' => 'orders', 'operator' => 'gte', 'values' => [10]]],
                'serve' => ['variant' => 'loyal'],
            ]],
        ]);

        $flags->create($project, 'payment-providers', 'Payment providers', null, FlagType::Object, [
            ['name' => 'cards', 'value' => ['providers' => ['card']]],
            ['name' => 'all', 'value' => ['providers' => ['card', 'paypal', 'klarna']]],
        ]);

        $this->command->info('Owner: demo@flagpole.test, password "password".');

        foreach ($project->environments()->orderBy('key')->get() as $environment) {
            $this->command->info(sprintf('SDK key for %s: %s', $environment->key, $keys->issue($environment, "demo {$environment->key}", null)->plainTextToken));
        }
    }

    /**
     * Sets a flag's settings in development, where the demo's rules live.
     *
     * @param  array{enabled: bool, off_variant: string, default: array<string, mixed>, rules: list<array<string, mixed>>}  $settings
     */
    private function configure(FlagWriter $flags, Flag $flag, array $settings): void
    {
        $development = $flag->settings()->whereHas('environment', static fn(Builder $query) => $query->where('key', 'development'))->sole();

        $flags->updateSettings($development, $settings);
    }
}
