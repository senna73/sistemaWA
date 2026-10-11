<?php

use App\Models\AgendaItem;
use App\Models\AgendaSchedule;
use App\Models\User;
use App\Services\Rh\AgendaService;
use App\Support\AccessControl;
use App\Support\SidebarMenu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

function agendaActor(string $role, array $extra = []): User
{
    AccessControl::seed();
    $user = User::factory()->create(array_merge(['role' => $role, 'active' => true], $extra));
    AccessControl::applyToUser($user, $role);

    return $user->fresh();
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-11 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates a monthly agenda and materializes the activity once', function () {
    $director = agendaActor('super_admin');

    $this->actingAs($director)
        ->post(route('agenda.schedules.store'), [
            'title' => 'Folha de pagamento',
            'work_function' => 'admin_finance',
            'recurrence' => 'monthly',
            'anchor_day' => 15,
            'due_time' => '18:00',
            'priority' => 'alta',
            'effort_hours' => 4,
            'active' => '1',
        ])
        ->assertRedirect();

    $schedule = AgendaSchedule::query()->first();
    expect($schedule)->not->toBeNull()
        ->and($schedule->work_function)->toBe('admin_finance')
        ->and($schedule->effort_hours)->toEqual('4.00');

    Artisan::call('rh:materialize-agenda-schedules');
    Artisan::call('rh:materialize-agenda-schedules');

    $items = AgendaItem::query()->get();
    expect($items)->toHaveCount(1);
    expect($items->first()->due_at->format('Y-m-d H:i'))->toBe('2026-10-15 18:00');
    expect($items->first()->assignee_id)->toBeNull();
    expect($items->first()->recurrence)->toBe('once');

    app(AgendaService::class)->updateStatus($items->first(), $director, AgendaItem::STATUS_DONE);

    expect(AgendaItem::query()->count())->toBe(1);
});

it('shows the activity only to the sector and keeps agenda setup with the director', function () {
    $director = agendaActor('super_admin');
    $finance = agendaActor('employee');
    $finance->update(['work_function' => 'admin_finance']);
    $recruitment = agendaActor('employee');
    $recruitment->update(['work_function' => 'recruitment']);

    AgendaItem::query()->create([
        'title' => 'Fechar benefícios',
        'assignee_id' => null,
        'created_by' => $director->id,
        'due_at' => '2026-10-20 18:00:00',
        'priority' => 'normal',
        'type' => 'routine',
        'status' => AgendaItem::STATUS_PENDING,
        'recurrence' => 'once',
        'work_function' => 'admin_finance',
    ]);

    $this->actingAs($finance)
        ->get(route('agenda.index'))
        ->assertForbidden();

    $this->actingAs($finance)
        ->get(route('agenda.board'))
        ->assertOk()
        ->assertSee('Fechar benefícios')
        ->assertDontSee('Agendas da plataforma');

    $labels = collect(SidebarMenu::items($finance->fresh(), Request::create('/agenda/produtividade', 'GET')))
        ->flatMap(fn (array $item) => $item['children'] ?? [$item])
        ->pluck('label');
    expect($labels)->toContain('Produtividade')
        ->and($labels)->not->toContain('Agenda');

    $this->actingAs($recruitment)
        ->get(route('agenda.board'))
        ->assertOk()
        ->assertDontSee('Fechar benefícios');

    $this->actingAs(agendaActor('employee'))
        ->get(route('agenda.board'))
        ->assertForbidden();

    $directorLabels = collect(SidebarMenu::items($director->fresh(), Request::create('/agenda', 'GET')))
        ->flatMap(fn (array $item) => $item['children'] ?? [$item])
        ->pluck('label');
    expect($directorLabels)->toContain('Agenda')
        ->and($directorLabels)->toContain('Produtividade');
});

it('puts a manual activity on the sector queue', function () {
    $director = agendaActor('super_admin');

    $this->actingAs($director)
        ->post(route('agenda.store'), [
            'title' => 'Conferir benefícios',
            'work_function' => 'people_management',
            'type' => 'routine',
            'recurrence' => 'once',
        ])
        ->assertRedirect();

    $item = AgendaItem::query()->first();
    expect($item)->not->toBeNull()
        ->and($item->title)->toBe('Conferir benefícios')
        ->and($item->assignee_id)->toBeNull()
        ->and($item->work_function)->toBe('people_management');
});

