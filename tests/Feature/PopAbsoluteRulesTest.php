<?php

use App\Models\AccountingListCheck;
use App\Models\AccountingListRow;
use App\Models\AgendaItem;
use App\Models\Collaborator;
use App\Models\Company;
use App\Models\DailyRate;
use App\Models\FinancialBatches;
use App\Models\InactivityAudit;
use App\Models\MedicalClinic;
use App\Models\OffboardingProcess;
use App\Models\OperationalDemand;
use App\Models\Section;
use App\Models\User;
use App\Services\Rh\AccountingListParser;
use App\Services\Rh\AccountingListService;
use App\Services\Rh\AccountingReconciler;
use App\Services\Rh\AgendaService;
use App\Services\Rh\ClinicPanelService;
use App\Services\Rh\CliomedReconciler;
use App\Services\Rh\DailyRateReleaseService;
use App\Services\Rh\InactiveCollaboratorDetector;
use App\Support\AccessControl;
use App\Support\PopCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function popUser(string $role): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => $role]);
    AccessControl::applyToUser($user, $role);

    return $user->fresh();
}

it('parses hundreds of SCI accounting rows without mutating data', function () {
    $lines = ['Relação por tempo de serviço'];
    for ($i = 1; $i <= 722; $i++) {
        $code = str_pad((string) $i, 6, '0', STR_PAD_LEFT);
        $lines[] = '1 ano  e 1 meses 16/04/2024PESSOA TESTE '.$code.$code;
    }
    $lines[] = 'Total de colaboradores: 722';

    $rows = app(AccountingListParser::class)->fromText(implode("\n", $lines));

    expect($rows)->toHaveCount(722);
    expect($rows[0]['code'])->toBe('000001');
    expect($rows[721]['code'])->toBe('000722');
    expect($rows[0]['admission_on'])->toBe('2024-04-16');
});

it('parses the SCI accounting list format', function () {
    $text = <<<'TXT'
Relação por tempo de serviço
2 anos  e 5 meses 16/04/2024ALCENIR BRUNETTO000001
 1 ano  e 6 meses 13/03/2025LUCAS FELIPE COLLAÇO PONTES000018
17/09/2026JOÃO GABRIEL DA COSTA FERNANDES001219
Total de colaboradores: 3
TXT;

    $rows = app(AccountingListParser::class)->fromText($text);

    expect($rows)->toHaveCount(3);
    expect($rows[0]['code'])->toBe('000001');
    expect($rows[0]['name'])->toBe('ALCENIR BRUNETTO');
    expect($rows[0]['admission_on'])->toBe('2024-04-16');
    expect($rows[2]['code'])->toBe('001219');
});

it('does not mutate collaborators on accounting list upload and applies one by one', function () {
    Storage::fake('local');
    $rh = popUser('rh');
    $keep = Collaborator::factory()->create([
        'name' => 'Alcenir Brunetto',
        'hired_at' => '2020-01-01',
        'accounting_code' => null,
        'active' => true,
    ]);
    $extra = Collaborator::factory()->create(['name' => 'So Na WA', 'active' => true]);

    $csv = "codigo,data admissao,colaborador\n000001,16/04/2024,ALCENIR BRUNETTO\n000002,13/03/2025,PESSOA NOVA DA LISTA\n";
    $file = UploadedFile::fake()->createWithContent('lista.csv', $csv);

    $this->actingAs($rh)
        ->post(route('work.accounting.store'), ['attachment' => $file])
        ->assertRedirect(route('work.accounting'));

    expect($keep->fresh()->hired_at?->toDateString())->toBe('2020-01-01');
    expect($extra->fresh()->active)->toBeTrue();

    $hiredRow = AccountingListRow::query()->where('bucket', AccountingListRow::BUCKET_HIRED_AT)->first();
    $onlyWa = AccountingListRow::query()->where('bucket', AccountingListRow::BUCKET_ONLY_WA)->where('collaborator_id', $extra->id)->first();
    $onlyList = AccountingListRow::query()->where('bucket', AccountingListRow::BUCKET_ONLY_LIST)->first();

    expect($hiredRow)->not->toBeNull();
    expect($onlyWa)->not->toBeNull();
    expect($onlyList)->not->toBeNull();

    $this->actingAs($rh)->post(route('work.accounting.apply', $hiredRow))->assertRedirect();
    expect($keep->fresh()->hired_at->toDateString())->toBe('2024-04-16');
    expect($keep->fresh()->accounting_code)->toBe('000001');

    $this->actingAs($rh)->post(route('work.accounting.apply', $onlyWa))->assertRedirect();
    expect($extra->fresh()->active)->toBeFalse();
});

