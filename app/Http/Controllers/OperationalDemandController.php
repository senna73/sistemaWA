<?php

namespace App\Http\Controllers;

use App\Models\Collaborator;
use App\Models\OperationalDemand;
use App\Models\OperationalDemandAttachment;
use App\Services\Rh\OperationalDemandService;
use App\Support\PopCatalog;
use App\Support\RhActivitySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalDemandController extends Controller
{
    public function index(Request $request, OperationalDemandService $demands): View|RedirectResponse
    {
        $user = $request->user();
        if ($user?->isRh()) {
            RhActivitySettings::abortUnlessVisible(RhActivitySettings::DEMANDS, $user);

            return redirect()->route('work.demands');
        }

        $canReview = $user?->can(PopCatalog::PERMISSION_REVIEW_DEMAND)
            || $user?->isSuperAdmin();

        if ($canReview) {
            return view('demands.index', [
                'mode' => 'oversight',
                'demands' => null,
                'board' => $demands->typeBoard($user, queueOnly: true),
                'categories' => PopCatalog::demandCategoriesFor($user),
            ]);
        }

        $mine = OperationalDemand::query()
            ->with(['opener', 'assignee', 'collaborator'])
            ->where(function ($inner) use ($user) {
                $inner->where('opened_by', $user?->id)->orWhere('assigned_to', $user?->id);
            })
            ->latest('id')
            ->paginate(30);

        return view('demands.index', [
            'mode' => 'mine',
            'demands' => $mine,
            'board' => null,
            'categories' => PopCatalog::demandCategories(),
        ]);
    }

    public function workQueue(Request $request, OperationalDemandService $demands): View
    {
        $user = $request->user();
        abort_unless($user?->isRh() || $user?->isSuperAdmin(), 403);
        abort_unless(
            $user?->can(PopCatalog::PERMISSION_HANDLE_DEMAND)
            || $user?->can(PopCatalog::PERMISSION_REVIEW_DEMAND)
            || $user?->isSuperAdmin(),
            403
        );
        RhActivitySettings::abortUnlessVisible(RhActivitySettings::DEMANDS, $user);

        return view('demands.index', [
            'mode' => $user->isRh() ? 'queue' : 'oversight',
            'demands' => null,
            'board' => $demands->typeBoard($user, queueOnly: true),
            'categories' => PopCatalog::demandCategories(),
        ]);
    }

    public function create(): View
    {
        $user = auth()->user();
        abort_unless($user && PopCatalog::canOpenOperationalDemand($user), 403);

        return view('demands.create', [
            'categories' => PopCatalog::demandCategoriesFor($user),
            'collaborators' => Collaborator::query()->where('active', true)->orderBy('name')->limit(400)->get(['id', 'name', 'mobile', 'group', 'pix_key', 'document']),
            'groups' => Collaborator::query()
                ->whereNotNull('group')
                ->where('group', '!=', '')
                ->distinct()
                ->orderBy('group')
                ->pluck('group'),
        ]);
    }

    public function store(Request $request, OperationalDemandService $demands): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && PopCatalog::canOpenOperationalDemand($user), 403);

        $categories = implode(',', array_keys(PopCatalog::demandCategoriesFor($user)));
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'collaborator_id' => ['nullable', 'exists:collaborators,id'],
            'category' => ['required', 'in:'.$categories],
            'request_text' => ['required', 'string', 'max:4000'],
            'payload' => ['nullable', 'array'],
            'payload.pix_key' => ['nullable', 'string', 'max:255'],
            'payload.group' => ['nullable', 'string', 'max:255'],
            'payload.name' => ['nullable', 'string', 'max:255'],
            'payload.mobile' => ['nullable', 'string', 'max:30'],
            'payload.document' => ['nullable', 'string', 'max:30'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120'],
        ]);

        $validated['payload'] = PopCatalog::payloadFromInput($validated['category'], $validated);
        $files = $request->file('attachments', []);
        $collaborator = isset($validated['collaborator_id'])
            ? Collaborator::query()->find($validated['collaborator_id'])
            : null;
        if ($collaborator) {
            $validated['name'] = ($validated['name'] ?? null) ?: $collaborator->name;
            $validated['mobile'] = ($validated['mobile'] ?? null) ?: $collaborator->mobile;
        }
        $validated['name'] = ($validated['name'] ?? null) ?: $user->name;

        $demands->open($user, $validated, is_array($files) ? $files : [], $collaborator);

        return redirect()->route('demands.index')->with('status', 'Demanda aberta.');
    }

    public function show(OperationalDemand $demand): View
    {
        $user = request()->user();
        $canHandle = $user?->can(PopCatalog::PERMISSION_HANDLE_DEMAND)
            || $user?->can(PopCatalog::PERMISSION_REVIEW_DEMAND)
            || $user?->isSuperAdmin();
        abort_unless(
            $canHandle || $demand->opened_by === $user?->id || $demand->assigned_to === $user?->id,
            403
        );

        $demand->load(['events.user', 'attachments', 'opener', 'collaborator.homeCompany', 'agendaItem']);

        return view('demands.show', [
            'demand' => $demand,
            'categories' => PopCatalog::demandCategories(),
            'canOperate' => (bool) $user?->isRh(),
            'groups' => $this->whatsappGroups(),
        ]);
    }

    public function start(OperationalDemand $demand, OperationalDemandService $demands): RedirectResponse
    {
        RhActivitySettings::abortUnlessVisible(RhActivitySettings::DEMANDS, request()->user());
        $demands->start($demand, request()->user());

        return back()->with('status', 'Demanda em atendimento.');
    }

    public function apply(Request $request, OperationalDemand $demand, OperationalDemandService $demands): RedirectResponse
    {
        abort_unless($request->user()?->isRh(), 403);
        RhActivitySettings::abortUnlessVisible(RhActivitySettings::DEMANDS, $request->user());

        $validated = $request->validate([
            'payload' => ['nullable', 'array'],
            'payload.pix_key' => ['nullable', 'string', 'max:255'],
            'payload.group' => ['nullable', 'string', 'max:255'],
            'payload.name' => ['nullable', 'string', 'max:255'],
            'payload.mobile' => ['nullable', 'string', 'max:30'],
            'payload.document' => ['nullable', 'string', 'max:30'],
        ]);

        $demands->applyFromCard($demand, $request->user(), $validated);

        return back()->with('status', 'Cadastro atualizado neste card.');
    }

    public function note(Request $request, OperationalDemand $demand, OperationalDemandService $demands): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);
        $demands->note($demand, $request->user(), $validated['notes'], $request->file('attachment'));

        return back()->with('status', 'Nota registrada.');
    }

    public function review(OperationalDemand $demand, OperationalDemandService $demands): RedirectResponse
    {
        $demands->sendToReview($demand, request()->user());

        return back()->with('status', 'Demanda na conferência.');
    }

    public function returnToProgress(Request $request, OperationalDemand $demand, OperationalDemandService $demands): RedirectResponse
    {
        $demands->returnToProgress($demand, $request->user(), $request->input('notes'));

        return back()->with('status', 'Demanda voltou para atendimento.');
    }

    public function finish(Request $request, OperationalDemand $demand, OperationalDemandService $demands): RedirectResponse
    {
        $demands->finish($demand, $request->user(), $request->input('notes'));

        return back()->with('status', 'Demanda finalizada.');
    }

    public function attachment(OperationalDemand $demand, OperationalDemandAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->operational_demand_id === $demand->id, 404);
        abort_unless($attachment->existsOnDisk(), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function whatsappGroups()
    {
        return Collaborator::query()
            ->whereNotNull('group')
            ->where('group', '!=', '')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');
    }
}