it('lets the director transfer the responsible inside the function', function () {
    $director = agendaActor('super_admin');
    $ana = agendaActor('employee', ['name' => 'Ana Oliveira']);
    $ana->update(['work_function' => 'admin_finance']);
    $other = agendaActor('employee');
    $other->update(['work_function' => 'recruitment']);

    $item = AgendaItem::query()->create([
        'title' => 'Análise de contrato',
        'created_by' => $director->id,
        'due_at' => '2026-10-12 18:00:00',
        'priority' => 'alta',
        'type' => 'routine',
        'status' => AgendaItem::STATUS_PENDING,
        'recurrence' => 'once',
        'work_function' => 'admin_finance',
    ]);

    $this->actingAs($director)
        ->post(route('agenda.assign', $item), ['assignee_id' => $ana->id])
        ->assertRedirect();

    expect($item->fresh()->assignee_id)->toBe($ana->id);

    $this->actingAs($director)
        ->post(route('agenda.assign', $item), ['assignee_id' => $other->id])
        ->assertSessionHasErrors('assignee_id');

    $this->actingAs($director)
        ->post(route('agenda.correction', $item), ['note' => 'Revisar o anexo'])
        ->assertRedirect();

    expect($item->fresh()->status)->toBe(AgendaItem::STATUS_IN_REVIEW)
        ->and($item->fresh()->correction_note)->toBe('Revisar o anexo');
});

it('counts scheduled occurrences and late activities in the month', function () {
    $director = agendaActor('super_admin');

    AgendaSchedule::query()->create([
        'title' => 'Fechamento do mês',
        'work_function' => 'operational_control',
        'recurrence' => 'monthly',
        'anchor_day' => 28,
        'due_time' => '18:00',
        'priority' => 'normal',
        'active' => true,
        'created_by' => $director->id,
    ]);

    AgendaItem::query()->create([
        'title' => 'Escala atrasada',
        'created_by' => $director->id,
        'due_at' => '2026-10-10 18:00:00',
        'priority' => 'normal',
        'type' => 'routine',
        'status' => AgendaItem::STATUS_PENDING,
        'recurrence' => 'once',
        'work_function' => 'operational_control',
    ]);

    $this->actingAs($director)
        ->get(route('agenda.board', ['period' => 'month', 'on' => '2026-10-11']))
        ->assertOk()
        ->assertViewHas('indicators', function (array $indicators) {
            return $indicators['scheduled'] === 1
                && $indicators['late'] === 1
                && $indicators['pending'] === 0;
        });

    $this->actingAs($director)
        ->get(route('agenda.index', ['period' => 'month', 'on' => '2026-10-11']))
        ->assertOk()
        ->assertSee('Fechamento do mês')
        ->assertSee('Analista de Controle Operacional')
        ->assertSee('Nova atividade');
});

it('updates the responsible and status without reloading the page', function () {
    $director = agendaActor('super_admin');
    $ana = agendaActor('employee', ['name' => 'Ana Oliveira']);
    $ana->update(['work_function' => 'admin_finance']);

    $item = AgendaItem::query()->create([
        'title' => 'Conferir ponto',
        'created_by' => $director->id,
        'due_at' => '2026-10-20 18:00:00',
        'priority' => 'normal',
        'type' => 'routine',
        'status' => AgendaItem::STATUS_PENDING,
        'recurrence' => 'once',
        'work_function' => 'admin_finance',
    ]);

    $this->actingAs($director)
        ->get(route('agenda.board'))
        ->assertOk()
        ->assertSee('data-agenda-form="card"', false)
        ->assertSee('id="agenda-app"', false);

    $this->actingAs($director)
        ->postJson(route('agenda.assign', $item), ['assignee_id' => $ana->id])
        ->assertOk()
        ->assertJsonPath('message', 'Responsável atualizado.')
        ->assertJsonPath('refresh', false)
        ->assertJsonPath('item.assignee_name', 'Ana Oliveira')
        ->assertJsonPath('item.column', 'pending');

    $this->actingAs($director)
        ->postJson(route('agenda.status', $item), ['status' => AgendaItem::STATUS_IN_PROGRESS])
        ->assertOk()
        ->assertJsonPath('item.status', AgendaItem::STATUS_IN_PROGRESS)
        ->assertJsonPath('item.column', 'in_progress')
        ->assertJsonPath('item.remove', false);

    $this->actingAs($director)
        ->postJson(route('agenda.correction', $item), ['note' => 'Ajustar o anexo'])
        ->assertOk()
        ->assertJsonPath('item.status', AgendaItem::STATUS_IN_REVIEW)
        ->assertJsonPath('item.column', 'in_review')
        ->assertJsonPath('item.correction_note', 'Ajustar o anexo');

    $this->actingAs($director)
        ->get(route('agenda.board'), ['X-Agenda-Fragment' => '1'])
        ->assertOk()
        ->assertSee('Produtividade')
        ->assertDontSee('<!DOCTYPE html>', false);
});
