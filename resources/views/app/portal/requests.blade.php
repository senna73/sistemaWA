<x-app-layout>
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @include('app.portal.partials.lookup')

        @if ($collaborator)
        @if ($canRequest ?? false)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Nova solicitação</h5></div>
            <div class="card-body">
                <p class="text-muted">O pedido vira atividade para o RH, vinculada ao seu cadastro. Troca de Pix só entra no cadastro depois da conferência.</p>
                <form method="POST" action="{{ route('portal.requests.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <select name="category" id="request-category" class="form-select" required>
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3" id="pix-field">
                        <label class="form-label">Nova chave Pix</label>
                        <input name="pix_key" class="form-control" value="{{ old('pix_key', $collaborator->pix_key) }}">
                    </div>
                    <div class="mb-3" id="group-field">
                        <label class="form-label">Grupo WhatsApp de destino</label>
                        <input name="payload[group]" class="form-control" list="portal-whatsapp-groups" value="{{ old('payload.group') }}">
                        <datalist id="portal-whatsapp-groups">
                            @foreach ($groups ?? [] as $group)
                                <option value="{{ $group }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div id="cadastro-fields">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input name="payload[name]" class="form-control" value="{{ old('payload.name', $collaborator->name) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Celular</label>
                            <input name="payload[mobile]" class="form-control" value="{{ old('payload.mobile', $collaborator->mobile) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Documento</label>
                            <input name="payload[document]" class="form-control" value="{{ old('payload.document', $collaborator->document) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Detalhe</label>
                        <textarea name="request_text" class="form-control" required rows="3">{{ old('request_text') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Anexos (até 3)</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                    </div>
                    <button class="btn btn-primary" type="submit">Enviar para o RH</button>
                </form>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Histórico</h5></div>
            <div class="card-body">
                @forelse ($demands ?? [] as $demand)
                    <div class="border rounded p-2 mb-2">
                        <strong>{{ $demand->categoryLabel() }}</strong>
                        <span class="text-muted">· {{ $demand->statusLabel() }}</span>
                        @if ($demand->agendaItem)
                            <span class="badge bg-label-primary">Atividade #{{ $demand->agendaItem->id }}</span>
                        @endif
                        <div class="small">{{ $demand->request_text }}</div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Nenhuma solicitação ainda.</p>
                @endforelse
                @if ($demands)
                    {{ $demands->links() }}
                @endif
            </div>
        </div>
        @endif
    </div>
    @if ($canRequest ?? false)
    <script>
        const category = document.getElementById('request-category');
        const pixField = document.getElementById('pix-field');
        const groupField = document.getElementById('group-field');
        const cadastroFields = document.getElementById('cadastro-fields');
        function togglePix() {
            pixField.style.display = category.value === 'troca_pix' ? '' : 'none';
            groupField.style.display = category.value === 'transferencia_grupo' ? '' : 'none';
            cadastroFields.style.display = category.value === 'atualizacao_cadastro' ? '' : 'none';
        }
        category.addEventListener('change', togglePix);
        togglePix();
    </script>
    @endif
</x-app-layout>
