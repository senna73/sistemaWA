<?php

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\ClinicPendingCard;
use App\Models\ClinicPrice;
use App\Models\CliomedWeeklyCheck;
use App\Models\Collaborator;
use App\Models\CollaboratorUniform;
use App\Models\Company;
use App\Models\FinancialBatches;
use App\Models\InactivityAudit;
use App\Models\MedicalClinic;
use App\Models\OffboardingProcess;
use App\Models\ProcessAttachment;
use App\Models\RhCostEntry;
use App\Models\RhTask;
use App\Models\User;
use App\Services\Rh\ClinicPanelService;
use App\Services\Rh\CliomedReconciler;
use App\Services\Rh\CliomedReportParser;
use App\Services\Rh\HiringService;
use App\Services\Rh\InactiveCollaboratorDetector;
use App\Services\Rh\OffboardingService;
use App\Services\Work\WorkHubDemo;
use App\Services\Work\WorkHubService;
use App\Support\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkHubController extends Controller
{
    public function index(WorkHubService $hub, Request $request): View
    {
        $data = $hub->dashboard($request->user());

        return view('work.home', $data);
    }

    public function demo(string $board, WorkHubDemo $demo): View
    {
        $payload = $board === 'demissoes' ? $demo->dismissals() : $demo->hiring();

        return view('work.demo', [
            'mode' => $board,
            'board' => $payload,
        ]);
    }

    public function demoCard(string $board, string $card, WorkHubDemo $demo): View
    {
        $item = $demo->card($card);
        abort_unless($item, 404);

        return view('work.demo-show', [
            'mode' => $board,
            'card' => $item,
        ]);
    }

    public function project(string $project, ClinicPanelService $clinics, Request $request): View
    {
        $user = $request->user();
        $this->assertProjectAccess($user, $project, $request->string('stage')->toString());

        $hub = app(WorkHubService::class);
        $accountingOnly = $this->seesOnlyAccounting($user);

        return match ($project) {
            'offboarding' => $this->offboarding($request, $clinics),
            'recruitment' => view('work.recruitment', [
                'companies' => Company::query()->orderBy('name')->get(),
                'board' => $hub->hireStageBoard($accountingOnly),
            ]),
            'finance' => view('work.finance', [
                'board' => $hub->financeStageBoard(),
            ]),
            'uniforms' => view('work.uniforms', [
                'pending' => CollaboratorUniform::query()->with('collaborator')->whereNull('delivered_at')->latest()->limit(50)->get(),
            ]),
            default => abort(404),
        };
    }

    public function cliomed(Request $request, ClinicPanelService $clinics): View
    {
        abort_unless($request->user()?->managesRhWork(), 403);
        $weekly = $clinics->hydrateOkPeople($clinics->ensureWeeklyCheck());

        return view('work.cliomed', [
            'weekly' => $weekly,
            'state' => $clinics->weeklyState($weekly),
            'groups' => $clinics->inconsistencyGroups($weekly),
        ]);
    }

    public function resolveCliomed(Request $request, ClinicPanelService $clinics): RedirectResponse
    {
        $validated = $request->validate([
            'check_id' => ['required', 'exists:cliomed_weekly_checks,id'],
            'key' => ['required', 'string', 'max:255'],
        ]);
        $check = CliomedWeeklyCheck::query()->findOrFail($validated['check_id']);
        $clinics->resolveInconsistency($check, $validated['key']);

        return redirect()->route('work.cliomed', array_filter([
            'bucket' => $request->string('bucket')->toString() ?: null,
            'q' => $request->string('q')->toString() ?: null,
        ]))->with('status', 'Regra da clínica aplicada.');
    }

    public function offboarding(Request $request, ClinicPanelService $clinics): View
    {
        $stage = $request->string('stage')->toString();
        $summary = $clinics->summary();

        return view('work.offboarding.index', [
            'stage' => $stage,
            'summary' => $summary,
            'coordinatorOnly' => $request->user()?->coordinatorWorkbench() ?? false,
            'board' => $stage === 'inactivity'
                ? app(WorkHubService::class)->inactivityBoard()
                : app(WorkHubService::class)->processStageBoard($stage, $this->seesOnlyAccounting($request->user())),
            'justifications' => \App\Support\PopCatalog::inactivityJustifications(),
        ]);
    }

    public function reviewInactivity(InactiveCollaboratorDetector $detector): RedirectResponse
    {
        $created = $detector->detect();

        return redirect()
            ->route('work.project', 'offboarding')
            ->with('status', $created > 0
                ? $created.' colaborador(es) entraram na revisão de inatividade.'
                : 'Nenhum colaborador novo com 25 dias ou mais sem diária.');
    }

    public function show(Request $request, OffboardingProcess $process): View
    {
        $this->assertProcessReadable($request->user(), $process);
        $process->load(['collaborator.medicalClinic', 'events.actor', 'attachments.uploader', 'tasks', 'costEntries', 'requestedBy', 'newCompany']);

        return view('work.offboarding.show', [
            'process' => $process,
            'companies' => Company::query()->orderBy('name')->get(),
            'checklist' => $process->checklist(),
        ]);
    }

    public function collaboratorData(Request $request, OffboardingProcess $process): View
    {
        $this->assertProcessReadable($request->user(), $process);
        $process->load(['collaborator.medicalClinic', 'collaborator.homeCompany.coordinator', 'collaborator.user']);
        $collaborator = $process->collaborator;
        abort_unless($collaborator, 404);

        $dailies = $collaborator->dailyRates()->with('company')->where('active', true)->orderByDesc('start')->limit(20)->get();
        $audits = $collaborator->inactivityAudits()->latest('id')->limit(10)->get();

        return view('work.offboarding.collaborator', [
            'process' => $process,
            'collaborator' => $collaborator,
            'dailies' => $dailies,
            'audits' => $audits,
        ]);
    }

    public function cliomedChargePdf(ClinicPanelService $clinics)
    {
        $weekly = $clinics->ensureWeeklyCheck();
        $names = $clinics->chargingNames($weekly);
        $html = view('work.cliomed-charge-pdf', ['names' => $names])->render();
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('Relatorio de Cobranca - Remocao de Inativos da Clinica.pdf', ['Attachment' => false]);
    }

    public function attachment(Request $request, OffboardingProcess $process, ProcessAttachment $attachment): StreamedResponse
    {
        $this->assertProcessReadable($request->user(), $process);
        abort_unless($attachment->offboarding_process_id === $process->id, 404);
        abort_unless($attachment->existsOnDisk(), 404);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name);
    }

    public function storeDocument(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:letter,aso,waiver,mail'],
            'file' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $fresh = $offboarding->attachInSequence(
            $process,
            $request->user(),
            $validated['kind'],
            $request->file('file'),
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'stage' => $fresh->boardStageKey(),
            ]);
        }

        return redirect()->route('work.project', 'offboarding')->with('status', 'Documento anexado.');
    }

    public function requestForm(Request $request): View
    {
        $selected = null;
        if ($request->filled('collaborator_id')) {
            $selected = Collaborator::query()->with('medicalClinic', 'homeCompany')->find($request->integer('collaborator_id'));
        }

        $results = $request->filled('q')
            ? Collaborator::query()->where('active', true)->search((string) $request->string('q'))->orderBy('name')->limit(20)->get()
            : collect();

        if (! $selected && $results->count() === 1) {
            $selected = $results->first()->load('medicalClinic', 'homeCompany');
        }

        return view('work.offboarding.request', [
            'collaborator' => $selected,
            'q' => $request->string('q')->toString(),
            'results' => $results,
        ]);
    }

    public function storeRequest(Request $request, OffboardingService $offboarding): RedirectResponse
    {
        $validated = $request->validate([
            'collaborator_id' => ['required', 'exists:collaborators,id'],
            'kind' => ['required', 'in:dismissal,transfer'],
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $collaborator = Collaborator::query()->findOrFail($validated['collaborator_id']);
        $process = $offboarding->open(
            $collaborator,
            $request->user(),
            OffboardingProcess::ORIGIN_COORDINATOR,
            $validated['kind'],
            $validated['notes'] ?? $validated['reason'],
            [
                'reason' => $validated['reason'],
            ]
        );

        return redirect()->route('work.offboarding.show', $process)->with('status', 'Solicitação enviada ao RH.');
    }

    public function startVerification(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        $offboarding->startVerification($process, $request->user());

        return redirect()->route('work.offboarding.show', $process)->with('status', 'Verificação do dossiê iniciada.');
    }

    public function markRegistered(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        abort_unless($request->user()->can(AccessControl::PERMISSION_ACCOUNTING), 403);

        $validated = $request->validate([
            'accounting_registered' => ['required', 'boolean'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $offboarding->markRegistered(
            $process,
            $request->user(),
            $request->boolean('accounting_registered'),
            $request->file('file'),
        );

        return back()->with('status', 'Registro no INSS atualizado.');
    }

    public function readNotification(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $url = $item->data['url'] ?? route('work.home');
        $item->markAsRead();

        return redirect()->to($url);
    }

    public function conference(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        $validated = $request->validate([
            'inss_daily_count' => ['nullable', 'integer', 'min:0'],
            'accounting_registered' => ['nullable', 'boolean'],
            'last_exam_clinic' => ['nullable', 'string', 'max:40'],
            'letter_status' => ['nullable', 'in:anexada,pendente,erro'],
        ]);

        $offboarding->recordConference(
            $process,
            $request->user(),
            isset($validated['inss_daily_count']) ? (int) $validated['inss_daily_count'] : null,
            $request->filled('accounting_registered') ? $request->boolean('accounting_registered') : null,
            $validated['last_exam_clinic'] ?? null,
            $validated['letter_status'] ?? null,
        );

        if ($request->hasFile('letter')) {
            $offboarding->attach($process->fresh(), $request->user(), 'letter', $request->file('letter'));
        }

        return back()->with('status', 'Conferência registrada.');
    }

    public function direction(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:authorize,release,release_pending,return_rh,keep,allow_return'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'correction_qty' => ['nullable', 'integer', 'min:0'],
        ]);

        abort_unless(
            $request->user()->isOwner()
            || $request->user()->can('Minhas Análises Direção')
            || in_array($request->user()->role, ['admin', 'dev'], true),
            403
        );

        $offboarding->directionDecide(
            $process,
            $request->user(),
            $validated['decision'],
            $validated['justification'] ?? null,
            isset($validated['correction_qty']) ? (int) $validated['correction_qty'] : null,
        );

        return back()->with('status', 'Decisão da Direção registrada.');
    }

    public function scheduleExam(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        $validated = $request->validate([
            'exam_at' => ['required', 'date'],
            'exam_location' => ['nullable', 'string', 'max:255'],
        ]);
        $offboarding->scheduleExam($process, $request->user(), $validated['exam_at'], $validated['exam_location'] ?? 'Cliomed');

        return back()->with('status', 'Exame agendado.');
    }

    public function aso(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        $offboarding->attachAso($process, $request->user(), $request->file('aso'));

        return back()->with('status', 'ASO anexado.');
    }

    public function missExam(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        $offboarding->markExamMissed($process, $request->user(), $request->input('notes'));

        return back()->with('status', 'Falta ao exame registrada.');
    }

    public function waiver(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        $offboarding->attachWaiverAndContinue($process, $request->user(), $request->file('waiver'));

        return back()->with('status', 'Declaração anexada.');
    }

    public function mail(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        if ($request->boolean('start_only')) {
            $offboarding->moveToMail($process, $request->user());

            return back()->with('status', 'Movido para Demissões via Correio.');
        }

        $validated = $request->validate(['mail_tracking' => ['required', 'string', 'max:255']]);
        $offboarding->recordMailProof($process, $request->user(), $validated['mail_tracking'], $request->file('mail'));

        return back()->with('status', 'Comprovante de correio registrado.');
    }

    public function docs(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        $offboarding->markDocs($process, $request->user(), $request->boolean('docs_received'), $request->boolean('docs_checked'));

        return back()->with('status', 'Documentação atualizada.');
    }

    public function complete(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        $offboarding->completeDismissal($process, $request->user(), $request->input('notes'));

        return back()->with('status', 'Desligamento concluído.');
    }

    public function transfer(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        if ($request->boolean('reject')) {
            $offboarding->rejectTransfer($process, $request->user(), $request->input('notes'));

            return back()->with('status', 'Transferência em análise da Direção.');
        }

        $validated = $request->validate([
            'new_company_id' => ['required', 'exists:companies,id'],
            'new_role' => ['nullable', 'string', 'max:255'],
            'transfer_start_date' => ['nullable', 'date'],
            'offered_stores' => ['nullable', 'string'],
            'collaborator_reply' => ['nullable', 'string'],
        ]);

        if (! empty($validated['offered_stores'])) {
            $offboarding->offerStores($process, $request->user(), $validated['offered_stores'], $validated['collaborator_reply'] ?? null);
        }

        $offboarding->completeTransfer(
            $process->fresh(),
            $request->user(),
            (int) $validated['new_company_id'],
            $validated['new_role'] ?? null,
            $validated['transfer_start_date'] ?? null,
        );

        return back()->with('status', 'Transferência concluída.');
    }

    public function cancel(Request $request, OffboardingProcess $process, OffboardingService $offboarding): RedirectResponse
    {
        $offboarding->cancel($process, $request->user(), $request->input('notes'));

        return back()->with('status', 'Processo cancelado.');
    }

    public function inactivityResponse(Request $request, InactivityAudit $audit, InactiveCollaboratorDetector $detector): RedirectResponse
    {
        $validated = $request->validate([
            'coordinator_response' => ['required', 'in:justificativa,continuar'],
            'coordinator_notes' => ['nullable', 'string'],
            'justification' => ['nullable', 'string'],
            'scale_date' => ['nullable', 'date'],
            'scale_store' => ['nullable', 'string'],
            'scale_role' => ['nullable', 'string'],
        ]);
        $detector->recordCoordinatorResponse($audit, $request->user(), $validated);

        return back()->with('status', $validated['coordinator_response'] === InactivityAudit::RESPONSE_JUSTIFY
            ? 'Justificativa enviada para Análise do RH.'
            : 'Processo continua até 25 dias.');
    }

    public function allowance(Request $request, InactivityAudit $audit, InactiveCollaboratorDetector $detector): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        $detector->registerAllowance($audit->collaborator, $request->user(), $validated, $request->file('evidence'), $audit);

        return back()->with('status', 'Abono registrado.');
    }

    public function resolveClinic(Request $request, ClinicPendingCard $card, ClinicPanelService $clinics): RedirectResponse|JsonResponse
    {
        $validated = $request->validate(['clinic_id' => ['required', 'exists:medical_clinics,id']]);
        $clinics->resolve($card, $request->user(), (int) $validated['clinic_id']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', 'Clínica regularizada.');
    }

    public function weekly(Request $request, ClinicPanelService $clinics): RedirectResponse
    {
        $validated = $request->validate([
            'check_id' => ['required', 'exists:cliomed_weekly_checks,id'],
            'report_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'complete' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);
        $check = CliomedWeeklyCheck::query()->findOrFail($validated['check_id']);

        if ($request->hasFile('attachment')) {
            $extension = strtolower((string) $request->file('attachment')->getClientOriginalExtension());
            if (! in_array($extension, ['xlsx', 'csv'], true)) {
                throw ValidationException::withMessages([
                    'attachment' => 'Envie o relatório da Cliomed em .xlsx (ou .csv).',
                ]);
            }

            $result = $clinics->ingestReport($check, $request->file('attachment'), app(CliomedReportParser::class), app(CliomedReconciler::class));
            $check = $check->fresh();
            $status = (($result['inconsistency_count'] ?? 0) === 0)
                ? 'Relatório da Cliomed bateu com o sistema. Finalize a conferência para deixar a semana em dia.'
                : ($result['inconsistency_count'].' inconsistência(s) para resolver. O card segue amarelo até finalizar.');

            if (! $request->boolean('complete')) {
                return redirect()->route('work.cliomed')->with('status', $status);
            }
        }

        if ($request->boolean('complete') || (! $request->hasFile('attachment') && $request->filled('report_count'))) {
            $check = $check->fresh();
            if ($check->report_count === null || $clinics->pendingInconsistencyCount($check) > 0) {
                throw ValidationException::withMessages([
                    'complete' => 'Resolva todas as inconsistências antes de finalizar a conferência.',
                ]);
            }

            $clinics->completeWeekly(
                $check,
                $request->user(),
                isset($validated['report_count']) ? (int) $validated['report_count'] : null,
                $validated['notes'] ?? null,
            );

            return redirect()->route('work.cliomed')->with('status', 'Conferência Cliomed em dia.');
        }

        return redirect()->route('work.cliomed')->withErrors(['attachment' => 'Envie a planilha da Cliomed para comparar com o sistema.']);
    }

    public function storeHire(Request $request, HiringService $hiring): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'admission_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $hiring->open(
            $request->user(),
            Company::query()->findOrFail($validated['company_id']),
            $validated['name'],
            $validated['job_title'] ?? null,
            $validated['notes'] ?? null,
            $validated['admission_on'] ?? null,
        );

        return redirect()->route('work.project', 'recruitment')->with('status', 'Contratação aberta.');
    }

    public function hireDocument(Request $request, Candidate $candidate, HiringService $hiring): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:id,exam_proof,aso,accounting,scale'],
            'file' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $fresh = $hiring->attachInSequence(
            $candidate,
            $request->user(),
            $validated['kind'],
            $request->file('file'),
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'stage' => $fresh->boardStageKey(),
            ]);
        }

        return redirect()->route('work.project', 'recruitment')->with('status', 'Documento anexado.');
    }

    public function verifyHire(Request $request, Candidate $candidate, HiringService $hiring): RedirectResponse
    {
        abort_unless($request->user()->can(AccessControl::PERMISSION_ACCOUNTING), 403);

        $hiring->verifyInss($candidate, $request->user());

        return redirect()->route('work.project', 'recruitment')->with('status', 'Registro no INSS verificado.');
    }

    public function hireAttachment(Candidate $candidate, ProcessAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->candidate_id === $candidate->id, 404);
        abort_unless($attachment->existsOnDisk(), 404);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name);
    }

    private function seesOnlyAccounting($user): bool
    {
        return $user->can(AccessControl::PERMISSION_ACCOUNTING)
            && ! $user->can(AccessControl::PERMISSION_MANAGE_OFFBOARDING)
            && ! $user->can(AccessControl::PERMISSION_RECRUITMENT);
    }

    private function assertProjectAccess(?User $user, string $project, string $stage): void
    {
        abort_unless($user, 403);

        $allowed = match ($project) {
            'offboarding' => $user->managesRhWork()
                || $user->can(AccessControl::PERMISSION_ACCOUNTING)
                || ($user->coordinatorWorkbench() && $stage === 'inactivity'),
            'recruitment' => $user->can(AccessControl::PERMISSION_RECRUITMENT)
                || $user->can(AccessControl::PERMISSION_ACCOUNTING)
                || $user->isSuperAdmin(),
            'finance', 'uniforms' => $user->managesRhWork(),
            default => false,
        };

        abort_unless($allowed, 403);
    }

    private function assertProcessReadable(?User $user, OffboardingProcess $process): void
    {
        abort_unless($user, 403);
        abort_unless(
            $user->managesRhWork()
            || $user->can(AccessControl::PERMISSION_ACCOUNTING)
            || $user->can(AccessControl::PERMISSION_DIRECTION)
            || (int) $process->requested_by_user_id === (int) $user->id,
            403
        );
    }

    public function updateQuota(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'headcount_quota' => ['required', 'integer', 'min:0', 'max:500'],
        ]);

        Company::query()->whereKey($validated['company_id'])->update([
            'headcount_quota' => $validated['headcount_quota'],
        ]);

        return back()->with('status', 'Cota da loja atualizada.');
    }

    public function storePrice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'city' => ['required', 'string'],
            'category' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'clinic' => ['nullable', 'string'],
        ]);
        ClinicPrice::create($validated + ['clinic' => $validated['clinic'] ?? 'cliomed']);

        return back()->with('status', 'Tabela de custo atualizada.');
    }

    public function updateCost(Request $request, RhCostEntry $entry): RedirectResponse
    {
        $validated = $request->validate([
            'actual_amount' => ['nullable', 'numeric'],
            'paid_amount' => ['nullable', 'numeric'],
        ]);
        $entry->update($validated);

        return back()->with('status', 'Custo atualizado.');
    }
}
