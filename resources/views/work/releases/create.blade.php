<x-app-layout>
    <div class="container">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Solicitar liberação de diária</h5></div>
            <div class="card-body">
                <p class="text-muted">O bloqueio de 25 dias permanece. A liberação vale só a data informada (ou 48h após a aprovação).</p>
                <form method="POST" action="{{ route('work.releases.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Colaborador</label>
                        @if ($collaborator)
                            <input type="hidden" name="collaborator_id" value="{{ $collaborator->id }}">
                            <input class="form-control" value="{{ $collaborator->name }}" disabled>
                        @else
                            <input type="number" name="collaborator_id" class="form-control" required>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Loja</label>
                        <select name="company_id" class="form-select">
                            <option value="">—</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data da diária</label>
                        <input type="date" name="daily_on" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <textarea name="reason" class="form-control" required></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Enviar pedido</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
