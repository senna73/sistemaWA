<?php

use App\Models\Collaborator;
use App\Models\MedicalClinic;
use App\Models\User;
use App\Services\Rh\ClinicPanelService;
use App\Support\AccessControl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function cliomedActor(): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => 'rh']);
    AccessControl::applyToUser($user, 'rh');

    return $user->fresh();
}

function cliomedUpload(ClinicPanelService $service, User $rh, array $names): \App\Models\CliomedWeeklyCheck
{
    $check = $service->ensureWeeklyCheck();
    $lines = ['Nome Unidade;Nome Setor;Nome Cargo;Nome Funcionário'];
    foreach ($names as $name) {
        $lines[] = 'WA MERCHANDISING;2 - Operacional;Repositor (a);'.$name;
    }
    $upload = UploadedFile::fake()->createWithContent('cliomed.csv', implode("\n", $lines));

    test()->actingAs($rh)
        ->post(route('work.clinic.weekly'), [
            'check_id' => $check->id,
            'attachment' => $upload,
        ])
        ->assertRedirect(route('work.cliomed'))
        ->assertSessionHas('status');

    return $check->fresh();
}

it('blocks a second cliomed report until the week is discarded', function () {
    Storage::fake('local');
    $rh = cliomedActor();
    $cliomed = MedicalClinic::query()->create(['name' => 'Cliomed Centro', 'active' => true]);
    Collaborator::factory()->create(['name' => 'Pessoa Ok', 'examined_medical_clinic_id' => $cliomed->id]);
    $service = app(ClinicPanelService::class);
    $check = cliomedUpload($service, $rh, ['PESSOA SO NA CLIOMED']);

    $this->actingAs($rh)
        ->get(route('work.cliomed'))
        ->assertOk()
        ->assertSee('Descartar conferência da semana')
        ->assertSee('Criar cadastro na WA')
        ->assertSee('Só no relatório de não cadastrados')
        ->assertSee('Apagar da lista ativa')
        ->assertDontSee('Comparar com o sistema');

    $this->actingAs($rh)
        ->post(route('work.clinic.weekly'), [
            'check_id' => $check->id,
            'attachment' => UploadedFile::fake()->createWithContent('cliomed2.csv', "Nome Funcionário\nOUTRA PESSOA"),
        ])
        ->assertSessionHasErrors('attachment');

    $this->actingAs($rh)
        ->from(route('work.cliomed'))
        ->post(route('work.cliomed.discard'), ['check_id' => $check->id])
        ->assertRedirect(route('work.cliomed'));

    expect($check->fresh()->report_count)->toBeNull();
    expect($check->fresh()->reconciliation)->toBeNull();

    $this->actingAs($rh)
        ->get(route('work.cliomed'))
        ->assertOk()
        ->assertSee('Comparar com o sistema');

    cliomedUpload($service, $rh, ['OUTRA PESSOA']);
    expect($check->fresh()->report_count)->toBe(1);
});

it('creates a wa collaborator from a cliomed-only name', function () {
    Storage::fake('local');
    $rh = cliomedActor();
    MedicalClinic::query()->create(['name' => 'Cliomed Centro', 'active' => true]);
    $service = app(ClinicPanelService::class);
    $check = cliomedUpload($service, $rh, ['NOVO NA CLIOMED']);
    $item = collect($service->pendingInconsistencies($check))->firstWhere('_bucket', 'only_report');

    $this->actingAs($rh)
        ->post(route('work.cliomed.resolve'), [
            'check_id' => $check->id,
            'key' => $item['_key'],
            'action' => 'create_in_wa',
            'name' => 'Novo Na Cliomed',
        ])
        ->assertRedirect();

    $created = Collaborator::query()->where('name', 'Novo Na Cliomed')->first();
    expect($created)->not->toBeNull();
    expect($created->clinicSlug())->toBe('cliomed');
    expect($created->active)->toBeTrue();
    expect($service->pendingInconsistencyCount($check->fresh()))->toBe(0);
});

it('soft-deletes a wa-only collaborator from the cliomed check', function () {
    Storage::fake('local');
    $rh = cliomedActor();
    $cliomed = MedicalClinic::query()->create(['name' => 'Cliomed Centro', 'active' => true]);
    $onlyWa = Collaborator::factory()->create(['name' => 'So No Sistema', 'examined_medical_clinic_id' => $cliomed->id]);
    $linked = User::factory()->create(['collaborator_id' => $onlyWa->id, 'active' => true]);
    $service = app(ClinicPanelService::class);
    $check = cliomedUpload($service, $rh, ['NINGUEM']);
    $item = collect($service->pendingInconsistencies($check->fresh()))->firstWhere('_bucket', 'only_system');

    $this->actingAs($rh)
        ->post(route('work.cliomed.resolve'), [
            'check_id' => $check->id,
            'key' => $item['_key'],
            'action' => 'deactivate',
            'name' => $onlyWa->name,
        ])
        ->assertRedirect();

    expect($onlyWa->fresh()->active)->toBeFalse();
    expect($linked->fresh()->active)->toBeFalse();
});

it('links an ambiguous cliomed name to a chosen collaborator', function () {
    $rh = cliomedActor();
    $cliomed = MedicalClinic::query()->create(['name' => 'Cliomed Centro', 'active' => true]);
    $one = Collaborator::factory()->create(['name' => 'Nome Igual', 'examined_medical_clinic_id' => $cliomed->id]);
    $two = Collaborator::factory()->create(['name' => 'Nome Igual', 'examined_medical_clinic_id' => $cliomed->id]);
    $service = app(ClinicPanelService::class);
    $check = $service->ensureWeeklyCheck();
    $result = app(\App\Services\Rh\CliomedReconciler::class)->compare([
        ['name' => 'Nome Igual', 'unit' => null, 'sector' => null, 'role' => null],
    ]);
    $check->update(['reconciliation' => $result, 'report_count' => 1]);

    $item = collect($service->pendingInconsistencies($check->fresh()))->firstWhere('_bucket', 'ambiguous');
    expect($item)->not->toBeNull();
    expect(collect($item['candidates'])->pluck('id')->all())->toContain($one->id, $two->id);

    $this->actingAs($rh)
        ->post(route('work.cliomed.resolve'), [
            'check_id' => $check->id,
            'key' => $item['_key'],
            'action' => 'link',
            'collaborator_id' => $two->id,
            'name' => 'Nome Igual',
        ])
        ->assertRedirect();

    $pending = collect($service->pendingInconsistencies($check->fresh()));
    expect($pending->firstWhere('_bucket', 'ambiguous'))->toBeNull();
    expect($pending->where('_bucket', 'only_system')->pluck('id')->all())->not->toContain($two->id);
    expect($one->fresh()->active)->toBeTrue();
    expect($two->fresh()->active)->toBeTrue();
});

it('lists unregistered cliomed names in the pdf', function () {
    Storage::fake('local');
    $rh = cliomedActor();
    MedicalClinic::query()->create(['name' => 'Cliomed Centro', 'active' => true]);
    $service = app(ClinicPanelService::class);
    cliomedUpload($service, $rh, ['PESSOA SO NA CLIOMED']);

    $this->actingAs($rh)
        ->get(route('work.cliomed.unregistered'))
        ->assertOk();

    $check = $service->ensureWeeklyCheck();
    expect($service->unregisteredNames($check))->toContain('PESSOA SO NA CLIOMED');
});
