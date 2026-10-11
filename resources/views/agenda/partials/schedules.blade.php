<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Agendas da plataforma</h5></div>
    <div class="card-body">
        <p class="small text-muted">Cada agenda é de um setor. O prazo da atividade é o desta configuração. A atividade entra sozinha quando faltam 7 dias ou menos.</p>
        <form method="POST" action="{{ route('agenda.schedules.store') }}" class="row g-2 mb-4" data-agenda-form="refresh">
            @csrf
            @include('agenda.partials.schedule-fields', ['schedule' => null])
            <div class="col-md-2"><button class="btn btn-primary" type="submit">Criar agenda</button></div>
        </form>

        @forelse ($schedules as $schedule)
            <form method="POST" action="{{ route('agenda.schedules.update', $schedule) }}" class="row g-2 border rounded p-2 mb-2" data-agenda-form="refresh">
                @csrf
                @method('PUT')
                @include('agenda.partials.schedule-fields', ['schedule' => $schedule])
                <div class="col-md-2"><button class="btn btn-outline-primary" type="submit">Salvar</button></div>
            </form>
        @empty
            <p class="text-muted mb-0">Nenhuma agenda configurada.</p>
        @endforelse
    </div>
</div>
