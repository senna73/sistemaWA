<?php

use App\Models\Candidate;
use App\Models\Collaborator;
use App\Models\Company;
use App\Models\OffboardingProcess;
use App\Models\User;
use App\Services\Rh\HiringService;
use App\Services\Rh\OffboardingService;
use App\Support\AccessControl;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

function boardOwner(): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => 'super_admin']);
    AccessControl::applyToUser($user, 'super_admin');

    return $user->fresh();
}

it('shows hiring by pop stage with open slots and in-progress cards', function () {
    $owner = boardOwner();
    $store = Company::query()->create([
        'name' => 'Bistek 04',
        'city' => 'Blumenau',
        'coordinator_value' => 0,
        'headcount_quota' => 2,
    ]);
    Collaborator::factory()->create([
        'name' => 'Maria Souza',
        'home_company_id' => $store->id,
        'active' => true,
    ]);
    $leaving = Collaborator::factory()->create([
        'name' => 'Julio Cesar',
        'home_company_id' => $store->id,
        'active' => true,
    ]);
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    AccessControl::applyToUser($coordinator, 'coordinator');
    app(OffboardingService::class)->open(
        $leaving,
        $coordinator,
        OffboardingProcess::ORIGIN_COORDINATOR,
        OffboardingProcess::KIND_DISMISSAL,
        'Sem escala'
    );

    Candidate::query()->create([
        'company_id' => $store->id,
        'name' => 'Camila Ferreira',
        'job_title' => 'Repositora',
        'status' => Candidate::STATUS_EXAM,
        'notes' => 'Exame admissional · Cliomed 26/09 09h',
    ]);

    $this->actingAs($owner)
        ->get(route('work.project', 'recruitment'))
        ->assertOk()
        ->assertSee('Contratações por etapa')
        ->assertSee('1. Documentos pessoais')
        ->assertSee('3. ASO → RH')
        ->assertSee('Vagas em aberto')
        ->assertSee('Camila Ferreira')
        ->assertSee('Bistek 04')
        ->assertDontSee('Julio Cesar')
        ->assertDontSee('Vaga em processo de abertura')
        ->assertDontSee('Candidato');
});

it('opens a hiring card and advances it after the first document is uploaded', function () {
    $owner = boardOwner();
    $store = Company::query()->create([
        'name' => 'Top Belchior',
        'coordinator_value' => 0,
        'headcount_quota' => 12,
    ]);

    $this->actingAs($owner)
        ->post(route('work.hiring.store'), [
            'company_id' => $store->id,
            'name' => 'Leticia Rocha',
            'job_title' => 'Mercearia',
        ])
        ->assertRedirect(route('work.project', 'recruitment'));

    $candidate = Candidate::query()->where('name', 'Leticia Rocha')->first();
    expect($candidate)->not->toBeNull();
    expect($candidate->status)->toBe(Candidate::STATUS_DOCS);
    expect($candidate->boardStageKey())->toBe('documentos');

    $this->actingAs($owner)
        ->post(route('work.hiring.document', $candidate), [
            'kind' => 'id',
            'file' => UploadedFile::fake()->image('rg.jpg'),
        ])
        ->assertRedirect(route('work.project', 'recruitment'));

    $candidate->refresh()->load('attachments');
    expect($candidate->status)->toBe(Candidate::STATUS_EXAM);
    expect($candidate->boardStageKey())->toBe('exame');
});

it('updates a store headcount quota', function () {
    $owner = boardOwner();
    $store = Company::query()->create([
        'name' => 'Top Tribess',
        'coordinator_value' => 0,
        'headcount_quota' => 10,
    ]);

    $this->actingAs($owner)
        ->post(route('work.quotas.update'), [
            'company_id' => $store->id,
            'headcount_quota' => 17,
        ])
        ->assertRedirect();

    expect($store->fresh()->headcount_quota)->toBe(17);
});

it('registers inss from the admission date and waits for accounting verification', function () {
    Storage::fake('local');
    $owner = boardOwner();
    $accounting = User::factory()->create(['role' => 'accounting']);
    AccessControl::applyToUser($accounting, 'accounting');
    $store = Company::query()->create([
        'name' => 'Loja INSS',
        'coordinator_value' => 0,
        'headcount_quota' => 3,
    ]);

    $candidate = app(HiringService::class)->open(
        $owner,
        $store,
        'Camila Ferreira',
        'Repositora',
        null,
        now()->toDateString(),
    );

    foreach (['id' => 'rg.jpg', 'exam_proof' => 'exame.jpg', 'aso' => 'aso.jpg'] as $kind => $name) {
        app(HiringService::class)->attachInSequence($candidate->fresh(), $owner, $kind, UploadedFile::fake()->image($name));
    }

    $candidate = $candidate->fresh();
    expect($candidate->status)->toBe(Candidate::STATUS_ACCOUNTING);
    expect($candidate->inss_registered_at)->not->toBeNull();
    expect($candidate->inss_verified_at)->toBeNull();
    expect($candidate->boardStageKey())->toBe('contabilidade');
    expect($accounting->fresh()->unreadNotifications)->not->toBeEmpty();

    expect(fn () => app(HiringService::class)->attachInSequence(
        $candidate,
        $owner,
        'scale',
        UploadedFile::fake()->image('escala.jpg'),
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    $this->actingAs($accounting)
        ->post(route('work.hiring.verify', $candidate))
        ->assertRedirect(route('work.project', 'recruitment'));

    expect($candidate->fresh()->status)->toBe(Candidate::STATUS_STORE);
    expect($candidate->fresh()->inss_verified_at)->not->toBeNull();
});
