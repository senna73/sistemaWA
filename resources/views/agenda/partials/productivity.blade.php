<div class="mb-4">
    <h5 class="mb-3">Produtividade</h5>
    @if ($productivity->isEmpty())
        <p class="text-muted">Nenhuma pessoa com função cadastrada.</p>
    @else
        <div class="row g-3">
            @foreach ($productivity as $row)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100" data-agenda-productivity="{{ $row['user']->id }}">
                        <div class="card-body">
                            <div class="fw-semibold">{{ $row['user']->name }}</div>
                            <div class="small text-muted mb-2">{{ $row['user']->workFunctionLabel() }}</div>
                            <div class="small">Recebidas: <span data-agenda-received>{{ $row['received'] }}</span></div>
                            <div class="small">Concluídas: <span data-agenda-done>{{ $row['done'] }}</span></div>
                            <div class="small">Atrasadas: <span data-agenda-late>{{ $row['late'] }}</span></div>
                            <div class="mt-2">Conclusão: <strong data-agenda-percent>{{ $row['percent'] }}%</strong></div>
                            @if ($row['recent']->isNotEmpty())
                                <div class="small text-muted mt-3 mb-1">Atividades recentes</div>
                                @foreach ($row['recent'] as $activity)
                                    <div class="d-flex justify-content-between small">
                                        <span>{{ $activity->title }}</span>
                                        @if ($activity->effort_hours)
                                            <span>{{ (float) $activity->effort_hours }}h</span>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
