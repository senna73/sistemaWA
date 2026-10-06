<x-app-layout>
    <div class="container">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Abrir demanda para o RH</h5></div>
            <div class="card-body">
                <p class="text-muted">O pedido vira card na fila do RH. Informe o colaborador e, quando der, o valor que deve ir para o cadastro.</p>
                <form method="POST" action="{{ route('demands.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Colaborador</label>
                        <select name="collaborator_id" id="demand-collaborator" class="form-select">
                            <option value="">Selecione</option>
                            @foreach ($collaborators as $person)
                                <option value="{{ $person->id }}"
                                    data-mobile="{{ $person->mobile }}"
                                    data-group="{{ $person->group }}"
                                    data-pix="{{ $person->pix_key }}"
                                    data-document="{{ $person->document }}"
                                    @selected((string) old('collaborator_id') === (string) $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome de quem pede (se não houver colaborador)</label>
                        <input name="name" class="form-control" value="{{ old('name') }}">
                    </div>
                        <select name="category" id="demand-category" class="form-select" required>
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 apply-field" data-for="troca_pix">
                        <label class="form-label">Nova chave Pix</label>
                        <input name="payload[pix_key]" class="form-control" value="{{ old('payload.pix_key') }}">
                    </div>
                    <div class="mb-3 apply-field" data-for="transferencia_grupo">
                        <label class="form-label">Grupo WhatsApp de destino</label>
                        <input name="payload[group]" class="form-control" list="whatsapp-groups" value="{{ old('payload.group') }}" placeholder="Nome do grupo">
                    </div>
                    <div class="apply-field" data-for="atualizacao_cadastro">
                        <div class="mb-3">
                            <label class="form-label">Nome no cadastro</label>
                            <input name="payload[name]" class="form-control" value="{{ old('payload.name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Celular</label>
                            <input name="payload[mobile]" class="form-control" value="{{ old('payload.mobile') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Documento</label>
                            <input name="payload[document]" class="form-control" value="{{ old('payload.document') }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pedido</label>
                        <textarea name="request_text" class="form-control" required>{{ old('request_text') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Anexos (até 3)</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                    </div>
                    <datalist id="whatsapp-groups">
                        @foreach ($groups as $group)
                            <option value="{{ $group }}"></option>
                        @endforeach
                    </datalist>
                    <button class="btn btn-primary" type="submit">Abrir para o RH</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        const category = document.getElementById('demand-category');
        function toggleFields() {
            document.querySelectorAll('.apply-field').forEach(function (block) {
                block.style.display = block.getAttribute('data-for') === category.value ? '' : 'none';
            });
        }
        category.addEventListener('change', toggleFields);
        toggleFields();
    </script>
</x-app-layout>
