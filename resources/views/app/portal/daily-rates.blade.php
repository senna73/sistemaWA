<x-app-layout>
    <div class="container">
        @include('app.portal.partials.lookup')

        @if ($collaborator && $dailyRates)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Diárias</h5>
            </div>
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('portal.daily-rates') }}" class="row g-3 align-items-end">
                    @if ($canSearch)
                        <input type="hidden" name="collaborator_id" value="{{ $collaborator->id }}">
                        @if ($q !== '')
                            <input type="hidden" name="q" value="{{ $q }}">
                        @endif
                    @endif
                    <div class="col-sm-4 col-md-3">
                        <label for="month" class="form-label">Mês</label>
                        <input type="month" class="form-control" id="month" name="month" value="{{ $month }}">
                    </div>
                    <div class="col-sm-8 col-md-6">
                        <label class="form-label d-block">Quinzena</label>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="quinzena" id="quinzena-1" value="1" @checked($quinzena === 1)>
                            <label class="btn btn-outline-primary" for="quinzena-1">1ª (1 a 15)</label>
                            <input type="radio" class="btn-check" name="quinzena" id="quinzena-2" value="2" @checked($quinzena === 2)>
                            <label class="btn btn-outline-primary" for="quinzena-2">2ª (16 a {{ $secondQuinzenaTo->format('d') }})</label>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-3">
                        <button type="submit" class="btn btn-primary">Exibir</button>
                    </div>
                </form>
                <p class="text-muted small mb-0 mt-3">
                    {{ $quinzenaFrom->format('d/m/Y') }} a {{ $quinzenaTo->format('d/m/Y') }}
                    · {{ $dailyRates->total() }} {{ $dailyRates->total() === 1 ? 'diária' : 'diárias' }}
                </p>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Estabelecimento</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailyRates as $daily)
                            @php
                                $situation = match ($daily->status) {
                                    'processado' => 'Recebida',
                                    'cancelado' => 'Cancelada',
                                    default => 'A receber',
                                };
                            @endphp
                            <tr>
                                <td>{{ $daily->start?->format('d/m/Y') }}</td>
                                <td>{{ $daily->company?->name ?? '—' }}</td>
                                <td>{{ $situation }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted">Nenhum registro nesta quinzena.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $dailyRates->links() }}</div>
        </div>
        @endif
    </div>
</x-app-layout>
