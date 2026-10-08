<?php

use App\Models\DailyRate;
use App\Models\Section;
use App\Models\ClinicPendingCard;
use App\Models\CliomedWeeklyCheck;
use App\Models\Collaborator;
use App\Models\Company;
use App\Models\FinancialBatches;
use App\Models\InactivityAllowance;
use App\Models\InactivityAudit;
use App\Models\MedicalClinic;
use App\Models\OffboardingProcess;
use App\Models\RhTask;
use App\Models\User;
use App\Services\Rh\ClinicPanelService;
use App\Services\Rh\InactiveCollaboratorDetector;
use App\Services\Rh\OffboardingService;
use App\Services\Work\WorkHubService;
use App\Mail\RhProcessMail;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Support\AccessControl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

function makeRoleUser(string $role): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => $role]);
    AccessControl::applyToUser($user, $role);

    return $user->fresh();
}

it('creates an rh task when the collaborator requests dismissal', function () {
    $this->mock(WhatsAppNotifier::class, function (MockInterface $mock) {
        $mock->shouldIgnoreMissing();
        $mock->shouldReceive('notifyCollaboratorAndCoordinator')->once();
        $mock->shouldReceive('removeFromStoreGroups')->andReturn(['result' => 'ok']);
    });

    $collaborator = Collaborator::factory()->create();
    $user = makeRoleUser('collaborator');
    $user->collaborator_id = $collaborator->id;
    $user->save();

    $this->actingAs($user)
        ->post(route('portal.dismissal'), ['notes' => 'Quero sair', 'letter_status' => 'pendente'])
        ->assertRedirect();

    $this->assertDatabaseHas('offboarding_processes', [
        'collaborator_id' => $collaborator->id,
        'origin' => OffboardingProcess::ORIGIN_COLLABORATOR,
        'kind' => OffboardingProcess::KIND_RESIGNATION,
        'status' => OffboardingProcess::STAGE_ATENDIMENTO_RH,
    ]);
    $this->assertDatabaseHas('rh_tasks', [
        'collaborator_id' => $collaborator->id,
        'type' => RhTask::TYPE_ATENDIMENTO,
        'status' => RhTask::STATUS_PENDING,
    ]);
});