it('breaks accounting duplicates with the most recent daily', function () {
    $a = Collaborator::factory()->create(['name' => 'Nome Igual']);
    $b = Collaborator::factory()->create(['name' => 'Nome Igual']);
    $section = Section::query()->create(['name' => 'Padaria']);
    DailyRate::forceCreate([
        'collaborator_id' => $b->id,
        'section_id' => $section->id,
        'start' => now()->subDay(),
        'end' => now()->subDay()->addHours(8),
        'active' => true,
        'status' => 'criado',
    ]);

    $rows = app(AccountingReconciler::class)->compare([
        ['code' => '000099', 'name' => 'Nome Igual', 'admission_on' => now()->toDateString()],
    ]);

    $match = collect($rows)->firstWhere('code', '000099');
    expect($match['collaborator_id'])->toBe($b->id);
    expect($match['bucket'])->toBe(AccountingListRow::BUCKET_HIRED_AT);
});

it('uses hired_at as admission and not created_at', function () {
    $collaborator = Collaborator::factory()->create([
        'created_at' => now()->subYears(2),
        'hired_at' => now()->subDays(10),
    ]);

    expect($collaborator->hiredAt()->toDateString())->toBe(now()->subDays(10)->toDateString());
    expect($collaborator->tenureDays())->toBe(10);

    $blank = Collaborator::factory()->create(['hired_at' => null, 'created_at' => now()->subDays(40)]);
    expect($blank->hiredAt())->toBeNull();
    expect($blank->tenureDays())->toBe(0);
});

