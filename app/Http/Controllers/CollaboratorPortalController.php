<?php

namespace App\Http\Controllers;

use App\Models\Collaborator;
use App\Models\CollaboratorWallet;
use App\Models\CollaboratorWalletTransactions;
use App\Models\OffboardingProcess;
use App\Services\Rh\OffboardingService;
use App\Support\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CollaboratorPortalController extends Controller
{
    public function show(Request $request): View
    {
        $context = $this->portalContext($request);
        $collaborator = $context['collaborator'];

        return view('app.portal.show', array_merge($context, [
            'wallet' => $collaborator ? $this->walletFor($collaborator) : null,
            'process' => $collaborator
                ? (app(OffboardingService::class)->openProcessFor($collaborator)
                    ?? $collaborator->offboardingProcesses()->latest('id')->first())
                : null,
        ]));
    }

    public function earnings(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->blockCollaboratorEarningsIfLocked($request)) {
            return $redirect;
        }

        $context = $this->portalContext($request);
        $collaborator = $context['collaborator'];
        $wallet = $collaborator ? $this->walletFor($collaborator) : null;

        $transactions = $wallet
            ? CollaboratorWalletTransactions::query()
                ->where('collaborator_wallet_id', $wallet->id)
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString()
            : null;

        return view('app.portal.earnings', array_merge($context, [
            'wallet' => $wallet,
            'transactions' => $transactions,
        ]));
    }

    public function dailyRates(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->blockCollaboratorEarningsIfLocked($request)) {
            return $redirect;
        }

        $context = $this->portalContext($request);
        $collaborator = $context['collaborator'];

        $dailyRates = $collaborator
            ? $collaborator->dailyRates()
                ->with('company')
                ->where('active', true)
                ->orderByDesc('start')
                ->paginate(20)
                ->withQueryString()
            : null;

        return view('app.portal.daily-rates', array_merge($context, [
            'dailyRates' => $dailyRates,
        ]));
    }

    public function update(Request $request): RedirectResponse
    {
        abort(403);
    }

    public function requestDismissal(Request $request, OffboardingService $offboarding): RedirectResponse
    {
        abort_if($request->user()?->isSuperAdmin(), 403);

        $collaborator = $this->ownCollaborator($request);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'letter_status' => ['nullable', 'in:anexada,pendente,erro'],
        ]);

        $process = $offboarding->open(
            $collaborator,
            $request->user(),
            OffboardingProcess::ORIGIN_COLLABORATOR,
            OffboardingProcess::KIND_RESIGNATION,
            $validated['notes'] ?? null,
            ['letter_status' => $validated['letter_status'] ?? OffboardingProcess::LETTER_PENDENTE]
        );

        if ($request->hasFile('letter')) {
            $offboarding->attach($process, $request->user(), 'letter', $request->file('letter'));
        }

        return back()->with('status', 'Pedido de demissão enviado. O RH já tem um card vinculado a você.');
    }

    /**
     * @return array{canSearch: bool, collaborator: ?Collaborator, searchResults: Collection, q: string}
     */
    private function portalContext(Request $request): array
    {
        $this->assertPortalOrSuperAdmin($request);

        $canSearch = (bool) $request->user()?->isSuperAdmin();
        $results = $this->searchResults($request, $canSearch);
        $collaborator = $canSearch
            ? $this->collaboratorForLookup($request, $results)
            : $this->ownCollaborator($request);

        return [
            'canSearch' => $canSearch,
            'collaborator' => $collaborator,
            'searchResults' => $results,
            'q' => $request->string('q')->toString(),
        ];
    }

    private function blockCollaboratorEarningsIfLocked(Request $request): ?RedirectResponse
    {
        $user = $request->user();

        if (! $user || $user->seesPortalEarningsAndDailyRates()) {
            return null;
        }

        return redirect()
            ->route('portal.show')
            ->with('status', 'Saldo e diárias serão liberados após a atualização cadastral. Por enquanto use Meu cadastro.');
    }

    private function assertPortalOrSuperAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->can(AccessControl::PERMISSION_PORTAL) || $user->isSuperAdmin()),
            403
        );
    }

    private function ownCollaborator(Request $request): Collaborator
    {
        abort_unless($request->user()?->can(AccessControl::PERMISSION_PORTAL), 403);

        $collaborator = $request->user()->collaborator;

        abort_unless($collaborator, 403);

        return $collaborator;
    }

    private function collaboratorForLookup(Request $request, Collection $results): ?Collaborator
    {
        if ($request->filled('collaborator_id')) {
            return Collaborator::query()->findOrFail($request->integer('collaborator_id'));
        }

        if ($results->count() === 1) {
            return $results->first();
        }

        return $request->user()?->collaborator;
    }

    private function searchResults(Request $request, bool $canSearch): Collection
    {
        if (! $canSearch || ! $request->filled('q')) {
            return collect();
        }

        return Collaborator::query()
            ->where('active', true)
            ->search((string) $request->string('q'))
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    private function walletFor(Collaborator $collaborator): CollaboratorWallet
    {
        return CollaboratorWallet::firstOrCreate(
            ['collaborator_id' => $collaborator->id],
            ['balance' => 0, 'total_added' => 0, 'total_spent' => 0]
        );
    }
}
