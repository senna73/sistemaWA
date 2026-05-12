<x-app-layout>
    <div class="container">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Cadastrando Colaboradores</h5>
            </div>
            <div class="card-body">
                <form id="form-edit-collaborator">
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-fullname">Nome</label>
                        <input type="text" class="form-control" id="basic-default-fullname" name="name" placeholder="João Doe" value="{{ $collaborator?->name ?? ''}}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mobile">Celular</label>
                        <input type="text" class="form-control mobile" id="mobile" name="mobile" placeholder="(00) 00000-0000" value="{{ $collaborator?->mobile ?? ''}}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-fullname">Documento</label>
                        <input type="text" class="form-control cpf" id="basic-default-fullname" name="document" placeholder="000.000.000-00" value="{{ $collaborator?->document ?? ''}}" />
                    </div>
                    <div class="d-flex">
                          <div class="form-check mb-3 me-2">
                           <input class="form-check-input me-2" type="checkbox" id="intermittent_contract" name="intermittent_contract" 
                                {{ isset($collaborator) && $collaborator?->intermittent_contract == 1 ? 'checked' : '' }}>
                        <label class="form-check-label" for="intermittent_contract">Contrato Intermitente</label>
                        </div>

                        <div class="form-check mb-3  me-2">
                            <input class="form-check-input" type="checkbox" id="is_leader" name="is_leader" 
                                {{ isset($collaborator) && $collaborator?->is_leader == 1 ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_leader">Líder</label>
                        </div>

                        <div class="form-check mb-3  me-2">
                            <input class="form-check-input" type="checkbox" id="is_supervisor" name="is_supervisor" 
                                {{ isset($collaborator) && $collaborator?->is_supervisor == 1 ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_supervisor">Supervisor</label>
                        </div>

                        <div class="form-check mb-3  me-2">
                            <input class="form-check-input" type="checkbox" id="is_extra" name="is_extra" 
                                {{ isset($collaborator) && $collaborator?->is_extra == 1 ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_extra">Recebe Valor Extra</label>
                        </div>


                        
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-fullname">Chave Pix</label>
                        <input type="text" class="form-control" id="pix_key" name="pix_key" value="{{ $collaborator?->pix_key ?? ''}}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-fullname">Cidade</label>
                        <input type="text" class="form-control" id="city" name="city" placeholder="A cidade em que o colaborador se encontra" value="{{ $collaborator?->city ?? ''}}" />
                    </div>
                    <div>
                        <label class="form-label" for="cities_can_work">Cidades</label>
                        <select multiple name="cities_can_work[]" id="cities_can_work" class="form-control">
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}"
                                    {{ in_array($city->id, $selectedCities ?? []) ? 'selected' : '' }}>
                                    {{ $city->name }}
                                </option>
                            @endforeach
                        </select>

                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-message">Observação</label>
                        <textarea id="basic-default-message" class="form-control" placeholder="Alguma observação?" name="observation">{!! $collaborator?->observation ?? '' !!}</textarea>
                    </div>
                </form>

            </div>
            <div class="card-footer">
                @if ($collaborator?->id ?? false)
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-primary right" onclick="update({{ $collaborator?->id ?? null }})">Salvar</button>
                    </div>
                @else
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-primary right" onclick="post()">Salvar</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    $(document).ready(function() {
    $('#cities_can_work').select2({
            placeholder: "Selecione a(s) cidade(s) em que Trabalha",
            allowClear: true
        });
    });

    function post() {

        $.ajax({
            url: '{{ route('collaborators.store') }}',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: $('#form-edit-collaborator').serialize(),
            success: function(response) {
                Swal.fire({
                    title: response?.title ?? 'Sucesso!',
                    text: response?.message ?? 'Sucesso na ação!',
                    icon: response?.type ?? 'success'
                }).then((result) => {
                    $('#form-edit-collaborator')[0].reset();

                    window.location.reload();
                });
            },
            error: function(response) {
                response = JSON.parse(response.responseText);
                Swal.fire({
                    title: response?.title ?? 'Oops!',
                    html: response?.message?.replace(/\n/g, '<br>') ?? 'Erro na ação!',
                    icon: response?.type ?? 'error'
                });
            }
        });
    }

    function update(id) {
        $.ajax({
            url: "{{ route('collaborators.update', '') }}" + '/' + id,
            type: 'PUT',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: $('#form-edit-collaborator').serialize(),
            success: function(response) {
                Swal.fire({
                    title: response?.title ?? 'Sucesso!',
                    text: response?.message ?? 'Sucesso na ação!',
                    icon: response?.type ?? 'success'
                }).then((result) => {
                    $('#form-edit-collaborator')[0].reset();

                    window.location.reload();
                });
            },
            error: function(response) {
                response = JSON.parse(response.responseText);
                Swal.fire({
                    title: response?.title ?? 'Oops!',
                    html: response?.message?.replace(/\n/g, '<br>') ?? 'Erro na ação!',
                    icon: response?.type ?? 'error'
                });
            }
        });
    }

    $(document).ready(function () {
        let cpfMask = new Inputmask('999.999.999-99', { 
            placeholder: ' ', 
            clearIncomplete: true 
        });
        cpfMask.mask('.cpf');
    });

    $(document).ready(function () {
    let cpfMask = new Inputmask('999.999.999-99', { 
        placeholder: ' ', 
        clearIncomplete: true 
    });
    cpfMask.mask('.cpf');

    let mobileMask = new Inputmask('(99) 99999-9999', { 
        placeholder: ' ', 
        clearIncomplete: true 
    });
    mobileMask.mask('.mobile');
    });
</script>