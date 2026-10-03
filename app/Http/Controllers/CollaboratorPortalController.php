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
        $audit = $collaborator
            ? $collaborator->inactivityAudits()
                ->whereIn('status', [\App\Models\InactivityAudit::STATUS_OPEN_18, \App\Models\InactivityAudit::STATUS_WATCH])
                ->latest('id')
                ->first()
            : null;

        return view('app.portal.show', array_merge($context, [
            'wallet' => $collaborator ? $this->walletFor($collaborator) : null,
            'process' => $collaborator
                ? (app(OffboardingService::class)->openProcessFor($collaborator)
                    ?? $collaborator->offboardingProcesses()->latest('id')->first())
                : null,
            'inactivityAudit' => $audit,
            'justifications' => \App\Support\PopCatalog::inactivityJustifications(),
            'canEditPix' => (bool) $request->user()?->collaborator_id && $collaborator && (int) $request->user()->collaborator_id === (int) $collaborator->id,
        ]));
    }

    public function earnings(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->blockUnlessPortalFeature($request, 'seesPortalEarnings', 'Saldo será liberado após a atualização cadastral. Por enquanto use Meu cadastro.')) {
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
        if ($redirect = $this->blockUnlessPortalFeature($request, 'seesPortalDailyRates', 'Diárias serão liberadas após a atualização cadastral. Por enquanto use Meu cadastro.')) {
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
        $collaborator = $this->ownCollaborator($request);

        $validated = $request->validate([
            'pix_key' => ['required', 'string', 'max:255'],
        ]);

        $this->openCollaboratorRequest($request, $collaborator, 'troca_pix', 'Troca de chave Pix para '.$validated['pix_key'].'.', [
            'pix_key' => $validated['pix_key'],
        ]);

        return back()->with('status', 'Pedido de troca de Pix enviado. O RH confere e atualiza o cadastro.');
    }

    public function requests(Request $request): View
    {
        $context = $this->portalContext($request);
        $collaborator = $context['collaborator'];
        $demands = $collaborator
            ? $collaborator->operationalDemands()->with('agendaItem')->latest('id')->paginate(20)->withQueryString()
            : null;

        return view('app.portal.requests', array_merge($context, [
            'demands' => $demands,
            'categories' => \App\Support\PopCatalog::collaboratorRequestCategories(),
            'canRequest' => (bool) $request->user()?->collaborator_id && $collaborator && (int) $request->user()->collaborator_id === (int) $collaborator->id,
        ]));
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $collaborator = $this->ownCollaborator($request);
        $validated = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(\App\Support\PopCatalog::collaboratorRequestCategories()))],
            'request_text' => ['required', 'string', 'max:4000'],
            'pix_key' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120'],
        ]);

        if ($validated['category'] === 'troca_pix' && blank($validated['pix_key'] ?? null)) {
            return back()->withErrors(['pix_key' => 'Informe a nova chave Pix.'])->withInput();
        }

        $payload = $validated['category'] === 'troca_pix' ? ['pix_key' => $validated['pix_key']] : null;
        $text = $validated['request_text'];
        if ($payload) {
            $text = 'Nova chave Pix: '.$payload['pix_key']."\n".$text;
        }

        $this->openCollaboratorRequest(
            $request,
            $collaborator,
            $validated['category'],
            $text,
            $payload,
            $request->file('attachments', [])
        );

        return back()->with('status', 'Solicitação enviada. Virou atividade para o RH.');
    }

    public function inactivityResponse(Request $request, \App\Models\InactivityAudit $audit, \App\Services\Rh\InactiveCollaboratorDetector $detector): RedirectResponse
    {
        $collaborator = $this->ownCollaborator($request);
        abort_unless((int) $audit->collaborator_id === (int) $collaborator->id, 403);

        $validated = $request->validate([
            'coordinator_response' => ['required', 'in:justificativa,continuar'],
            'justification' => ['nullable', 'string'],
        ]);
        $detector->recordCoordinatorResponse($audit, $request->user(), $validated);

        return back()->with('status', $validated['coordinator_response'] === 'justificativa'
            ? 'Justificativa enviada para Análise do RH.'
            : 'Processo continua até 25 dias.');
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
     * @param  array<string, mixed>|null  $payload
     * @param  array<int, mixed>  $files
     */
    private function openCollaboratorRequest(
        Request $request,
        Collaborator $collaborator,
        string $category,
        string $text,
        ?array $payload = null,
        array $files = [],
    ): void {
        $releases = app(\App\Services\Rh\DailyRateReleaseService::class);
        if ($category === 'troca_pix' && $releases->paymentInProgress($collaborator)) {
            $text .= ' Pagamento em andamento: não aplicar a chave até o lote fechar.';
        }

        app(\App\Services\Rh\OperationalDemandService::class)->open(
            $request->user(),
            [
                'name' => $collaborator->name,
                'mobile' => $collaborator->mobile,
                'category' => $category,
                'request_text' => $text,
                'payload' => $payload,
            ],
            is_array($files) ? $files : [],
            $collaborator
        );
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

    private function blockUnlessPortalFeature(Request $request, string $ability, string $message): ?RedirectResponse
    {
        $user = $request->user();

        if (! $user || $user->{$ability}()) {
            return null;
        }

        return redirect()
            ->route('portal.show')
            ->with('status', $message);
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
