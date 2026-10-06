<?php

namespace App\Services\Work;

use App\Models\Candidate;
use App\Models\CliomedWeeklyCheck;
use App\Models\Collaborator;
use App\Models\CollaboratorUniform;
use App\Models\Company;
use App\Models\DailyRate;
use App\Models\FinancialBatches;
use App\Models\InactivityAudit;
use App\Models\OffboardingProcess;
use App\Models\RhTask;
use App\Models\User;
use App\Services\Rh\ClinicPanelService;
use App\Support\AccessControl;
use App\Support\RhActivitySettings;
use Illuminate\Support\Collection;

class WorkHubService
{
    public function __construct(private ClinicPanelService $clinics) {}

    public function dashboard(User $user): array
    {
        $this->clinics->syncPendingCards();
        $this->clinics->ensureWeeklyCheck();

        $analyses = $this->gestorDutyCount();
        $openTasks = RhTask::query()->where('status', RhTask::STATUS_PENDING)->count()
            + CliomedWeeklyCheck::query()->where('status', CliomedWeeklyCheck::STATUS_OPEN)->count();
        $openProcesses = OffboardingProcess::query()->whereIn('status', OffboardingProcess::OPEN_STATUSES)->count();
        $doneMonth = OffboardingProcess::query()
            ->whereIn('status', [OffboardingProcess::STAGE_DESLIGADO_CONCLUIDO, OffboardingProcess::STAGE_TRANSFERENCIA_CONCLUIDA])
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        return [
            'analyses' => $analyses,
            'rh_queue' => $this->rhDutyCount(),
            'open_tasks' => $openTasks,
            'open_processes' => $openProcesses,
            'done_month' => $doneMonth,
            'projects' => $this->projects($user),
            'decisions' => $this->decisionsFor($user),
        ];
    }