it('sends 18 day justification to rh analysis and continue does not pause the clock', function () {
    $this->mock(\App\Services\WhatsApp\WhatsAppNotifier::class, function ($mock) {
        $mock->shouldIgnoreMissing();
    });
    popUser('rh');
    $coordinator = popUser('coordinator');
    $collaborator = Collaborator::factory()->create([
        'hired_at' => now()->subDays(20),
        'created_at' => now()->subDays(20),
    ]);

    app(InactiveCollaboratorDetector::class)->detect();
    $audit = InactivityAudit::query()->where('collaborator_id', $collaborator->id)->first();
    expect($audit?->status)->toBe(InactivityAudit::STATUS_OPEN_18);

    $this->actingAs($coordinator)
        ->post(route('work.inactivity.response', $audit), [
            'coordinator_response' => 'continuar',
        ])
        ->assertRedirect();

    expect($audit->fresh()->status)->toBe(InactivityAudit::STATUS_WATCH);
    expect($audit->fresh()->coordinator_response)->toBe(InactivityAudit::RESPONSE_CONTINUE);

    $this->actingAs($coordinator)
        ->post(route('work.inactivity.response', $audit->fresh()), [
            'coordinator_response' => 'justificativa',
            'justification' => 'doenca',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('rh_tasks', [
        'collaborator_id' => $collaborator->id,
        'type' => \App\Models\RhTask::TYPE_INACTIVITY,
        'status' => \App\Models\RhTask::STATUS_PENDING,
    ]);
});

it('blocks inactive daily rates until an approved release and reactivates after the daily', function () {
    $rh = popUser('rh');
    $leader = popUser('coordinator');
    $collaborator = Collaborator::factory()->create(['active' => false, 'hired_at' => now()->subDays(40)]);
    $company = Company::query()->create(['name' => 'Loja Libera', 'coordinator_value' => 0]);

    $block = app(DailyRateReleaseService::class)->blockReason($collaborator->id, now());
    expect($block['code'])->toBe('inactive');

    $this->actingAs($leader)
        ->post(route('work.releases.store'), [
            'collaborator_id' => $collaborator->id,
            'company_id' => $company->id,
            'daily_on' => now()->toDateString(),
            'reason' => 'diária feita e não lançada',
        ])
        ->assertRedirect();

    $release = \App\Models\DailyRateReleaseRequest::query()->first();
    $this->actingAs($rh)
        ->post(route('work.releases.decide', $release), ['decision' => 'approve'])
        ->assertRedirect();

    expect(app(DailyRateReleaseService::class)->blockReason($collaborator->id, now()))->toBeNull();

    app(DailyRateReleaseService::class)->afterDailyCreated($collaborator->id, now());
    expect($collaborator->fresh()->active)->toBeTrue();
});

it('applies cliomed clinic rules and builds a names-only charging pdf', function () {
    $rh = popUser('rh');
    $cliomed = MedicalClinic::query()->create(['name' => 'Cliomed Centro', 'active' => true]);
    $conserta = MedicalClinic::query()->create(['name' => 'Conserta Norte', 'active' => true]);
    $wrong = Collaborator::factory()->create(['name' => 'Clinica Errada', 'examined_medical_clinic_id' => $conserta->id]);
    $onlyWa = Collaborator::factory()->create(['name' => 'So No WA', 'examined_medical_clinic_id' => $cliomed->id]);
    Collaborator::factory()->create(['name' => 'Inativo Relatorio', 'active' => false, 'examined_medical_clinic_id' => $cliomed->id]);

    $result = app(CliomedReconciler::class)->compare([
        ['name' => 'Clinica Errada', 'unit' => null, 'sector' => null, 'role' => null],
        ['name' => 'Inativo Relatorio', 'unit' => null, 'sector' => null, 'role' => null],
    ]);

    $check = app(ClinicPanelService::class)->ensureWeeklyCheck();
    $check->update(['reconciliation' => $result, 'report_count' => 2]);

    $service = app(ClinicPanelService::class);
    foreach ($service->pendingInconsistencies($check->fresh()) as $item) {
        $service->resolveInconsistency($check->fresh(), $item['_key']);
    }

    expect($wrong->fresh()->clinicSlug())->toBe(OffboardingProcess::CLINIC_CLIOMED);
    expect($onlyWa->fresh()->clinicSlug())->toBe(OffboardingProcess::CLINIC_CONSERTA);

    $this->actingAs($rh)
        ->get(route('work.cliomed.charge'))
        ->assertOk();

    $names = $service->chargingNames($check->fresh());
    expect($names)->toContain('Inativo Relatorio');
    expect($names)->not->toContain('Clinica Errada');
});

it('opens collaborator data from the dossier and returns to the same process', function () {
    $rh = popUser('rh');
    $collaborator = Collaborator::factory()->create(['name' => 'Ficha Completa', 'hired_at' => now()->subDays(200)]);
    $process = OffboardingProcess::query()->create([
        'collaborator_id' => $collaborator->id,
        'requested_by_user_id' => $rh->id,
        'kind' => OffboardingProcess::KIND_DISMISSAL,
        'origin' => OffboardingProcess::ORIGIN_COORDINATOR,
        'status' => OffboardingProcess::STAGE_ATENDIMENTO_RH,
    ]);

    $this->actingAs($rh)
        ->get(route('work.offboarding.show', $process))
        ->assertOk()
        ->assertSee('Ver dados do colaborador');

    $this->actingAs($rh)
        ->get(route('work.offboarding.collaborator', $process))
        ->assertOk()
        ->assertSee('Ficha Completa')
        ->assertSee('Voltar para o dossiê');
});

it('opens a pix demand when payment is in progress', function () {
    $rh = popUser('rh');
    $user = popUser('collaborator');
    $collaborator = Collaborator::factory()->create(['pix_key' => 'antiga']);
    $user->collaborator_id = $collaborator->id;
    $user->save();
    $company = Company::query()->create(['name' => 'Loja Pix', 'coordinator_value' => 0]);
    $section = Section::query()->create(['name' => 'Caixa']);
    DailyRate::forceCreate([
        'collaborator_id' => $collaborator->id,
        'company_id' => $company->id,
        'section_id' => $section->id,
        'start' => now(),
        'active' => true,
        'status' => 'criado',
    ]);
    FinancialBatches::query()->create([
        'company_id' => $company->id,
        'total_amount' => 10,
        'remaining_amount' => 10,
        'period_start' => now()->subDay(),
        'period_end' => now()->addDay(),
        'status' => 'processing',
    ]);

    $this->actingAs($user->fresh())
        ->put(route('portal.update'), ['pix_key' => 'nova-chave'])
        ->assertRedirect();

    expect($collaborator->fresh()->pix_key)->toBe('antiga');
    $this->assertDatabaseHas('operational_demands', [
        'category' => 'troca_pix',
        'collaborator_id' => $collaborator->id,
    ]);
    $this->assertDatabaseHas('agenda_items', [
        'type' => 'collaborator_request',
        'collaborator_id' => $collaborator->id,
        'assignee_id' => $rh->id,
    ]);
});

it('turns a collaborator pix request into a linked activity without changing the key yet', function () {
    $rh = popUser('rh');
    $user = popUser('collaborator');
    $collaborator = Collaborator::factory()->create(['pix_key' => 'chave-atual']);
    $user->collaborator_id = $collaborator->id;
    $user->save();

    $this->actingAs($user->fresh())
        ->post(route('portal.requests.store'), [
            'category' => 'troca_pix',
            'pix_key' => 'chave-nova',
            'request_text' => 'Quero atualizar o Pix',
        ])
        ->assertRedirect();

    expect($collaborator->fresh()->pix_key)->toBe('chave-atual');
    $demand = OperationalDemand::query()->where('collaborator_id', $collaborator->id)->first();
    expect($demand?->category)->toBe('troca_pix');
    expect($demand?->agenda_item_id)->not->toBeNull();
    expect($demand?->payload['pix_key'] ?? null)->toBe('chave-nova');

    $this->actingAs($rh)
        ->post(route('demands.start', $demand))
        ->assertRedirect();
    $this->actingAs($rh)
        ->post(route('demands.review', $demand))
        ->assertRedirect();
    $this->actingAs($rh)
        ->post(route('demands.finish', $demand))
        ->assertRedirect();

    expect($collaborator->fresh()->pix_key)->toBe('chave-nova');
});

it('opens an offboarding group-removal activity linked to the collaborator', function () {
    $rh = popUser('rh');
    $coordinator = popUser('coordinator');
    $collaborator = Collaborator::factory()->create(['name' => 'Sai Do Grupo', 'group' => 'Célula Norte']);

    $process = app(\App\Services\Rh\OffboardingService::class)->open(
        $collaborator,
        $coordinator,
        OffboardingProcess::ORIGIN_COORDINATOR,
        OffboardingProcess::KIND_DISMISSAL
    );

    $item = AgendaItem::query()->where('offboarding_process_id', $process->id)->where('type', 'offboarding_groups')->first();
    expect($item)->not->toBeNull();
    expect($item->collaborator_id)->toBe($collaborator->id);
    expect($process->events()->where('event_type', 'whatsapp_group')->exists())->toBeFalse();

    app(\App\Services\Rh\AgendaService::class)->updateStatus($item, $rh, AgendaItem::STATUS_DONE);

    expect($collaborator->fresh()->group)->toBeNull();
    expect($process->fresh()->events()->where('event_type', 'whatsapp_group')->exists())->toBeTrue();
});

it('refuses agenda assignees who are not rh or coordinator', function () {
    $rh = popUser('rh');
    $employee = popUser('employee');

    expect(fn () => app(AgendaService::class)->create($rh, [
        'title' => 'Rotina',
        'assignee_id' => $employee->id,
        'type' => 'once',
        'due_at' => now(),
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    $item = app(AgendaService::class)->create($rh, [
        'title' => 'Rotina RH',
        'assignee_id' => $rh->id,
        'type' => 'weekly',
        'recurrence' => 'weekly',
        'due_at' => now(),
    ]);

    expect($item)->toBeInstanceOf(AgendaItem::class);
    expect($item->assignee_id)->toBe($rh->id);
});
