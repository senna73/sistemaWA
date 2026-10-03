<?php

namespace App\Http\Controllers;

use App\Models\OperationalDemand;
use App\Models\OperationalDemandAttachment;
use App\Services\Rh\OperationalDemandService;
use App\Support\PopCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalDemandController extends Controller
{
    public function index(Request $request): View
    {
        $query = OperationalDemand::query()->with(['opener', 'assignee', 'collaborator'])->latest('id');
        $user = $request->user();
        $canHandle = $user?->can(PopCatalog::PERMISSION_HANDLE_DEMAND) || $user?->can(PopCatalog::PERMISSION_REVIEW_DEMAND) || $user?->isSuperAdmin();
        if (! $canHandle) {
            $query->where(function ($inner) use ($user) {
                $inner->where('opened_by', $user?->id)->orWhere('assigned_to', $user?->id);
            });
        }

        return view('demands.index', [
            'demands' => $query->paginate(30),
            'categories' => PopCatalog::demandCategories(),
        ]);
    }

    public function create(): View
    {
        return view('demands.create', [
            'categories' => PopCatalog::demandCategories(),
            'collaborator' => auth()->user()?->collaborator,
        ]);
    }

    public function store(Request $request, OperationalDemandService $demands): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'category' => ['required', 'in:'.implode(',', array_keys(PopCatalog::demandCategories()))],
            'request_text' => ['required', 'string', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120'],
        ]);

        $files = $request->file('attachments', []);
        $demands->open($request->user(), $validated, is_array($files) ? $files : [], $request->user()?->collaborator);

        return redirect()->route('demands.index')->with('status', 'Demanda aberta.');
    }

    public function show(OperationalDemand $demand): View
    {
        $demand->load(['events.user', 'attachments', 'opener', 'collaborator', 'agendaItem']);

        return view('demands.show', [
            'demand' => $demand,
            'categories' => PopCatalog::demandCategories(),
        ]);
    }

    public function start(OperationalDemand $demand, OperationalDemandService $demands): RedirectResponse
    {
        $demands->start($demand, request()->user());

        return back()->with('status', 'Demanda em atendimento.');
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
}
