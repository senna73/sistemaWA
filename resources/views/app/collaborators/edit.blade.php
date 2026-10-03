<x-app-layout>
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ isset($collaborator?->id) ? 'Editando Colaborador' : 'Cadastrando Colaboradores' }}
                </h5>
            </div>
            <div class="card-body">
                <form id="form-edit-collaborator">
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-fullname">Nome</label>
                        <input type="text" class="form-control" id="basic-default-fullname" name="name"
                            placeholder="João Doe" value="{{ old('name', $collaborator?->name ?? request('name', '')) }}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mobile">Celular</label>
                        <input type="text" class="form-control mobile" id="mobile" name="mobile"
                            placeholder="(00) 00000-0000" value="{{ $collaborator?->mobile ?? '' }}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="leave_end_date">Afastado até: </label>
                        <input type="text" class="form-control" id="leave_end_date" name="leave_end_date"
                            placeholder="DD/MM/AAAA" maxlength="10"
                            value="{{ isset($collaborator?->leave_end_date) ? \Carbon\Carbon::parse($collaborator->leave_end_date)->format('d/m/Y') : '' }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="hired_at">Data de admissão</label>
                        <input type="text" class="form-control" id="hired_at" name="hired_at"
                            placeholder="DD/MM/AAAA" maxlength="10"
                            value="{{ old('hired_at', isset($collaborator?->hired_at) ? \Carbon\Carbon::parse($collaborator->hired_at)->format('d/m/Y') : request('hired_at', '')) }}">
                        @if (! ($collaborator?->hired_at ?? request('hired_at')))
                            <div class="form-text text-warning">Sem admissão conferida. Use a data da lista da contabilidade, não a data de cadastro no sistema.</div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="accounting_code">Código da contabilidade</label>
                        <input type="text" class="form-control" id="accounting_code" name="accounting_code"
                            placeholder="000001"
                            value="{{ old('accounting_code', $collaborator?->accounting_code ?? request('accounting_code', '')) }}">
                    </div>
                    @if (request('accounting_row_id'))
                        <input type="hidden" name="accounting_row_id" value="{{ request('accounting_row_id') }}">
                    @endif

                    <div class="mb-3">
                        <label class="form-label" for="group">Grupo</label>
                        <select class="form-control select2-tags" id="group" name="group">
                            <option value="">Selecione ou digite um grupo</option>
                            @foreach ($groups as $groupOption)
                                <option value="{{ $groupOption }}"
                                    {{ old('group', $collaborator?->group ?? '') == $groupOption ? 'selected' : '' }}>
                                    {{ $groupOption }}
                                </option>
                            @endforeach

                            @if (!empty($collaborator?->group) && !in_array($collaborator->group, $groups))
                                <option value="{{ $collaborator->group }}" selected>{{ $collaborator->group }}</option>
                            @endif
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="document">Documento</label>
                        <input type="text" class="form-control cpf" id="document" name="document"
                            placeholder="000.000.000-00" value="{{ $collaborator?->document ?? '' }}" />
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="uniform_size">Tamanho do Uniforme</label>
                            <select class="form-select form-control" id="uniform_size" name="uniform_size">
                                <option value="">Selecione o tamanho</option>
                                @foreach ($sizes as $size)
                                    <option value="{{ $size }}"
                                        {{ ($collaborator?->uniform_size ?? '') == $size ? 'selected' : '' }}>
                                        {{ $size }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="d-flex">
                        <div class="form-check mb-3 me-2">
                            <input class="form-check-input me-2" type="checkbox" id="intermittent_contract"
                                name="intermittent_contract"
                                {{ isset($collaborator) && $collaborator?->intermittent_contract == 1 ? 'checked' : '' }}>
                            <label class="form-check-label" for="intermittent_contract">Contrato Intermitente</label>
                        </div>

                        <div class="form-check mb-3 me-2">
                            <input class="form-check-input" type="checkbox" id="is_leader" name="is_leader"
                                {{ isset($collaborator) && $collaborator?->is_leader == 1 ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_leader">Líder</label>
                        </div>

                        <div class="form-check mb-3 me-2">
                            <input class="form-check-input" type="checkbox" id="is_supervisor" name="is_supervisor"
                                {{ isset($collaborator) && $collaborator?->is_supervisor == 1 ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_supervisor">Supervisor</label>
                        </div>

                        <div class="form-check mb-3 me-2">
                            <input class="form-check-input" type="checkbox" id="is_extra" name="is_extra"
                                {{ isset($collaborator) && $collaborator?->is_extra == 1 ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_extra">Recebe Valor Extra</label>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label" for="medical_clinic_id">Clínica Médica</label>
                        <select name="medical_clinic_id" id="medical_clinic_id" class="form-control">
                            <option value="">Selecione uma clínica (Opcional)</option>
                            @foreach ($available_clinics as $clinic)
                                <option value="{{ $clinic->id }}"
                                    {{ isset($collaborator) && $collaborator->examined_medical_clinic_id == $clinic->id ? 'selected' : '' }}>
                                    {{ $clinic->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pix_key">Chave Pix</label>
                        <input type="text" class="form-control" id="pix_key" name="pix_key"
                            value="{{ $collaborator?->pix_key ?? '' }}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="city">Cidade</label>
                        <input type="text" class="form-control" id="city" name="city"
                            placeholder="A cidade em que o colaborador se encontra"
                            value="{{ $collaborator?->city ?? '' }}" />
                    </div>

                    <div>
                        <label class="form-label" for="cities_can_work">Cidades em que trabalha</label>
                        <select multiple name="cities_can_work[]" id="cities_can_work" class="form-control">
                            @foreach ($cities as $city)
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
                        <button type="button" class="btn btn-primary right"
                            onclick="update({{ $collaborator?->id ?? null }})">Salvar</button>
                    </div>
                @else
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-primary right" onclick="post()">Salvar</button>
                    </div>
                @endif
            </div>
        </div>

        @can('Solicitar desligamento')
            @if ($collaborator?->id)
                <div class="card mb-4" id="offboarding">
                    <div class="card-header"><h5 class="mb-0">Pedir demissão</h5></div>
                    <div class="card-body">
                        <p class="text-muted mb-3">O botão abre um card no quadro do RH, sempre vinculado a {{ $collaborator->name }}.</p>
                        <form method="POST" action="{{ route('collaborators.offboarding', $collaborator) }}">
                            @csrf
                            <input type="hidden" name="kind" value="dismissal">
                            <div class="mb-3">
                                <label class="form-label" for="offboarding-notes">Motivo</label>
                                <textarea id="offboarding-notes" name="notes" class="form-control" rows="3" required>{{ old('notes') }}</textarea>
                            </div>
                            <button class="btn btn-danger" type="submit">Pedir demissão</button>
                        </form>
                    </div>
                </div>
            @endif
        @endcan
    </div>
</x-app-layout>

<script>
    $(document).ready(function() {
        $('#cities_can_work').select2({
            placeholder: "Selecione a(s) cidade(s) em que Trabalha",
            allowClear: true
        });

        $('.select2-tags').select2({
            tags: true,
            placeholder: "Selecione ou digite um grupo",
            allowClear: true,
            createTag: function(params) {
                var term = $.trim(params.term);
                if (term === '') return null;
                return {
                    id: term,
                    text: term,
                    newTag: true
                };
            }
        });

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

        let dateMask = new Inputmask('99/99/9999', {
            placeholder: ' ',
            clearIncomplete: true
        });
        dateMask.mask('#leave_end_date');
        dateMask.mask('#hired_at');
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
                }).then(() => {
                    $('#form-edit-collaborator')[0].reset();
                    window.location.reload();
                });
            },
            error: function(response) {
                let res = JSON.parse(response.responseText);
                Swal.fire({
                    title: res?.title ?? 'Oops!',
                    html: res?.message?.replace(/\n/g, '<br>') ?? 'Erro na ação!',
                    icon: res?.type ?? 'error'
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
                }).then(() => {
                    $('#form-edit-collaborator')[0].reset();
                    window.location.reload();
                });
            },
            error: function(response) {
                let res = JSON.parse(response.responseText);
                Swal.fire({
                    title: res?.title ?? 'Oops!',
                    html: res?.message?.replace(/\n/g, '<br>') ?? 'Erro na ação!',
                    icon: res?.type ?? 'error'
                });
            }
        });
    }
</script>
