<x-app-layout>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="mb-4">
            <h4 class="mb-1">Configurações</h4>
            <p class="text-muted mb-0">Libere telas do portal e defina, por setor, quem vê cada área do RH Controle.</p>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-lg-5">
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

                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Atividades do RH</h5>
                            <p class="text-muted small mb-0 mt-1">O diretor sempre vê tudo. O <strong>setor da agenda</strong> restringe a tela a quem está naquele setor. Sem setor marcado, vale só o papel. Com os dois, a pessoa precisa do papel e do setor.</p>
                        </div>
                        <div class="card-body">
                            @foreach ($rhActivities as $key => $activity)
                                <div class="border rounded p-3 mb-3">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="rh_activity_{{ $key }}" name="rh_activity_{{ $key }}" value="1" @checked($activity['enabled'])>
                                        <label class="form-check-label" for="rh_activity_{{ $key }}">
                                            Mostrar <strong>{{ $activity['label'] }}</strong>
                                            <span class="d-block text-muted small">{{ $activity['hint'] }}</span>
                                        </label>
                                    </div>
                                    <div class="ps-1">
                                        <div class="small fw-semibold mb-1">Papel</div>
                                        <div class="d-flex flex-wrap gap-3 mb-2">
                                            @foreach ($audienceRoles as $role => $label)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="rh_activity_{{ $key }}_audience_{{ $role }}" name="rh_activity_{{ $key }}_audiences[]" value="{{ $role }}" @checked(in_array($role, $activity['selected_audiences'], true))>
                                                    <label class="form-check-label" for="rh_activity_{{ $key }}_audience_{{ $role }}">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="small fw-semibold mb-1">Setor da agenda</div>
                                        <div class="d-flex flex-wrap gap-3">
                                            @foreach ($audienceSectors as $sector => $label)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="rh_activity_{{ $key }}_sector_{{ $sector }}" name="rh_activity_{{ $key }}_sectors[]" value="{{ $sector }}" @checked(in_array($sector, $activity['selected_sectors'], true))>
                                                    <label class="form-check-label" for="rh_activity_{{ $key }}_sector_{{ $sector }}">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
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
