<x-app-layout>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="mb-4">
            <h4 class="mb-1">Configurações</h4>
            <p class="text-muted mb-0">Libere ou esconda telas do portal do colaborador e das atividades do RH.</p>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Portal do colaborador</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="collaborator_earnings_enabled" name="collaborator_earnings_enabled" value="1" @checked($earningsEnabled)>
                                <label class="form-check-label" for="collaborator_earnings_enabled">
                                    Liberar <strong>Meu saldo</strong> para colaboradores
                                </label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="collaborator_daily_rates_enabled" name="collaborator_daily_rates_enabled" value="1" @checked($dailyRatesEnabled)>
                                <label class="form-check-label" for="collaborator_daily_rates_enabled">
                                    Liberar <strong>Diárias</strong> para colaboradores
                                </label>
                            </div>

                            <p class="text-muted small mb-0">Enquanto estiver desligado, o colaborador fica só com Meu cadastro. Super admin continua vendo saldo e diárias.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Atividades do RH</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Cada card do RH Controle entra ligado. Desative só o que a operação não deve usar agora. Super admin continua vendo tudo.</p>

                            @foreach ($rhActivities as $key => $activity)
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="rh_activity_{{ $key }}" name="rh_activity_{{ $key }}" value="1" @checked($activity['enabled'])>
                                    <label class="form-check-label" for="rh_activity_{{ $key }}">
                                        Mostrar <strong>{{ $activity['label'] }}</strong>
                                        <span class="d-block text-muted small">{{ $activity['hint'] }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary" type="submit">Salvar</button>
            </div>
        </form>
    </div>
</x-app-layout>
