<?php

declare(strict_types=1);

use App\Flags\FlagWriter;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use App\Webhooks\DeliverWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    $this->user = User::factory()->create();
    WebhookSubscription::factory()->for($this->user)->create(['events' => ['flag.created', 'flag.updated', 'flag.archived', 'flag.restored', 'flag.settings.updated']]);
    $this->project = projectWithEnvironments($this->user);
});

it('sends an event for each change, saying what changed and not what it changed to', function (): void {
    $writer = app(FlagWriter::class);
    $flag = flagIn($this->project);
    $writer->update($flag, ['name' => 'Renamed']);
    setSettings($flag, 'production', ['enabled' => true, 'rules' => [['conditions' => [['attribute' => 'email', 'operator' => 'ends_with', 'values' => ['@example.com']]], 'serve' => ['variant' => 'on']]]]);
    $writer->archive($flag);
    $writer->restore($flag);

    $events = WebhookEvent::query()->orderBy('id')->get();

    expect($events->pluck('type')->all())->toBe(['flag.created', 'flag.updated', 'flag.settings.updated', 'flag.archived', 'flag.restored'])
        ->and($events->pluck('subject')->unique()->all())->toBe(['checkout/new-checkout'])
        ->and($events[0]->data)->toBe(['project' => 'checkout', 'flag' => 'new-checkout', 'name' => 'A flag', 'type' => 'boolean'])
        ->and($events[1]->data)->toBe(['project' => 'checkout', 'flag' => 'new-checkout', 'changed' => ['name']])
        ->and($events[2]->data)->toBe(['project' => 'checkout', 'flag' => 'new-checkout', 'environment' => 'production', 'enabled' => true, 'changed' => ['enabled', 'rules']])
        ->and(json_encode($events[2]->data))->not->toContain('example.com');

    Queue::assertPushed(DeliverWebhook::class, 5);
});

it('sends nothing for a change that changes nothing', function (): void {
    $flag = flagIn($this->project);
    setSettings($flag, 'production', ['enabled' => false]);

    expect(WebhookEvent::query()->pluck('type')->all())->toBe(['flag.created']);
});

it('sends nothing to another owner\'s subscriptions', function (): void {
    $other = User::factory()->create();
    WebhookSubscription::factory()->for($other)->create(['events' => ['*']]);

    flagIn($this->project);

    expect(WebhookEvent::query()->where('user_id', $other->id)->exists())->toBeFalse();
});
