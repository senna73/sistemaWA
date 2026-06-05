<x-app-layout>
    <div class="container">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Cadastrando Estabelecimento</h5>
            </div>
            <div class="card-body">
                <form id="form-edit-establishment">

                    <x-input id="name" name="name" type="text" label="Nome" :value="$company?->name ?? null" placeholder="Nome do Estabelecimento" />

                    <x-input id="document" name="document" type="text" label="CNPJ" :value="$company?->document ?? null " placeholder="Documento do Estabelecimento" class="cnpj" />

                    <div>
                        <select id='city_select' name='city_select' class="form-control" >
                            <option value="">Selecione uma cidade</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->name }}"
                                    {{ old('city', optional($company)->city) === $city->name ? 'selected' : '' }}>
                                    {{ $city->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row mt-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label" for="coordinator_id">Coordenador Geral</label>
                            <select id="coordinator_id" name="coordinator_id" class="form-control select2-coordinator">
                                <option value="">Selecione um coordenador (Opcional)</option>
                                @foreach($coordinators as $coordinator)
                                    <option value="{{ $coordinator->id }}" 
                                        {{ old('coordinator_id', $company?->coordinator_id) == $coordinator->id ? 'selected' : '' }}>
                                        {{ $coordinator->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="coordinator_value">Valor Coordenação</label>
                            <input type="number" step="1" class="form-control" id="coordinator_value" name="coordinator_value" 
                                placeholder="0" value="{{ old('coordinator_value', $company?->coordinator_value ?? '') }}">
                        </div>
                    </div>
                    <x-input id="uniforms_laid" name="uniforms_laid" type="number" label="Qtd. Uniformes em Loja" :value="$company?->uniforms_laid ?? null" placeholder="Quantidade de uniformes em loja" />

                    <div class="mb-3">
                        <label class="form-label" for="basic-default-text">Setores</label><br>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select" id="sectionSelect">
                                <option value="empty">Selecione um setor</option>
                                @foreach ($sections as $setor)
                                    <option value="{{ $setor->id }}" data-nome="{{ $setor->name }}">{{ $setor->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addLabelForSection()">+</button>
                        </div>
                        <div class="w-100" id="accordionDiv" style="background-color:#CCE0FF">
                            <div id="selectedSections" class="w-100"></div>
                        </div>
                    </div>

                    <x-checkbox id="not_flashing" name="not_flashing" title="Nao Intermitente" :checked="$company->not_flashing ?? false" />

                    <x-textarea id="observation" name="observation" label="Observação" placeholder="Alguma observação?">{!! $company?->observation ?? null !!}</x-textarea>
                </form>
            </div>

            <div class="card-footer">
                @if ($company?->id ?? false)
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-primary right" onclick="update({{ $company?->id ?? null }})">Salvar</button>
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

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>

    let establishment = @json($company ?? null);
    let companySections = @json($company?->companySections ?? []);
    let sections = @json($sections ?? null);
    $(document).ready(function() {
        $('#city_select').select2({
            tags: true,
            placeholder: "Selecione ou adicione uma cidade",
            width: '100%',
            createTag: function (params) {
                var term = $.trim(params.term);
                if (term === '') {
                    return null;
                }
                return {
                    id: term,
                    text: term,
                    newTag: true // sinaliza que é novo
                };
            }
        });
    });

    $(document).ready(function () {
        let cnpjMask = new Inputmask('99.999.999/9999-99', {
            placeholder: ' ',
            clearIncomplete: true
        });
        cnpjMask.mask('.cnpj');

        $('#coordinator_id').select2({
            placeholder: "Selecione um coordenador",
            allowClear: true,
            width: '100%'
        });

    });
    function createSectionCard(collapseId, setorId, setorNome, earned, employee_pay, extra, leader_pay, comission, perHour, supervisor){
        return `
                <div class="accordion-item card-body mb-1 w-100">
                    <h2 class="accordion-header" id="heading${setorId}">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                            <strong>Setor:</strong> ${setorNome}
                        </button>
                    </h2>

                    <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="heading${setorId}" data-bs-parent="#accordionDiv">

                    <div class="accordion-body justify-content-end">
                            <input type="hidden" id="setores[${setorId}][id]" name="section_id[${setorId}]" value="${setorId}">
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="setores[${ setorId }][perHour]" name="perHour[${ setorId }]" ${perHour == 1 ? 'checked' : '' }>
                                <label class="form-check-label" for="setores[${ setorId }][perHour]">Contrato por Hora</label>
                            </div>
                            <label class="form-label mb-3">Recebido:
                                <input type="number" class="form-control number" id="setores[${setorId}][earned]" name="earned[${setorId}]" value="${earned ?? 1000}">
                            </label>
                            <label class="form-label mb-1">Diária:
                                <input type="number" class="form-control number" id="setores[${setorId}][diaria]" name="diaria[${setorId}]" value="${employee_pay ?? 100}">
                            </label>
                            <label class="form-label mb-1">Colaborador Extra:
                                <input type="number" class="form-control number" id="setores[${setorId}][extra]" name="extra[${setorId}]" value="${extra ?? 110}">
                            </label>
                            <label class="form-label mb-1">Líder:
                                <input type="number" class="form-control number" id="setores[${setorId}][lider]" name="lider[${setorId}]" value="${leader_pay ?? 122}">
                            </label>
                            <label class="form-label mb-3">Comissão:
                                <input type="number" class="form-control number" id="setores[${setorId}][comissao]" name="comissao[${setorId}]" value="${comission ?? 8}">
                            </label>
                            <label class="form-label mb-1">Supervisor:
                                <input type="number" class="form-control number" id="setores[${setorId}][supervisor]" name="supervisor[${setorId}]" value="${supervisor ?? 0}">
                            </label>
                        </div>
                        <div class="d-flex justify-content-center d-flex bd-highlight">
                            @if ($company?->id ?? false)
                                <button type="button" class="btn btn-danger mt-2 p-2 flex-fill bd-highlight" onclick="removeSection('${setorId}', '${setorNome}', {{ $company?->id }})">Remover</button>
                            @else
                                <button type="button" class="btn btn-danger mt-2 p-2 flex-fill bd-highlight" onclick="removeSection('${setorId}', '${setorNome}', {{ 0 }})">Remover</button>
                            @endif
                        </div>
                        </div>
                </div>
            `;
    }

    function loadExistingRegisteredSections(companySections, sections){
        let setorSelect = document.getElementById("sectionSelect");
        //let setorId = setorSelect.value;
        let setorNome = setorSelect.options[setorSelect.selectedIndex].dataset.nome;




        for (let setSection of companySections) {

            if (!setSection.active) {
                continue;
            };

            let div = document.createElement("div");
            div.style.backgroundColor = "#E6F7FF";
            div.className = "mb-1 pt-1 form-label rounded-3 d-flex flex-column align-items-center";
            div.id = `setor-${setSection?.id}`;

            let collapseId = "collapse" + setSection?.id;
            let sectioName = sections.find(section => section.id === setSection.section_id);
            let sectionContent = createSectionCard(collapseId, setSection.section_id, sectioName.name, setSection.earned, setSection.employeePay, setSection.extra, setSection.leaderPay, setSection.leaderComission, setSection.perHour, setSection.supervisorPay);

            console.log(setSection.section_id);
            //setorSelect.options[setSection.section_id].remove();
            for (let i = 0; i < setorSelect.options.length; i++) {
                if (setorSelect.options[i].value == setSection.section_id) {
                    setorSelect.remove(i);
                    break;
                }
            }
            if (sectionContent) {
                div.innerHTML = sectionContent;
                document.getElementById("selectedSections").appendChild(div);
            } else {
                console.warn("loadExistingRegisteredSections returned empty content");
            }
        }

    }

    function addLabelForSection() {
        let setorSelect = document.getElementById("sectionSelect");
        let setorId = setorSelect.value;
        let setorNome = setorSelect.options[setorSelect.selectedIndex].dataset.nome;

        if (setorId === "empty") {
            Swal.fire({
                    title: 'Error!',
                    text: 'Por favor, selecione um setor antes de adicionar.!',
                    icon: 'Error'
                });
            return;
        }

        let div = document.createElement("div");
        div.style.backgroundColor = "#E6F7FF";
        div.className = "mb-1 pt-1 form-label rounded-3 d-flex flex-column align-items-center";
        //div.style.border = "2px solid #CCE0FF";
        div.id = `setor-${setorId}`;

        let collapseId = 'collapse' + setorId;

        div.innerHTML = createSectionCard(collapseId, setorId, setorNome);

        document.getElementById("selectedSections").appendChild(div);

        setorSelect.options[setorSelect.selectedIndex].remove();
    }

    // function saveSection(sectionId, sectionName, establishmentId) {
    //     // Extração dos valores dos campos
    //     let employeePay = document.getElementById(`setores[${sectionId}][diaria]`).value;
    //     let leaderPay = document.getElementById(`setores[${sectionId}][lider]`).value;
    //     let comission = document.getElementById(`setores[${sectionId}][comissao]`).value;
    //     let earned = document.getElementById(`setores[${sectionId}][earned]`).value;

    //     console.log(sectionId);
    //     // Enviar dados via AJAX
    //     $.ajax({
    //         url: '{ route('companyHasSection.storeObject') }}',  // URL da rota
    //         type: 'POST',
    //         headers: {
    //             'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') // Garantir CSRF token
    //         },
    //         data: {
    //             establishment_id: establishmentId,
    //             section_id: sectionId,
    //             employee_pay: employeePay,
    //             leader_pay: leaderPay,
    //             leaderComission: comission,
    //             earned: earned
    //         },
    //         success: function(response) {
    //             Swal.fire({
    //                 title: response?.title ?? 'Sucesso!',
    //                 text: response?.message ?? 'Sucesso na ação!',
    //                 icon: response?.type ?? 'success'
    //             }).then((result) => {
    //                 //window.location.reload();
    //             });
    //         },
    //         error: function(response) {
    //             response = JSON.parse(response.responseText); // Captura o erro
    //             Swal.fire({
    //                 title: response?.title ?? 'Oops!',
    //                 html: response?.message?.replace(/\n/g, '<br>') ?? 'Erro na ação!',
    //                 icon: response?.type ?? 'error'
    //             });
    //         }
    //     });
    // }

    function removeSection(sectionId, sectionName, establishmentID) {


        let setorSelect = document.getElementById("sectionSelect");
        let option = document.createElement("option");
        option.value = sectionId;
        option.textContent = sectionName;
        option.dataset.nome = sectionName;

        setorSelect.appendChild(option);

        if (establishmentID) {
            $.ajax({
                url: `{{ route('companyHasSection.remove') }}`,
                type: "DELETE",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                },
                data: {
                    establishment_id: establishmentID,
                    section_id: sectionId
                },
                success: function(response) {
                    Swal.fire({
                    title: response?.title ?? 'Sucesso!',
                    text: response?.message ?? 'Sucesso na ação!',
                    icon: response?.type ?? 'success'
                }).then((result) => {
                    let sectionDiv = document.getElementById(`setor-${sectionId}`);
                    if (sectionDiv) {
                        sectionDiv.remove();
                    }
                    window.location.reload();
                })
                },
                error: function(response) {
                    Swal.fire({
                        title: "Erro!",
                        text: "Não foi possível remover o setor.",
                        icon: "error"
                    });
                }
            });
        }
    }


    function post() {

        console.log($('#form-edit-establishment').serialize());
        $.ajax({
            url: '{{ route('companies.store') }}',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: $('#form-edit-establishment').serialize(),
            success: function(response) {
                Swal.fire({
                    title: response?.title ?? 'Sucesso!',
                    text: response?.message ?? 'Sucesso na ação!',
                    icon: response?.type ?? 'success'
                }).then((result) => {
                    $('#form-edit-establishment')[0].reset();

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
        console.log($('#form-edit-establishment').serialize());

        $.ajax({
            url: "{{ route('companies.update', '') }}" + '/' + id,
            type: 'PUT',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: $('#form-edit-establishment').serialize(),
            success: function(response) {
                Swal.fire({
                    title: response?.title ?? 'Sucesso!',
                    text: response?.message ?? 'Sucesso na ação!',
                    icon: response?.type ?? 'success'
                }).then((result) => {

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
        let cnpjMask = new Inputmask('99.999.999/9999-99', {
            placeholder: ' ',
            clearIncomplete: true
        });
        cnpjMask.mask('.cnpj');

        let moneyMask = new Inputmask("R$ 999,99", {
            numericInput: true,
            rightAlign: false,
            prefix: "R$ ",
            groupSeparator: ".",
            radixPoint: ",",
            autoGroup: true,
            unmaskAsNumber: true,
            allowMinus: true
        });
        moneyMask.mask('.money');
        $('#coordinator_id').select2({
            placeholder: "Selecione um coordenador",
            allowClear: true,
            width: '100%'
        });
    });

    window.onload = function() {
        if (companySections.length > 0) {
            loadExistingRegisteredSections(companySections,sections);
        }
    };

</script>
