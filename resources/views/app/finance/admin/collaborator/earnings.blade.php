<x-app-layout>
    <div class="card">
        <div class="card-body">
            <h5>Saldo em Carteira</h5>
            <h2 class="text-success">R$ {{ number_format($wallet->balance, 2, ',', '.') }}</h2>
            <small class="text-muted">A receber: R$ {{ number_format($pendingAmount, 2, ',', '.') }}</small>
        </div>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Descrição</th>
                <th>Tipo</th>
                <th>Valor</th>
                <th>Saldo Após</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $tx)
            <tr>
                <td>{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $tx->description }}</td>
                <td>
                    <span class="badge {{ $tx->type == 'credit' ? 'bg-success' : 'bg-danger' }}">
                        {{ strtoupper($tx->type) }}
                    </span>
                </td>
                <td>R$ {{ number_format($tx->amount, 2, ',', '.') }}</td>
                <td class="text-muted">R$ {{ number_format($tx->balance_after, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="row mb-4">
    <div class="col-md-4">
        <div class="input-group">
            <input type="number" id="collaborator_id" class="form-control" placeholder="ID do Colaborador..." min="1">
            <button class="btn btn-primary" type="button" onclick="searchCollaborator()">
                Buscar Carteira
            </button>
        </div>
    </div>
</div>

<script>
    function searchCollaborator() {
        const id = document.getElementById('collaborator_id').value;
        if (id) {
            // Redireciona para /collaborator/earnings/{id}
            window.location.href = "{{ route('admin.collaborator.earnings.single', '') }}/" + id;
        } else {
            alert('Por favor, insira um ID válido.');
        }
    }
</script>
</x-app-layout>