    public function rhProgress(): array
    {
        $open = OffboardingProcess::query()
            ->with(['collaborator.homeCompany'])
            ->whereIn('status', OffboardingProcess::OPEN_STATUSES)
            ->whereNotNull('collaborator_id')
            ->whereHas('collaborator')
            ->orderBy('updated_at')
            ->get();

        $analysis = $open
            ->filter(fn (OffboardingProcess $process) => $process->duty() === OffboardingProcess::DUTY_GESTOR)
            ->values();

        $stages = [];
        foreach (OffboardingProcess::OPEN_STATUSES as $status) {
            $items = $open
                ->where('status', $status)
                ->reject(fn (OffboardingProcess $process) => $process->duty() === OffboardingProcess::DUTY_GESTOR)
                ->values();
            $stages[] = [
                'label' => OffboardingProcess::LABELS[$status],
                'count' => $items->count(),
                'items' => $items,
            ];
        }

        $done = OffboardingProcess::query()
            ->with('collaborator')
            ->whereIn('status', [OffboardingProcess::STAGE_DESLIGADO_CONCLUIDO, OffboardingProcess::STAGE_TRANSFERENCIA_CONCLUIDA])
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $weekly = $this->clinics->weeklyState($this->clinics->ensureWeeklyCheck());

        return [
            'stages' => $stages,
            'open_count' => $open->count(),
            'with_rh' => $open->filter(fn (OffboardingProcess $process) => $process->duty() === OffboardingProcess::DUTY_RH)->count(),
            'analysis_queue' => $analysis,
            'with_gestor' => $analysis->count(),
            'waiting' => $open->filter(fn (OffboardingProcess $process) => $process->duty() === OffboardingProcess::DUTY_WAIT)->count(),
            'done_month' => $done,
            'cliomed' => $weekly,
            'inactivity' => InactivityAudit::query()->where('status', InactivityAudit::STATUS_OPEN_18)->count(),
            'rh_areas' => $this->rhAreaMirror(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rhAreaMirror(): array
    {
        $demandSummary = $this->demandQueueSummary();

        return collect($this->projects(auth()->user()))
            ->map(function (array $project) use ($demandSummary) {
                if (($project['key'] ?? '') === 'demands') {
                    $project['url'] = route('demands.index');
                    $project['kicker'] = $demandSummary['rh'] > 0
                        ? 'O RH ainda precisa atender'
                        : ($demandSummary['review'] > 0 ? 'O RH enviou para conferência' : 'Fila em dia');
                }

                return $project;
            })
            ->all();
    }

    /**
     * @return array{rh: int, review: int, open: int}
     */
    public function demandQueueSummary(): array
    {
        $open = \App\Models\OperationalDemand::query()
            ->where('status', '!=', \App\Models\OperationalDemand::STATUS_DONE)
            ->get(['status']);

        $rh = $open->whereIn('status', [
            \App\Models\OperationalDemand::STATUS_AWAITING,
            \App\Models\OperationalDemand::STATUS_IN_PROGRESS,
        ])->count();
        $review = $open->where('status', \App\Models\OperationalDemand::STATUS_REVIEW)->count();

        return [
            'rh' => $rh,
            'review' => $review,
            'open' => $open->count(),
        ];
    }

    public function hiringIsVisible(): bool
    {
        return (bool) config('rh.show_hiring');
    }

    public function projects(?User $user = null): array
    {
        $user ??= auth()->user();
        if (! $user) {
            return [];
        }

        if ($user->coordinatorWorkbench()) {
            return $this->coordinatorProjects($user);
        }

        $projects = [];
        if ($user->managesRhWork() && RhActivitySettings::visible(RhActivitySettings::DEMANDS, $user)) {
            $demandSummary = $this->demandQueueSummary();
            $projects[] = [
                'key' => 'demands',
                'title' => 'Demandas a atender',
                'subtitle' => 'Fila operacional do RH: Pix, cadastro, declaração e pedidos da loja.',
                'badge' => $this->formatDemandBadge($demandSummary),
                'needs_action' => $demandSummary['rh'] > 0,
                'kicker' => $demandSummary['rh'] > 0
                    ? 'Aguardando o RH'
                    : ($demandSummary['review'] > 0 ? 'Em conferência' : 'Em dia'),
                'url' => $user->isRh() ? route('work.demands') : route('demands.index'),
            ];
        }

        if (
            ($user->managesRhWork() || $user->can(AccessControl::PERMISSION_ACCOUNTING) || $user->isSuperAdmin())
            && RhActivitySettings::visible(RhActivitySettings::OFFBOARDING, $user)
        ) {
            $rh = $this->rhDutyCount();
            $gestor = $this->gestorDutyCount();
            $projects[] = [
                'key' => 'offboarding',
                'title' => 'Gestão RH Demissional',
                'subtitle' => 'Desligamentos, inatividade e transferências.',
                'badge' => $this->formatOffboardingBadge($rh, $gestor),
                'needs_action' => ($rh + $gestor) > 0,
                'kicker' => $rh > 0 ? 'Aguardando o RH' : ($gestor > 0 ? 'Análises do Super admin' : 'Em dia'),
                'url' => route('work.project', 'offboarding'),
            ];
        }

        if ($user->managesRhWork() && RhActivitySettings::visible(RhActivitySettings::CLIOMED, $user)) {
            $weekly = $this->clinics->weeklyState($this->clinics->ensureWeeklyCheck());
            $projects[] = [
                'key' => 'cliomed',
                'title' => 'Conferência Cliomed',
                'subtitle' => 'Planilha da semana, inconsistências e fechamento da base.',
                'badge' => $weekly['badge'],
                'needs_action' => $weekly['needs_action'],
                'kicker' => $weekly['kicker'],
                'tone' => $weekly['tone'],
                'url' => route('work.cliomed'),
            ];
        }

        if (
            ($user->can(\App\Support\PopCatalog::PERMISSION_ACCOUNTING_LIST) || $user->isSuperAdmin())
            && RhActivitySettings::visible(RhActivitySettings::ACCOUNTING, $user)
        ) {
            $openAccounting = \App\Models\AccountingListRow::query()->whereNull('resolved_at')->where('bucket', '!=', \App\Models\AccountingListRow::BUCKET_OK)->count();
            $projects[] = [
                'key' => 'accounting',
                'title' => 'Conferência da contabilidade',
                'subtitle' => 'A lista da contabilidade é a base oficial. Bata um a um o que estiver fora do padrão.',
                'badge' => $openAccounting.' desvios',
                'needs_action' => $openAccounting > 0,
                'kicker' => $openAccounting > 0 ? 'Fora do padrão' : 'Em dia',
                'url' => route('work.accounting'),
            ];
        }

        if ($user->managesRhWork() && RhActivitySettings::visible(RhActivitySettings::RELEASES, $user)) {
            $pendingReleases = \App\Models\DailyRateReleaseRequest::query()
                ->where('status', \App\Models\DailyRateReleaseRequest::STATUS_PENDING)
                ->count();
            $projects[] = [
                'key' => 'releases',
                'title' => 'Liberações de diária',
                'subtitle' => 'Aprovar ou recusar pedidos de lançamento fora do bloqueio.',
                'badge' => $pendingReleases.' pendentes',
                'needs_action' => $pendingReleases > 0,
                'kicker' => $pendingReleases > 0 ? 'Aguardando o RH' : 'Em dia',
                'url' => route('work.releases.index'),
            ];
        }

        if ($user->managesRhWork() && RhActivitySettings::visible(RhActivitySettings::FINANCE, $user)) {
            $financePending = FinancialBatches::query()->whereIn('status', ['pending', 'processing'])->count();
            $projects[] = [
                'key' => 'finance',
                'title' => 'Financeiro e DRE',
                'subtitle' => 'Entradas, saídas e resultados por loja.',
                'badge' => $financePending.' pendências',
                'needs_action' => $financePending > 0,
                'kicker' => $financePending > 0 ? 'Pendência financeira' : 'Em dia',
                'url' => route('work.project', 'finance'),
            ];
        }

        if ($user->managesRhWork() && RhActivitySettings::visible(RhActivitySettings::UNIFORMS, $user)) {
            $uniformTasks = CollaboratorUniform::query()->whereNull('delivered_at')->count();
            $projects[] = [
                'key' => 'uniforms',
                'title' => 'Operação e uniformes',
                'subtitle' => 'Solicitações, estoque e entregas.',
                'badge' => $uniformTasks.' tarefas',
                'needs_action' => $uniformTasks > 0,
                'kicker' => $uniformTasks > 0 ? 'Entrega pendente' : 'Em dia',
                'url' => route('work.project', 'uniforms'),
            ];
        }

        return $projects;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function coordinatorProjects(User $user): array
    {
        $pendingReleases = \App\Models\DailyRateReleaseRequest::query()
            ->where('requested_by', $user->id)
            ->where('status', \App\Models\DailyRateReleaseRequest::STATUS_PENDING)
            ->count();

        $openInactivity = InactivityAudit::query()
            ->whereIn('status', [
                InactivityAudit::STATUS_OPEN_18,
                InactivityAudit::STATUS_WATCH,
            ])
            ->count();

        $projects = [];

        if (RhActivitySettings::visible(RhActivitySettings::RELEASES, $user)) {
            $projects[] = [
                'key' => 'releases',
                'title' => 'Liberação de diária',
                'subtitle' => 'Peça ao RH a liberação para lançar uma diária bloqueada.',
                'badge' => $pendingReleases > 0 ? $pendingReleases.' enviadas' : 'Solicitar',
                'needs_action' => true,
                'kicker' => 'Pedido do coordenador',
                'url' => route('work.releases.create'),
            ];
        }

        if (RhActivitySettings::visible(RhActivitySettings::INACTIVITY, $user)) {
            $projects[] = [
                'key' => 'inactivity',
                'title' => 'Inatividade da equipe',
                'subtitle' => 'Justifique 18 dias sem diária ou peça a liberação.',
                'badge' => $openInactivity > 0 ? $openInactivity.' abertas' : 'Acompanhar',
                'needs_action' => $openInactivity > 0,
                'kicker' => $openInactivity > 0 ? 'Resposta do coordenador' : 'Em dia',
                'url' => route('work.project', ['project' => 'offboarding', 'stage' => 'inactivity']),
            ];
        }

        return $projects;
    }

    public function storeBoard(?string $stage = null): array
    {
        $processes = OffboardingProcess::query()
            ->with(['collaborator.homeCompany'])
            ->when(
                $stage !== '' && $stage !== null && $stage !== 'inactivity' && $stage !== 'clinic',
                fn ($query) => $query->where('status', $stage),
                fn ($query) => $query->whereIn('status', OffboardingProcess::OPEN_STATUSES),
            )
            ->latest('id')
            ->get();

        $activeCollaborators = Collaborator::query()
            ->where('active', true)
            ->get(['id', 'home_company_id', 'name', 'job_title', 'group']);

        $lastStoreByCollaborator = $this->lastStoreByCollaborator(
            $activeCollaborators->whereNull('home_company_id')->pluck('id')
                ->merge($processes->pluck('collaborator_id')->filter())
                ->unique()
                ->values()
        );

        $occupiedByStore = [];
        $peopleByStore = [];
        foreach ($activeCollaborators as $collaborator) {
            $storeId = $this->storeIdFor($collaborator, $lastStoreByCollaborator);
            if (! $storeId) {
                continue;
            }
            $occupiedByStore[$storeId] = ($occupiedByStore[$storeId] ?? 0) + 1;
            $peopleByStore[$storeId][] = $collaborator;
        }

        $companyIds = collect(array_keys($occupiedByStore))
            ->merge($processes->map(fn (OffboardingProcess $process) => $this->storeIdFor($process->collaborator, $lastStoreByCollaborator)))
            ->filter()
            ->unique()
            ->values();

        $companies = Company::query()
            ->where(function ($query) use ($companyIds) {
                $query->where('active', true);
                if ($companyIds->isNotEmpty()) {
                    $query->orWhereIn('id', $companyIds);
                }
            })
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $columns = [];
        foreach ($companies as $company) {
            $occupied = (int) ($occupiedByStore[$company->id] ?? 0);
            $quota = $company->headcount_quota !== null ? (int) $company->headcount_quota : null;
            $open = $quota === null ? 0 : max(0, $quota - $occupied);
            $people = collect($peopleByStore[$company->id] ?? [])->sortBy('name')->values();

            $columns[$company->id] = [
                'id' => $company->id,
                'name' => $company->name,
                'city' => $company->city,
                'quota' => $quota,
                'occupied' => $occupied,
                'open' => $open,
                'excess' => $quota === null ? 0 : max(0, $occupied - $quota),
                'people' => $people,
                'opening' => 0,
                'processes' => collect(),
            ];
        }

        $unassigned = [
            'id' => null,
            'name' => 'Sem estabelecimento',
            'city' => null,
            'quota' => null,
            'occupied' => 0,
            'open' => 0,
            'opening' => 0,
            'excess' => 0,
            'people' => collect(),
            'processes' => collect(),
        ];

        foreach ($processes as $process) {
            $storeId = $this->storeIdFor($process->collaborator, $lastStoreByCollaborator);
            if ($storeId && isset($columns[$storeId])) {
                $columns[$storeId]['processes']->push($process);
                if ($process->freesHeadcountSlot()) {
                    $columns[$storeId]['opening']++;
                }
            } else {
                $unassigned['processes']->push($process);
                if ($process->freesHeadcountSlot()) {
                    $unassigned['opening']++;
                }
            }
        }

        $board = collect(array_values($columns));
        if ($unassigned['processes']->isNotEmpty()) {
            $board->push($unassigned);
        }

        return [
            'columns' => $board,
            'openings' => (int) $board->sum('open'),
            'opening_count' => (int) $board->sum('opening'),
            'excess_count' => (int) $board->sum('excess'),
            'process_count' => (int) $board->sum(fn (array $column) => $column['processes']->count()),
            'store_count' => $board->count(),
        ];
    }

    public function openSlotCount(): int
    {
        return (int) $this->storeBoard()['openings'];
    }

    /**
     * @return array<string, mixed>
     */
    public function hireStageBoard(bool $accountingOnly = false): array
    {
        $pipeline = $accountingOnly
            ? ['contabilidade' => 'Verificação do registro no INSS']
            : [
                'documentos' => '1. Documentos pessoais',
                'exame' => '2. Exame admissional',
                'aso' => '3. ASO → RH',
                'contabilidade' => '4. Encaminhar à contabilidade',
                'loja' => '5. Cadastro na loja',
                'vagas' => 'Vagas em aberto',
            ];

        $columns = [];
        foreach ($pipeline as $key => $name) {
            $columns[$key] = [
                'id' => $key,
                'name' => $name,
                'city' => null,
                'cards' => [],
            ];
        }

        $hires = Candidate::query()
            ->with(['company', 'attachments'])
            ->whereIn('status', $accountingOnly ? [Candidate::STATUS_ACCOUNTING] : Candidate::OPEN_STATUSES)
            ->latest('id')
            ->get();

        foreach ($hires as $candidate) {
            $key = $candidate->boardStageKey();
            if (! isset($columns[$key])) {
                continue;
            }
            $columns[$key]['cards'][] = $candidate->boardCard();
        }

        if (! $accountingOnly) {
            $this->appendOpenSlots($columns);
        }

        $columnList = collect(array_values($columns));

        return [
            'columns' => $columnList,
            'store_count' => $columnList->count(),
            'openings' => $accountingOnly ? 0 : count($columns['vagas']['cards']),
            'opening_count' => (int) $hires->count(),
            'excess_count' => 0,
            'process_count' => (int) $columnList->sum(fn (array $column) => count($column['cards'])),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $columns
     */
    private function appendOpenSlots(array &$columns): void
    {
        $stores = $this->storeBoard();
        foreach ($stores['columns'] as $store) {
            $open = (int) ($store['open'] ?? 0);
            if ($open < 1) {
                continue;
            }
            for ($slot = 1; $slot <= $open; $slot++) {
                $columns['vagas']['cards'][] = [
                    'style' => 'open-slot',
                    'tag' => 'Vaga em aberto',
                    'title' => 'Vaga livre '.$slot.' de '.$open,
                    'store' => $store['name'],
                    'body' => 'Cota '.$store['quota'].' · '.$store['occupied'].' pessoas ativas nesta loja.',
                ];
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function processStageBoard(?string $stage = null, bool $accountingOnly = false): array
    {
        $pipeline = $accountingOnly
            ? ['contabilidade' => 'Contabilidade']
            : [
                'atendimento' => 'Aguardando atendimento do RH',
                'carta' => '1. Carta a punho',
                'conferencia' => '2. Conferência WA × INSS',
                'direcao' => '3. Decisão da Direção',
                'exame' => '4. Marcação de exame / ASO',
                'correio' => '5. Correio / AR',
                'contabilidade' => '6. Contabilidade',
                'baixa' => '7. Baixa e WhatsApp',
            ];

        $processes = OffboardingProcess::query()
            ->with(['collaborator.homeCompany', 'attachments'])
            ->whereNotNull('collaborator_id')
            ->whereHas('collaborator')
            ->whereIn('status', $accountingOnly
                ? [OffboardingProcess::STAGE_AGUARDANDO_CONTABILIDADE, OffboardingProcess::STAGE_AGUARDANDO_DOCUMENTACAO]
                : OffboardingProcess::OPEN_STATUSES)
            ->when(
                ! $accountingOnly && $stage !== '' && $stage !== null && $stage !== 'inactivity' && $stage !== 'clinic',
                fn ($query) => $query->where('status', $stage),
            )
            ->latest('id')
            ->get();

        $columns = [];
        foreach ($pipeline as $key => $name) {
            $columns[$key] = [
                'id' => $key,
                'name' => $name,
                'city' => null,
                'cards' => [],
            ];
        }

        foreach ($processes as $process) {
            if (! $process->collaborator_id || $process->collaborator === null) {
                continue;
            }
            $key = $process->boardStageKey();
            if (! isset($columns[$key])) {
                continue;
            }
            $columns[$key]['cards'][] = $process->boardCard();
        }

        if (isset($columns['atendimento'])) {
            usort($columns['atendimento']['cards'], function (array $a, array $b): int {
                $aEasy = ((int) ($a['tenure_days'] ?? 0)) < 135 ? 0 : 1;
                $bEasy = ((int) ($b['tenure_days'] ?? 0)) < 135 ? 0 : 1;
                if ($aEasy !== $bEasy) {
                    return $aEasy <=> $bEasy;
                }

                return ((int) ($b['days_without_daily'] ?? 0)) <=> ((int) ($a['days_without_daily'] ?? 0));
            });
        }

        $columnList = collect(array_values($columns));

        return [
            'columns' => $columnList,
            'store_count' => $columnList->count(),
            'opening_count' => (int) $columnList->sum(fn (array $column) => count($column['cards'])),
            'openings' => 0,
            'excess_count' => 0,
            'process_count' => (int) $columnList->sum(fn (array $column) => count($column['cards'])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inactivityBoard(): array
    {
        $audits = InactivityAudit::query()
            ->with(['collaborator.homeCompany'])
            ->whereIn('status', [
                InactivityAudit::STATUS_OPEN_18,
                InactivityAudit::STATUS_WATCH,
                InactivityAudit::STATUS_OPEN_25,
            ])
            ->latest('id')
            ->get();

        $columns = [
            'open_18' => ['id' => 'open_18', 'name' => '18 dias sem diária', 'city' => null, 'cards' => []],
            'watch' => ['id' => 'watch', 'name' => 'Acompanhamento', 'city' => null, 'cards' => []],
            'open_25' => ['id' => 'open_25', 'name' => 'Revisão 25 dias sem diária', 'city' => null, 'cards' => []],
        ];

        foreach ($audits as $audit) {
            $collaborator = $audit->collaborator;
            if (! $collaborator) {
                continue;
            }
            $key = match ($audit->status) {
                InactivityAudit::STATUS_OPEN_25 => 'open_25',
                InactivityAudit::STATUS_WATCH => 'watch',
                default => 'open_18',
            };
            $columns[$key]['cards'][] = [
                'slug' => 'inactivity-'.$audit->id,
                'audit_id' => $audit->id,
                'process_id' => $audit->offboarding_process_id,
                'style' => 'opening-slot',
                'tag' => $audit->status === InactivityAudit::STATUS_OPEN_25 ? 'Revisão 25 dias' : '18 dias sem diária',
                'title' => $collaborator->name,
                'name' => $collaborator->name,
                'store' => $collaborator->homeCompany?->name ?: '—',
                'whatsapp_group' => $collaborator->group ?: '—',
                'body' => $audit->days_without_daily.' dias sem diária',
                'days_without_daily' => $audit->days_without_daily,
                'justify_url' => route('work.inactivity.response', $audit),
                'release_url' => route('work.releases.create', ['collaborator_id' => $collaborator->id]),
                'can_act' => $audit->status !== InactivityAudit::STATUS_OPEN_25,
            ];
        }

        $columnList = collect(array_values($columns));

        return [
            'columns' => $columnList,
            'store_count' => $columnList->count(),
            'opening_count' => (int) $columnList->sum(fn (array $column) => count($column['cards'])),
            'openings' => 0,
            'excess_count' => 0,
            'process_count' => (int) $columnList->sum(fn (array $column) => count($column['cards'])),
            'inactivity' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function financeStageBoard(): array
    {
        $pipeline = [
            'diarias' => '1. Diárias do período',
            'nota' => '2. Emissão da nota',
            'pagamento' => '3. Pagamento / recebimento',
        ];

        $columns = [];
        foreach ($pipeline as $key => $name) {
            $columns[$key] = [
                'id' => $key,
                'name' => $name,
                'city' => null,
                'cards' => [],
            ];
        }

        $batches = FinancialBatches::query()->with('company')->latest('id')->limit(30)->get();
        foreach ($batches as $batch) {
            $key = $batch->attendanceStageKey();
            $columns[$key]['cards'][] = [
                'style' => 'opening-slot',
                'tag' => 'Atendimento',
                'title' => $batch->company?->name ?? 'Lote #'.$batch->id,
                'body' => $batch->attendanceStageLabel()
                    .' · '.$batch->period_start?->format('d/m').' a '.$batch->period_end?->format('d/m')
                    .' · R$ '.number_format((float) $batch->total_amount, 2, ',', '.'),
            ];
        }

        $columnList = collect(array_values($columns));

        return [
            'columns' => $columnList,
            'store_count' => $columnList->count(),
            'opening_count' => (int) $batches->count(),
            'openings' => 0,
            'excess_count' => 0,
            'process_count' => (int) $batches->count(),
        ];
    }

    public function storeIdFor(?Collaborator $collaborator, array $lastStoreByCollaborator = []): ?int
    {
        if (! $collaborator) {
            return null;
        }

        if ($collaborator->home_company_id) {
            return (int) $collaborator->home_company_id;
        }

        $fromDaily = $lastStoreByCollaborator[$collaborator->id] ?? null;

        return $fromDaily ? (int) $fromDaily : null;
    }

    private function lastStoreByCollaborator(Collection $collaboratorIds): array
    {
        if ($collaboratorIds->isEmpty()) {
            return [];
        }

        return DailyRate::query()
            ->select('collaborator_id', 'company_id')
            ->whereIn('collaborator_id', $collaboratorIds)
            ->where('active', true)
            ->whereNotNull('company_id')
            ->orderByDesc('start')
            ->orderByDesc('id')
            ->get()
            ->unique('collaborator_id')
            ->pluck('company_id', 'collaborator_id')
            ->all();
    }

    public function rhDutyCount(): int
    {
        return $this->rhDutyQuery()->count();
    }

    private function rhDutyQuery()
    {
        return OffboardingProcess::query()
            ->whereIn('status', OffboardingProcess::OPEN_STATUSES)
            ->whereNotIn('status', OffboardingProcess::DIRECTION_STAGES)
            ->whereNotNull('collaborator_id')
            ->whereHas('collaborator')
            ->where(function ($query) {
                $query->whereNotIn('status', [
                    OffboardingProcess::STAGE_AGUARDANDO_CONTABILIDADE,
                    OffboardingProcess::STAGE_AGUARDANDO_DOCUMENTACAO,
                ])->orWhere(function ($query) {
                    $query->where('docs_received', true)
                        ->where(function ($query) {
                            $query->where('docs_checked', false)->orWhereNull('docs_checked');
                        });
                });
            });
    }

    public function gestorDutyCount(): int
    {
        return OffboardingProcess::query()
            ->whereIn('status', OffboardingProcess::DIRECTION_STAGES)
            ->whereNotNull('collaborator_id')
            ->whereHas('collaborator')
            ->count();
    }

    public function offboardingBadge(): string
    {
        return $this->formatOffboardingBadge($this->rhDutyCount(), $this->gestorDutyCount());
    }

    private function formatOffboardingBadge(int $rh, int $gestor): string
    {
        $parts = [];

        if ($rh > 0) {
            $parts[] = $rh.' aguardando o RH';
        }
        if ($gestor > 0) {
            $parts[] = $gestor.' '.($gestor === 1 ? 'análise' : 'análises');
        }

        return $parts === [] ? 'Nada pendente' : implode(' · ', $parts);
    }

    /**
     * @param  array{rh: int, review: int, open: int}  $summary
     */
    private function formatDemandBadge(array $summary): string
    {
        if ($summary['open'] === 0) {
            return 'Nada pendente';
        }

        $parts = [];
        if ($summary['rh'] > 0) {
            $parts[] = $summary['rh'].' com o RH';
        }
        if ($summary['review'] > 0) {
            $parts[] = $summary['review'].' em conferência';
        }

        return $parts === [] ? 'Nada pendente' : implode(' · ', $parts);
    }

    public function decisionsFor(User $user): array
    {
        $items = [];
        $seesRh = $this->seesRhDuty($user);
        $seesGestor = $this->seesGestorDuty($user);

        if ($seesRh) {
            foreach ($this->rhDutyQuery()->with(['collaborator.homeCompany'])->latest('id')->limit(20)->get() as $process) {
                $items[] = [
                    'tag' => $process->kindLabel(),
                    'title' => $process->collaborator->name,
                    'body' => $process->label(),
                    'url' => route('work.offboarding.show', $process),
                    'duty' => OffboardingProcess::DUTY_RH,
                    'duty_label' => 'Responsabilidade do RH',
                ];
            }
        }

        if ($seesGestor) {
            foreach (OffboardingProcess::query()->with('collaborator')->whereNotNull('collaborator_id')->whereHas('collaborator')->whereIn('status', OffboardingProcess::DIRECTION_STAGES)->latest('id')->limit(20)->get() as $process) {
                $items[] = [
                    'tag' => $process->kindLabel(),
                    'title' => $process->collaborator->name,
                    'body' => $this->decisionBody($process),
                    'url' => route('work.offboarding.show', $process),
                    'duty' => OffboardingProcess::DUTY_GESTOR,
                    'duty_label' => 'Aprova o Super admin',
                ];
            }
        }

        if ($seesRh || $user->isCoordinator()) {
            foreach (InactivityAudit::query()->with('collaborator')->whereNotNull('collaborator_id')->whereHas('collaborator')->where('status', InactivityAudit::STATUS_OPEN_18)->latest('id')->limit(10)->get() as $audit) {
                $items[] = [
                    'tag' => 'INATIVIDADE',
                    'title' => $audit->collaborator->name,
                    'body' => $audit->days_without_daily.' dias sem diária e sem abono ativo.',
                    'url' => route('work.project', ['project' => 'offboarding', 'stage' => 'inactivity']),
                    'duty' => OffboardingProcess::DUTY_RH,
                    'duty_label' => 'Responsabilidade do RH',
                ];
            }
        }

        return $items;
    }

    private function seesRhDuty(User $user): bool
    {
        return $user->isOwner()
            || $user->isRh()
            || in_array($user->role, ['admin', 'dev'], true)
            || $user->can(AccessControl::PERMISSION_MANAGE_OFFBOARDING);
    }

    public function seesGestorDuty(User $user): bool
    {
        return $user->isOwner()
            || in_array($user->role, ['admin', 'dev'], true)
            || $user->can(AccessControl::PERMISSION_DIRECTION);
    }

    private function decisionBody(OffboardingProcess $process): string
    {
        if ($process->inss_daily_count !== null) {
            $diff = $process->inssDifference();
            if ($diff !== 0) {
                return 'Divergência de diárias WA × INSS.';
            }
        }

        if ($process->kind === OffboardingProcess::KIND_INACTIVITY) {
            return 'Desligamento por inatividade aguardando a Direção.';
        }

        if ($process->kind === OffboardingProcess::KIND_TRANSFER) {
            return 'Transferência sem vaga aceita — custo e momento da empresa.';
        }

        return $process->reason ?: $process->label();
    }
}