it('lets the coordinator open the dedicated offboarding request screen', function () {
    $collaborator = Collaborator::factory()->create(['name' => 'Ana Coord Pedido']);
    $coordinator = makeRoleUser('coordinator');

    $this->actingAs($coordinator)
        ->get(route('work.request'))
        ->assertOk()
        ->assertSee('Solicitar desligamento (coordenador)')
        ->assertSee('pesquise o colaborador');

    $this->actingAs($coordinator)
        ->get(route('work.request', ['q' => 'Ana Coord']))
        ->assertOk()
        ->assertSee('Ana Coord Pedido');

    $this->actingAs($coordinator)
        ->post(route('work.request.store'), [
            'collaborator_id' => $collaborator->id,
            'kind' => 'dismissal',
            'reason' => 'Sem escala na loja',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('offboarding_processes', [
        'collaborator_id' => $collaborator->id,
        'origin' => OffboardingProcess::ORIGIN_COORDINATOR,
        'kind' => OffboardingProcess::KIND_DISMISSAL,
        'requested_by_user_id' => $coordinator->id,
        'status' => OffboardingProcess::STAGE_ATENDIMENTO_RH,
    ]);
    $this->assertDatabaseHas('rh_tasks', [
        'collaborator_id' => $collaborator->id,
        'type' => RhTask::TYPE_ATENDIMENTO,
        'status' => RhTask::STATUS_PENDING,
    ]);
});

it('creates a process when the coordinator requests offboarding', function () {
    $collaborator = Collaborator::factory()->create();
    $coordinator = makeRoleUser('coordinator');

    $this->actingAs($coordinator)
        ->post(route('collaborators.offboarding', $collaborator), ['notes' => 'Sem escala'])
        ->assertRedirect();

    $this->assertDatabaseHas('offboarding_processes', [
        'collaborator_id' => $collaborator->id,
        'origin' => OffboardingProcess::ORIGIN_COORDINATOR,
        'kind' => OffboardingProcess::KIND_DISMISSAL,
        'status' => OffboardingProcess::STAGE_ATENDIMENTO_RH,
        'requested_by_user_id' => $coordinator->id,
    ]);
});

it('opens transfer without blocking daily rates', function () {
    $collaborator = Collaborator::factory()->create();
    $coordinator = makeRoleUser('coordinator');
    $service = app(OffboardingService::class);
    $process = $service->open($collaborator, $coordinator, OffboardingProcess::ORIGIN_COORDINATOR, OffboardingProcess::KIND_TRANSFER, 'Mudança de loja');

    expect($process->kind)->toBe(OffboardingProcess::KIND_TRANSFER);
    expect(OffboardingProcess::blocksDailyRatesFor($collaborator->id))->toBeFalse();
});

it('locks conserta only on coordinator dismissal', function () {
    $clinic = MedicalClinic::query()->create(['name' => 'Conserta Centro', 'active' => true]);
    $byCoordinator = Collaborator::factory()->create(['examined_medical_clinic_id' => $clinic->id]);
    $byCollaborator = Collaborator::factory()->create(['examined_medical_clinic_id' => $clinic->id]);
    $coordinator = makeRoleUser('coordinator');
    $colabUser = makeRoleUser('collaborator');
    $service = app(OffboardingService::class);

    $dismissal = $service->open($byCoordinator, $coordinator, OffboardingProcess::ORIGIN_COORDINATOR, OffboardingProcess::KIND_DISMISSAL);
    $resignation = $service->open($byCollaborator, $colabUser, OffboardingProcess::ORIGIN_COLLABORATOR, OffboardingProcess::KIND_RESIGNATION);

    expect($dismissal->direction_lock_conserta)->toBeTrue();
    expect($resignation->direction_lock_conserta)->toBeFalse();
});

it('sends collaborator pending letter to direction after conference', function () {
    $collaborator = Collaborator::factory()->create(['hired_at' => now()->subDays(10)]);
    $colabUser = makeRoleUser('collaborator');
    $rh = makeRoleUser('rh');
    $service = app(OffboardingService::class);
    $process = $service->open($collaborator, $colabUser, OffboardingProcess::ORIGIN_COLLABORATOR, OffboardingProcess::KIND_RESIGNATION, 'sair', [
        'letter_status' => OffboardingProcess::LETTER_PENDENTE,
    ]);

    $service->recordConference($process, $rh, $process->wa_daily_count, true, 'cliomed', OffboardingProcess::LETTER_PENDENTE);

    expect($process->fresh()->status)->toBe(OffboardingProcess::STAGE_ANALISE_DIRECAO);
});

it('routes less than 135 days to accounting and 135 plus to cliomed', function () {
    $rh = makeRoleUser('rh');
    $coordinator = makeRoleUser('coordinator');
    $service = app(OffboardingService::class);

    $short = Collaborator::factory()->create(['hired_at' => now()->subDays(20)]);
    $long = Collaborator::factory()->create(['hired_at' => now()->subDays(200)]);

    $p1 = $service->open($short, $coordinator, OffboardingProcess::ORIGIN_COORDINATOR, OffboardingProcess::KIND_DISMISSAL);
    $service->recordConference($p1, $rh, $p1->wa_daily_count, true, 'cliomed');
    expect($p1->fresh()->status)->toBe(OffboardingProcess::STAGE_AGUARDANDO_DOCUMENTACAO);

    $p2 = $service->open($long, $coordinator, OffboardingProcess::ORIGIN_COORDINATOR, OffboardingProcess::KIND_DISMISSAL);
    $service->recordConference($p2, $rh, $p2->wa_daily_count, true, 'cliomed');
    expect($p2->fresh()->status)->toBe(OffboardingProcess::STAGE_MARCACAO_EXAME);
});

it('refuses baixa without accounting docs and completes when checked', function () {
    $rh = makeRoleUser('rh');
    $coordinator = makeRoleUser('coordinator');
    $collaborator = Collaborator::factory()->create(['hired_at' => now()->subDays(10)]);
    $service = app(OffboardingService::class);
    $process = $service->open($collaborator, $coordinator, OffboardingProcess::ORIGIN_COORDINATOR, OffboardingProcess::KIND_DISMISSAL);
    $service->recordConference($process, $rh, $process->wa_daily_count, true, 'cliomed');

    expect(fn () => $service->completeDismissal($process->fresh(), $rh))->toThrow(\Illuminate\Validation\ValidationException::class);

    $service->markDocs($process->fresh(), $rh, true, true);
    $done = $service->completeDismissal($process->fresh(), $rh);
    expect($done->status)->toBe(OffboardingProcess::STAGE_DESLIGADO_CONCLUIDO);
    expect($collaborator->fresh()->active)->toBeFalse();
    expect(OffboardingProcess::blocksDailyRatesFor($collaborator->id))->toBeTrue();
});

it('creates an 18 day inactivity audit and a 25 day offboarding process', function () {
    $this->mock(WhatsAppNotifier::class, function (MockInterface $mock) {
        $mock->shouldIgnoreMissing();
        $mock->shouldReceive('removeFromStoreGroups')->andReturn(['result' => 'ok']);
    });
    makeRoleUser('rh');

    $alert = Collaborator::factory()->create([
        'created_at' => now()->subDays(20),
        'hired_at' => now()->subDays(20),
        'updated_at' => now()->subDays(20),
    ]);
    $off = Collaborator::factory()->create([
        'created_at' => now()->subDays(30),
        'hired_at' => now()->subDays(30),
        'updated_at' => now()->subDays(30),
    ]);
    Collaborator::factory()->create(['created_at' => now()->subDays(5), 'hired_at' => now()->subDays(5)]);

    $created = app(InactiveCollaboratorDetector::class)->detect();
    expect($created)->toBe(2);

    $this->assertDatabaseHas('inactivity_audits', [
        'collaborator_id' => $alert->id,
        'status' => InactivityAudit::STATUS_OPEN_18,
    ]);

    $this->assertDatabaseHas('offboarding_processes', [
        'collaborator_id' => $off->id,
        'kind' => OffboardingProcess::KIND_INACTIVITY,
    ]);

    expect(app(InactiveCollaboratorDetector::class)->detect())->toBe(0);
    $this->artisan('rh:detect-inactive-collaborators')->assertSuccessful();
});

it('pauses inactivity counting while an allowance is active', function () {
    makeRoleUser('rh');
    $collaborator = Collaborator::factory()->create([
        'created_at' => now()->subDays(40),
        'hired_at' => now()->subDays(40),
    ]);
    InactivityAllowance::create([
        'collaborator_id' => $collaborator->id,
        'reason' => 'viagem',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addDays(5)->toDateString(),
    ]);

    expect(app(InactiveCollaboratorDetector::class)->detect())->toBe(0);
});

it('creates a unique clinic pending card and closes it when regularized', function () {
    $rh = makeRoleUser('rh');
    $clinic = MedicalClinic::query()->create(['name' => 'Cliomed Sul', 'active' => true]);
    $collaborator = Collaborator::factory()->create(['examined_medical_clinic_id' => null]);

    $service = app(ClinicPanelService::class);
    expect($service->syncPendingCards())->toBe(1);
    expect($service->syncPendingCards())->toBe(0);

    $card = ClinicPendingCard::query()->where('collaborator_id', $collaborator->id)->first();
    expect($card->status)->toBe(ClinicPendingCard::STATUS_OPEN);

    $service->resolve($card, $rh, $clinic->id);
    expect($card->fresh()->status)->toBe(ClinicPendingCard::STATUS_RESOLVED);
    expect($service->summary()['without'])->toBe(0);
});

it('regularizes a clinic pending card over json without a redirect', function () {
    $rh = makeRoleUser('rh');
    $clinic = MedicalClinic::query()->create(['name' => 'Conserta Centro', 'active' => true]);
    $collaborator = Collaborator::factory()->create(['examined_medical_clinic_id' => null]);
    app(ClinicPanelService::class)->syncPendingCards();
    $card = ClinicPendingCard::query()->where('collaborator_id', $collaborator->id)->first();

    $this->actingAs($rh)
        ->postJson(route('work.clinic.resolve', $card), ['clinic_id' => $clinic->id])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($card->fresh()->status)->toBe(ClinicPendingCard::STATUS_RESOLVED);
    expect($collaborator->fresh()->examined_medical_clinic_id)->toBe($clinic->id);
});

it('creates and completes the weekly cliomed check', function () {
    $rh = makeRoleUser('rh');
    $service = app(ClinicPanelService::class);
    $check = $service->ensureWeeklyCheck();
    expect($check->isOpen())->toBeTrue();
    $service->completeWeekly($check, $rh, $check->wa_count, 'bateu');
    expect($check->fresh()->status)->toBe(CliomedWeeklyCheck::STATUS_DONE);
    $this->artisan('rh:cliomed-weekly-check')->assertSuccessful();
});

it('reconciles the cliomed report against collaborators by name', function () {
    $rh = makeRoleUser('rh');
    $cliomed = MedicalClinic::query()->create(['name' => 'Cliomed Centro', 'active' => true]);
    $conserta = MedicalClinic::query()->create(['name' => 'Conserta Norte', 'active' => true]);

    Collaborator::factory()->create(['name' => 'Felipe Da Silva Januário', 'examined_medical_clinic_id' => $cliomed->id]);
    Collaborator::factory()->create(['name' => 'Wesley Souza', 'examined_medical_clinic_id' => $cliomed->id]);
    Collaborator::factory()->create(['name' => 'Alguem So No Sistema', 'examined_medical_clinic_id' => $cliomed->id]);
    Collaborator::factory()->create(['name' => 'Carina Paula da Costa', 'examined_medical_clinic_id' => $conserta->id]);
    Collaborator::factory()->create(['name' => 'Inativo Na Lista', 'active' => false, 'examined_medical_clinic_id' => $cliomed->id]);

    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cliomed-report.csv';
    file_put_contents($path, implode("\n", [
        'Nome Unidade;Nome Setor;Nome Cargo;Nome Funcionário',
        'WA MERCHANDISING;2 - Operacional;Repositor (a);FELIPE DA SILVA JANUÁRIO',
        'WA MERCHANDISING;2 - Operacional;Repositor (a);WESLEY FONSECA SOUZA',
        'WA MERCHANDISING;2 - Operacional;Operador de Loja;CARINA PAULA DA COSTA',
        'WA MERCHANDISING;2 - Operacional;Operador de Loja;PESSOA SO NA CLIOMED',
        'WA MERCHANDISING;2 - Operacional;Operador de Loja;INATIVO NA LISTA',
    ]));

    $check = app(ClinicPanelService::class)->ensureWeeklyCheck();
    $upload = Illuminate\Http\UploadedFile::fake()->createWithContent('Lista de Colaboradores WA Merchandising.csv', file_get_contents($path));

    $this->actingAs($rh)
        ->post(route('work.clinic.weekly'), [
            'check_id' => $check->id,
            'attachment' => $upload,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $recon = $check->fresh()->reconciliation;
    expect($recon['report_count'])->toBe(5);
    expect($recon['ok'])->toBe(2);
    expect(collect($recon['ok_people'])->pluck('name')->all())->toContain('Felipe Da Silva Januário');
    expect(collect($recon['only_report'])->pluck('name')->all())->toContain('PESSOA SO NA CLIOMED');
    expect(collect($recon['only_system'])->pluck('name')->all())->toContain('Alguem So No Sistema');
    expect(collect($recon['wrong_clinic'])->pluck('name')->all())->toContain('Carina Paula da Costa');
    expect(collect($recon['inactive_in_report'])->pluck('name')->all())->toContain('Inativo Na Lista');

    $this->actingAs($rh)
        ->post(route('work.clinic.weekly'), [
            'check_id' => $check->id,
            'complete' => 1,
        ])
        ->assertSessionHasErrors('complete');

    expect($check->fresh()->isOpen())->toBeTrue();
    expect(app(ClinicPanelService::class)->weeklyState($check->fresh())['tone'])->toBe('is-week');

    $service = app(ClinicPanelService::class);
    foreach ($service->pendingInconsistencies($check->fresh()) as $item) {
        $action = match ($item['_bucket'] ?? '') {
            'only_report' => 'report_only',
            'only_system' => 'deactivate',
            'wrong_clinic' => 'set_cliomed',
            'inactive_in_report' => 'acknowledge',
            'ambiguous' => 'no_match',
            default => 'acknowledge',
        };
        $service->resolveInconsistency($check->fresh(), $item['_key'], $action);
    }

    $this->actingAs($rh)
        ->post(route('work.clinic.weekly'), [
            'check_id' => $check->id,
            'complete' => 1,
            'notes' => 'base alinhada',
        ])
        ->assertRedirect(route('work.cliomed'));

    $done = $check->fresh();
    expect($done->status)->toBe(CliomedWeeklyCheck::STATUS_DONE);
    expect($service->weeklyState($done)['tone'])->toBe('is-quiet');
    expect($service->weeklyState($done)['kicker'])->toBe('Em dia');
});

it('opens the work hub for direction roles', function () {
    $owner = makeRoleUser('super_admin');
    $this->actingAs($owner)->get(route('work.home'))->assertOk()->assertSee('RH Controle')->assertSee('Acompanhamento RH');
    $this->actingAs($owner)->get(route('work.project', 'offboarding'))
        ->assertOk()
        ->assertSee('Desligamentos por etapa')
        ->assertSee('Revisar 25 dias sem diária')
        ->assertDontSee('Decisões para você')
        ->assertDontSee('Clínica pendente')
        ->assertDontSee('Auditoria de inatividade')
        ->assertDontSee('Mensalidade Cliomed');
    $this->actingAs($owner)->get(route('work.project', ['project' => 'offboarding', 'stage' => 'clinic']))
        ->assertOk()
        ->assertDontSee('Conferir base Cliomed')
        ->assertDontSee('Comparar com o sistema');

    $this->actingAs($owner)->get(route('work.home'))
        ->assertOk()
        ->assertSee('Conferência Cliomed')
        ->assertDontSee('Contratações por etapa');

    $this->actingAs($owner)->get(route('work.cliomed'))
        ->assertOk()
        ->assertSee('Conferir base Cliomed')
        ->assertSee('Comparar com o sistema');
});

it('shows hiring entry points on the recruitment board', function () {
    $owner = makeRoleUser('super_admin');

    $this->actingAs($owner)
        ->get(route('work.home'))
        ->assertOk()
        ->assertSee('Processo de contratação');

    $this->actingAs($owner)
        ->get(route('work.project', 'recruitment'))
        ->assertOk()
        ->assertSee('Contratações por etapa')
        ->assertSee('Nova contratação');
});

it('does not mix pending financial batches into the offboarding decisions', function () {
    $owner = makeRoleUser('super_admin');
    $company = Company::query()->create(['name' => 'Loja Financeira', 'coordinator_value' => 0]);
    FinancialBatches::query()->create([
        'company_id' => $company->id,
        'status' => 'pending',
        'description' => 'Lote de teste para pagamento',
        'total_amount' => 100,
        'remaining_amount' => 100,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $this->actingAs($owner)
        ->get(route('work.project', 'offboarding'))
        ->assertOk()
        ->assertDontSee('FINANCEIRO')
        ->assertDontSee('Pagamento pendente')
        ->assertDontSee('Lote de teste para pagamento');
});

it('shows the real offboarding board by pop stage', function () {
    $rh = makeRoleUser('rh');
    $coordinator = makeRoleUser('coordinator');
    $collaborator = Collaborator::factory()->create(['name' => 'Ana Board']);
    app(OffboardingService::class)->open(
        $collaborator,
        $coordinator,
        OffboardingProcess::ORIGIN_COORDINATOR,
        OffboardingProcess::KIND_DISMISSAL,
    );

    $this->actingAs($rh)
        ->get(route('work.project', 'offboarding'))
        ->assertOk()
        ->assertSee('Desligamentos por etapa')
        ->assertSee('Aguardando atendimento do RH')
        ->assertSee('1. Carta a punho')
        ->assertSee('2. Conferência WA × INSS')
        ->assertSee('Ana Board')
        ->assertSee('Iniciar verificação do dossiê')
        ->assertSee('Coordenador solicitar desligamento');
});

it('moves the card to conference after the handwritten letter is uploaded', function () {
    $rh = makeRoleUser('rh');
    $coordinator = makeRoleUser('coordinator');
    $collaborator = Collaborator::factory()->create();
    $process = app(OffboardingService::class)->open(
        $collaborator,
        $coordinator,
        OffboardingProcess::ORIGIN_COORDINATOR,
        OffboardingProcess::KIND_DISMISSAL,
    );

    expect($process->boardStageKey())->toBe('atendimento');

    $this->actingAs($rh)
        ->post(route('work.offboarding.start', $process))
        ->assertRedirect(route('work.offboarding.show', $process));

    $process->refresh();
    expect($process->boardStageKey())->toBe('carta');

    $this->actingAs($rh)
        ->post(route('work.offboarding.document', $process), [
            'kind' => 'letter',
            'file' => UploadedFile::fake()->image('carta.jpg'),
        ])
        ->assertRedirect(route('work.project', 'offboarding'));

    $process->refresh()->load('attachments');
    expect($process->letter_status)->toBe(OffboardingProcess::LETTER_ANEXADA);
    expect($process->boardStageKey())->toBe('conferencia');
    expect($process->status)->toBe(OffboardingProcess::STAGE_ATENDIMENTO_RH);
});

it('completes a transfer into a new store', function () {
    $rh = makeRoleUser('rh');
    $coordinator = makeRoleUser('coordinator');
    $collaborator = Collaborator::factory()->create(['group' => 'Célula Norte']);
    $company = Company::query()->create(['name' => 'Loja Nova', 'coordinator_value' => 0]);
    $service = app(OffboardingService::class);
    $process = $service->open($collaborator, $coordinator, OffboardingProcess::ORIGIN_COORDINATOR, OffboardingProcess::KIND_TRANSFER);
    $done = $service->completeTransfer($process, $rh, $company->id, 'Repositor', now()->toDateString());
    expect($done->status)->toBe(OffboardingProcess::STAGE_TRANSFERENCIA_CONCLUIDA);
    expect($collaborator->fresh()->home_company_id)->toBe($company->id);
    expect($collaborator->fresh()->active)->toBeTrue();
    expect($collaborator->fresh()->group)->toBe('Célula Norte');
});

it('renders demo document images and pending placeholders', function () {
    $rh = makeRoleUser('rh');

    $this->actingAs($rh)
        ->get(route('work.demo.card', ['contratacoes', 'camila-ferreira']))
        ->assertOk()
        ->assertSee('img/demo-docs/rg-camila.png')
        ->assertSee('Clique para enviar a imagem agora.');
});

it('archives the dossier, emails accounting and serves attachments', function () {
    Mail::fake();
    config(['rh.accounting_email' => 'contabilidade@example.com']);

    $rh = makeRoleUser('rh');
    $coordinator = makeRoleUser('coordinator');
    $company = Company::query()->create([
        'name' => 'Loja Dossie',
        'coordinator_value' => 0,
        'contact_email' => 'loja@example.com',
    ]);
    $collaborator = Collaborator::factory()->create([
        'hired_at' => now()->subDays(10),
        'home_company_id' => $company->id,
    ]);
    $service = app(OffboardingService::class);
    $process = $service->open($collaborator, $coordinator, OffboardingProcess::ORIGIN_COORDINATOR, OffboardingProcess::KIND_DISMISSAL);
    $attachment = $service->attach($process, $rh, 'letter', UploadedFile::fake()->image('carta.jpg'));

    $service->recordConference($process->fresh(), $rh, $process->wa_daily_count, true, 'cliomed');
    Mail::assertSent(RhProcessMail::class, 1);

    $service->markDocs($process->fresh(), $rh, true, true);
    $done = $service->completeDismissal($process->fresh(), $rh);

    expect($done->dossier_archived_at)->not->toBeNull();
    expect(Storage::disk('local')->exists($done->dossier_path.'/manifest.json'))->toBeTrue();
    Mail::assertSent(RhProcessMail::class, 2);

    $this->actingAs($rh)
        ->get(route('work.offboarding.show', $done))
        ->assertOk()
        ->assertSee('Carta a punho');

    $this->actingAs($rh)
        ->get(route('work.offboarding.attachment', [$done, $attachment]))
        ->assertOk();
});

it('notifies collaborator and coordinator after 18 days without a daily rate', function () {
    $this->mock(WhatsAppNotifier::class, function (MockInterface $mock) {
        $mock->shouldIgnoreMissing();
    });

    $coordinator = makeRoleUser('coordinator');
    $store = Company::query()->create([
        'name' => 'Loja Alerta',
        'coordinator_value' => 0,
        'coordinator_id' => $coordinator->id,
    ]);
    $collaborator = Collaborator::factory()->create([
        'hired_at' => now()->subDays(20),
        'home_company_id' => $store->id,
    ]);
    $collaboratorUser = makeRoleUser('collaborator');
    $collaboratorUser->collaborator_id = $collaborator->id;
    $collaboratorUser->save();

    app(InactiveCollaboratorDetector::class)->detect();

    expect($coordinator->fresh()->unreadNotifications)->toHaveCount(1);
    expect($collaboratorUser->fresh()->unreadNotifications)->toHaveCount(1);
    expect($coordinator->unreadNotifications->first()->data['body'])->toContain('Identifique o motivo');
});

it('keeps people under 135 days ahead in the rh waiting list', function () {
    $rh = makeRoleUser('rh');
    $coordinator = makeRoleUser('coordinator');
    $service = app(OffboardingService::class);
    $long = Collaborator::factory()->create(['name' => 'Mais Tempo', 'hired_at' => now()->subDays(200)]);
    $short = Collaborator::factory()->create(['name' => 'Menos Tempo', 'hired_at' => now()->subDays(40)]);
    $service->open($long, $coordinator, OffboardingProcess::ORIGIN_COORDINATOR, OffboardingProcess::KIND_DISMISSAL);
    $service->open($short, $coordinator, OffboardingProcess::ORIGIN_COORDINATOR, OffboardingProcess::KIND_DISMISSAL);

    $cards = collect(app(WorkHubService::class)->processStageBoard()['columns'])
        ->firstWhere('id', 'atendimento')['cards'];

    expect(array_column($cards, 'title'))->toBe(['Menos Tempo', 'Mais Tempo']);
    expect($cards[0]['body'])->toContain('Exame dispensado');
    expect($cards[1]['body'])->toContain('pago');
});

it('shows payment and invoice as attendance stages', function () {
    $owner = makeRoleUser('super_admin');
    $company = Company::query()->create(['name' => 'Loja Nota', 'coordinator_value' => 0]);
    FinancialBatches::query()->create([
        'company_id' => $company->id,
        'description' => 'Lote etapa',
        'total_amount' => 80,
        'remaining_amount' => 80,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'status' => 'pending',
    ]);

    $this->actingAs($owner)
        ->get(route('work.project', 'finance'))
        ->assertOk()
        ->assertSee('1. Diárias do período')
        ->assertSee('2. Emissão da nota')
        ->assertSee('3. Pagamento / recebimento')
        ->assertSee('Loja Nota');
});

it('puts a yellow rh card on the board when the collaborator asks to leave', function () {
    $this->mock(WhatsAppNotifier::class, function (MockInterface $mock) {
        $mock->shouldIgnoreMissing();
        $mock->shouldReceive('notifyCollaboratorAndCoordinator')->once();
        $mock->shouldReceive('removeFromStoreGroups')->andReturn(['result' => 'ok']);
    });

    $collaborator = Collaborator::factory()->create(['name' => 'Camila Pedido']);
    $user = makeRoleUser('collaborator');
    $user->collaborator_id = $collaborator->id;
    $user->save();
    $rh = makeRoleUser('rh');

    $this->actingAs($user)
        ->post(route('portal.dismissal'), ['notes' => 'Quero sair'])
        ->assertRedirect();

    $cards = collect(app(WorkHubService::class)->processStageBoard()['columns'])
        ->firstWhere('id', 'atendimento')['cards'];

    expect($cards)->toHaveCount(1);
    expect($cards[0]['title'])->toBe('Camila Pedido');
    expect($cards[0]['collaborator_id'])->toBe($collaborator->id);
    expect($cards[0]['duty'])->toBe(OffboardingProcess::DUTY_RH);

    $this->actingAs($rh)
        ->get(route('work.project', 'offboarding'))
        ->assertOk()
        ->assertSee('Camila Pedido')
        ->assertSee('PEDIDO DE DEMISSÃO')
        ->assertSee('Responsabilidade do RH')
        ->assertSee('Contorno amarelo');

    $this->assertDatabaseHas('rh_tasks', [
        'collaborator_id' => $collaborator->id,
        'type' => RhTask::TYPE_ATENDIMENTO,
        'status' => RhTask::STATUS_PENDING,
    ]);
});

it('opens a dismissal card after 25 days without a daily rate and paints direction red', function () {
    $this->mock(WhatsAppNotifier::class, function (MockInterface $mock) {
        $mock->shouldIgnoreMissing();
        $mock->shouldReceive('removeFromStoreGroups')->andReturn(['result' => 'ok']);
    });
    $rh = makeRoleUser('rh');
    $collaborator = Collaborator::factory()->create([
        'name' => 'Inativo Vinte Cinco',
        'created_at' => now()->subDays(30),
        'hired_at' => now()->subDays(30),
    ]);

    app(InactiveCollaboratorDetector::class)->detect();

    $cards = collect(app(WorkHubService::class)->processStageBoard()['columns'])
        ->firstWhere('id', 'atendimento')['cards'];

    expect(collect($cards)->pluck('title'))->toContain('Inativo Vinte Cinco');
    expect(collect($cards)->firstWhere('title', 'Inativo Vinte Cinco')['collaborator_id'])->toBe($collaborator->id);
    expect(collect($cards)->firstWhere('title', 'Inativo Vinte Cinco')['duty'])->toBe(OffboardingProcess::DUTY_RH);

    $process = OffboardingProcess::query()->where('collaborator_id', $collaborator->id)->first();
    app(OffboardingService::class)->startVerification($process, $rh);

    expect($process->fresh()->duty())->toBe(OffboardingProcess::DUTY_GESTOR);
    expect($process->fresh()->boardStageKey())->toBe('direcao');
});

it('refuses a dismissal card without a collaborator', function () {
    $user = makeRoleUser('rh');

    expect(fn () => OffboardingProcess::create([
        'requested_by_user_id' => $user->id,
        'origin' => OffboardingProcess::ORIGIN_COORDINATOR,
        'kind' => OffboardingProcess::KIND_DISMISSAL,
        'status' => OffboardingProcess::STAGE_ATENDIMENTO_RH,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('lets the gestor geral approve a red card', function () {
    $coordinator = makeRoleUser('coordinator');
    $gestor = makeRoleUser('super_admin');
    $collaborator = Collaborator::factory()->create(['name' => 'Espera Gestor', 'hired_at' => now()->subDays(10)]);
    $service = app(OffboardingService::class);
    $process = $service->open($collaborator, $coordinator, OffboardingProcess::ORIGIN_COLLABORATOR, OffboardingProcess::KIND_RESIGNATION, 'sair', [
        'letter_status' => OffboardingProcess::LETTER_PENDENTE,
    ]);
    $service->recordConference($process, makeRoleUser('rh'), $process->wa_daily_count, true, 'cliomed', OffboardingProcess::LETTER_PENDENTE);

    expect($process->fresh()->duty())->toBe(OffboardingProcess::DUTY_GESTOR);

    $this->actingAs($gestor)
        ->post(route('work.offboarding.direction', $process), [
            'decision' => 'authorize',
            'justification' => 'Pode seguir',
        ])
        ->assertRedirect();

    expect($process->fresh()->direction_released_at)->not->toBeNull();
});

it('shows rh attendance on meu controle when direction has nothing to analyze', function () {
    $rh = makeRoleUser('rh');
    $coordinator = makeRoleUser('coordinator');
    $collaborator = Collaborator::factory()->create(['name' => 'Fila Do Rh']);
    app(OffboardingService::class)->open(
        $collaborator,
        $coordinator,
        OffboardingProcess::ORIGIN_COORDINATOR,
        OffboardingProcess::KIND_DISMISSAL,
        'Sem escala',
    );

    $this->actingAs($rh)
        ->get(route('work.home'))
        ->assertOk()
        ->assertSee('RH Controle')
        ->assertSee('Aguardando o RH')
        ->assertSee('1 aguardando o RH')
        ->assertDontSee('Acompanhamento RH')
        ->assertDontSee('Fila Do Rh')
        ->assertDontSee('0 análises');

    $this->actingAs($rh)->get(route('rh.inbox'))->assertForbidden();
});

it('shows the gestor how rh work is moving', function () {
    $owner = makeRoleUser('super_admin');
    $coordinator = makeRoleUser('coordinator');
    $collaborator = Collaborator::factory()->create(['name' => 'Andamento Do Rh']);
    app(OffboardingService::class)->open(
        $collaborator,
        $coordinator,
        OffboardingProcess::ORIGIN_COORDINATOR,
        OffboardingProcess::KIND_DISMISSAL,
        'Sem escala',
    );

    $this->actingAs($owner)
        ->get(route('rh.inbox'))
        ->assertOk()
        ->assertSee('Acompanhamento RH')
        ->assertSee('Andamento Do Rh')
        ->assertSee('Aguardando atendimento do RH')
        ->assertDontSee('Realocar')
        ->assertDontSee('Demitir')
        ->assertDontSee('Autorizar');
});

it('lets the gestor decide from acompanhamento rh analysis queue', function () {
    $coordinator = makeRoleUser('coordinator');
    $gestor = makeRoleUser('super_admin');
    $collaborator = Collaborator::factory()->create(['name' => 'Fila Do Gestor', 'hired_at' => now()->subDays(10)]);
    $service = app(OffboardingService::class);
    $process = $service->open($collaborator, $coordinator, OffboardingProcess::ORIGIN_COLLABORATOR, OffboardingProcess::KIND_RESIGNATION, 'sair', [
        'letter_status' => OffboardingProcess::LETTER_PENDENTE,
    ]);
    $service->recordConference($process, makeRoleUser('rh'), $process->wa_daily_count, true, 'cliomed', OffboardingProcess::LETTER_PENDENTE);

    $this->actingAs($gestor)
        ->get(route('rh.inbox'))
        ->assertOk()
        ->assertSee('Na sua análise')
        ->assertSee('Fila Do Gestor')
        ->assertSee('Autorizar')
        ->assertSee('Devolver ao RH')
        ->assertSee('Liberar com pendência');

    $this->actingAs($gestor)
        ->post(route('work.offboarding.direction', $process), [
            'decision' => 'authorize',
            'justification' => 'Pode seguir pela fila do gestor',
        ])
        ->assertRedirect();

    expect($process->fresh()->direction_released_at)->not->toBeNull();
});

it('reads the last work day from daily rates and leaves other queues uncolored', function () {
    $coordinator = makeRoleUser('coordinator');
    $rh = makeRoleUser('rh');
    $collaborator = Collaborator::factory()->create([
        'name' => 'Com Diaria',
        'hired_at' => now()->subDays(20),
    ]);
    $section = Section::query()->create(['name' => 'Caixa']);
    DailyRate::forceCreate([
        'collaborator_id' => $collaborator->id,
        'section_id' => $section->id,
        'start' => '2026-09-01 08:00:00',
        'end' => '2026-09-01 17:00:00',
        'active' => true,
        'status' => 'criado',
    ]);
    DailyRate::forceCreate([
        'collaborator_id' => $collaborator->id,
        'section_id' => $section->id,
        'start' => '2026-09-20 08:00:00',
        'end' => '2026-09-20 17:00:00',
        'active' => true,
        'status' => 'criado',
    ]);

    $process = app(OffboardingService::class)->open(
        $collaborator,
        $coordinator,
        OffboardingProcess::ORIGIN_COORDINATOR,
        OffboardingProcess::KIND_DISMISSAL,
        'Sem escala',
    );

    expect($process->last_work_day->toDateString())->toBe('2026-09-20');

    $this->actingAs($coordinator)
        ->get(route('work.request', ['collaborator_id' => $collaborator->id]))
        ->assertOk()
        ->assertSee('20/09/2026')
        ->assertDontSee('name="last_work_day"', false);

    app(OffboardingService::class)->recordConference($process, $rh, $process->wa_daily_count, true, 'cliomed');
    $cards = collect(app(WorkHubService::class)->processStageBoard()['columns'])
        ->firstWhere('id', 'contabilidade')['cards'];

    expect(collect($cards)->firstWhere('title', 'Com Diaria')['duty'])->toBeNull();
    expect(collect($cards)->firstWhere('title', 'Com Diaria')['body'])->toContain('Último dia trabalhado 20/09/2026');
});
