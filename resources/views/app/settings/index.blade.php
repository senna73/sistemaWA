<x-app-layout>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="mb-4">
            <h4 class="mb-1">Configurações</h4>
            <p class="text-muted mb-0">Libere ou bloqueie telas do portal do colaborador.</p>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="card" style="max-width: 640px;">
            <div class="card-header">
                <h5 class="mb-0">Portal do colaborador</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="collaborator_earnings_enabled" name="collaborator_earnings_enabled" value="1" @checked($earningsEnabled)>
                        <label class="form-check-label" for="collaborator_earnings_enabled">
                            Liberar <strong>Meu saldo</strong> para colaboradores
                        </label>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="collaborator_daily_rates_enabled" name="collaborator_daily_rates_enabled" value="1" @checked($dailyRatesEnabled)>
                        <label class="form-check-label" for="collaborator_daily_rates_enabled">
                            Liberar <strong>Diárias</strong> para colaboradores
                        </label>
                    </div>

                    <p class="text-muted small">Enquanto estiver desligado, o colaborador fica só com Meu cadastro. Super admin continua vendo saldo e diárias.</p>

                    <button class="btn btn-primary" type="submit">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
