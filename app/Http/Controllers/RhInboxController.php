<?php

namespace App\Http\Controllers;

use App\Models\Collaborator;
use App\Models\OffboardingProcess;
use App\Models\RhTask;
use App\Services\Rh\OffboardingService;
use App\Services\Work\WorkHubService;
use App\Support\RhActivitySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RhInboxController extends Controller
{
    public function index(Request $request, WorkHubService $hub): View
    {
        abort_unless($hub->seesGestorDuty($request->user()), 403);
        RhActivitySettings::abortUnlessVisible(RhActivitySettings::INBOX, $request->user());

        $focus = $request->query('focus', 'open');
        if (! in_array($focus, ['open', 'rh', 'gestor', 'wait'], true)) {
            $focus = 'open';
        }

        return view('app.rh.inbox', $hub->rhProgress() + ['focus' => $focus]);
    }

    public function decideReallocate(Request $request, RhTask $task, OffboardingService $offboarding): RedirectResponse
    {
        $this->assertProcessTask($task);
        $offboarding->decideReallocate($task->process, $request->user(), $request->input('notes'));

        return back()->with('status', 'Processo enviado para realocação.');
    }

    public function decideDismiss(Request $request, RhTask $task, OffboardingService $offboarding): RedirectResponse
    {
        $this->assertProcessTask($task);
        $offboarding->decideDismiss($task->process, $request->user(), $request->input('notes'));

        return back()->with('status', 'Demissão iniciada. Novas diárias ficam bloqueadas.');
    }

    public function completeReallocation(Request $request, RhTask $task, OffboardingService $offboarding): RedirectResponse
    {
        $this->assertProcessTask($task);
        $offboarding->completeReallocation($task->process, $request->user(), $request->input('notes'));

        return back()->with('status', 'Realocação concluída.');
    }

    public function proceedToDismissal(Request $request, RhTask $task, OffboardingService $offboarding): RedirectResponse
    {
        $this->assertProcessTask($task);
        $offboarding->proceedToDismissal($task->process, $request->user(), $request->input('notes'));

        return back()->with('status', 'Seguiu para demissão.');
    }

    public function completeDismissal(Request $request, RhTask $task, OffboardingService $offboarding): RedirectResponse
    {
        $this->assertProcessTask($task);
        $offboarding->completeDismissal($task->process, $request->user(), $request->input('notes'));

        return back()->with('status', 'Demissão concluída.');
    }

    public function cancelProcess(Request $request, RhTask $task, OffboardingService $offboarding): RedirectResponse
    {
        $this->assertProcessTask($task);
        $offboarding->cancel($task->process, $request->user(), $request->input('notes'));

        return back()->with('status', 'Processo cancelado.');
    }

    public function resolveInactivity(Request $request, RhTask $task, OffboardingService $offboarding): RedirectResponse
    {
        $offboarding->resolveInactivity($task, $request->user(), $request->input('notes'));

        return back()->with('status', 'Inatividade resolvida.');
    }

    public function openOffboardingFromInactivity(Request $request, RhTask $task, OffboardingService $offboarding): RedirectResponse
    {
        $offboarding->openOffboardingFromInactivity($task, $request->user(), $request->input('notes'));

        return back()->with('status', 'Desligamento aberto a partir da inatividade.');
    }

    public function requestForCollaborator(Request $request, Collaborator $collaborator, OffboardingService $offboarding): RedirectResponse
    {
        RhActivitySettings::abortUnlessVisible(RhActivitySettings::OFFBOARDING_REQUEST, $request->user());
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $process = $offboarding->open(
            $collaborator,
            $request->user(),
            OffboardingProcess::ORIGIN_COORDINATOR,
            $request->input('kind', OffboardingProcess::KIND_DISMISSAL),
            $validated['notes'] ?? null,
            ['reason' => $validated['notes'] ?? null]
        );

        return redirect()
            ->route('work.offboarding.show', $process)
            ->with('status', 'Card de demissão aberto para o RH, vinculado a '.$collaborator->name.'.');
    }

    private function assertProcessTask(RhTask $task): void
    {
        abort_unless($task->process, 404);
        $task->loadMissing('process.collaborator');
    }
}
