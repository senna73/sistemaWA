<x-app-layout>
    <div class="container">
        <div class="card">
            
            <div class="card-header text-center">
                <h4 class="mb-0">Configuração de Valores Extra</h4>
            </div>
                        
            <div class="card-body">
                <form id="form-valor-extra">
                    <div class="mb-3">
                        <label class="form-label" for="companies_id">Estabelecimento</label>
                        
                        <select class="form-control" id="company_id" name="company_id">
                            
                            <option value="">Selecione o Estabelecimento</option>
                            
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                            
                        </select>
                        </div>

                    
                </form>
            </div>
            
        </div>
        <div class='card mt-2' id="items_card">
            <div id="existing_agreements_container" class="p-3">
            
            </div>
        </div>
            
    </div>
</x-app-layout>




<script>
let collaboratorSelect2; 
const $existingAgreementsContainer = $('#existing_agreements_container');

const colaborador_select_html = `
    <div id="new_agreement_form_container" class="mb-3 p-3 border rounded">
        <h4>Nova Regra de Valor Extra</h4>
        <div class="align-items-center">
            <div class="d-flex align-items-center">
                
                <div class="col-9 me-2"> 
                    <select id="new_collaborator_select" class="form-control form-control-sm" name="new_collaborator_id">
                        <option value="" disabled selected>Selecione o Estabelecimento primeiro</option> 
                    </select>
                </div>

                <div class="col-3"> 
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">R$</span>
                        <input 
                            type="text" 
                            id="new_agreement_value" 
                            class="form-control agreement-value-input" 
                            name="new_value" 
                            value="" 
                            placeholder="0.00"
                        >
                    </div>
                </div>
                
            </div>
            <div class="mt-1 w-100">
                <button 
                    type="button" 
                    class="btn btn-primary btn-sm w-100" 
                    id="btn_save_new_agreement"
                    onclick="post_new_agreement()"
                >
                    Salvar Regra
                </button>
            </div>
        </div>
    </div>`;

const GLOBAL_CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

$(document).ready(function() {
    const $itemsCard = $('#items_card');
    $itemsCard.prepend(colaborador_select_html); 
    $itemsCard.append('<div id="existing_agreements_container" class="p-3"></div>');
    
    collaboratorSelect2 = $('#new_collaborator_select').select2({
        multiple: false, 
        width: '100%',
        placeholder: "Colaborador...",
        allowClear: true
    });
    
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': GLOBAL_CSRF_TOKEN
        }
    });

    $('#company_id').on('input change', function(){
        find_new_set_of_rules();
    });
    
    if ($('#company_id').val()) {
        find_new_set_of_rules();
    }
});


function find_new_set_of_rules() {
    const companyId = $('#company_id').val();
    const $existingAgreementsContainer = $('#existing_agreements_container');
    
    if (!companyId) {
        $existingAgreementsContainer.html('<p class="text-info">Selecione um estabelecimento para carregar as regras.</p>');
        collaboratorSelect2.empty().append('<option value="" disabled selected>Selecione um colaborador</option>').trigger('change');
        $('#new_agreement_value').prop('disabled', true);
        $('#btn_save_new_agreement').prop('disabled', true);
        return;
    }
    
    $existingAgreementsContainer.html('<p class="text-info">Carregando regras e colaboradores...</p>');
    $('#new_agreement_value').prop('disabled', false);
    $('#btn_save_new_agreement').prop('disabled', false);

    $.ajax({
        url: "/rules/acordo-valor-extra/list/" + companyId,
        type: "GET",
        dataType: "json",
        success: function(response) {
            
            const acordos = response.data; 
            const colaboradores = response.collaborators; 
            
            let new_collaboratorOptions = '<option value="" disabled selected>Selecione um colaborador</option>';
            if (Array.isArray(colaboradores) && colaboradores.length > 0) {
                colaboradores.forEach(function(colaborador) {
                    new_collaboratorOptions += `<option value="${colaborador.id}">${colaborador.name}</option>`;
                });
            } else {
                 new_collaboratorOptions = '<option value="" disabled selected>Nenhum colaborador encontrado</option>';
            }
            
            $('#new_collaborator_select').html(new_collaboratorOptions).val('').trigger('change');
            
            $existingAgreementsContainer.empty();
            
            if (response.success && Array.isArray(acordos) && acordos.length > 0) {
                acordos.forEach(function(acordo) {
                    const itemHtml = `
                        <div class="card p-3 mb-2 border-primary">
                            <form class="form-horizontal agreement-form" data-agreement-id="${acordo.id}">
                                <input type="hidden" name="agreement_id" value="${acordo.id}">
                                <input type="hidden" name="collaborator_id" value="${acordo.colaborator_id}">
                                <input type="hidden" name="company_id" value="${acordo.company_id}">
                                
                                <div class="row align-items-center">
                                    
                                    <div class="col-6 col-md-5">
                                        <label class="form-label mb-0 outline btn-outline-primary">
                                            <strong>${acordo.collaborator_name}</strong>
                                        </label>
                                        em
                                        <label class="form-label mb-0 outline btn-outline-primary">
                                            <strong> ${acordo.company_name}</strong>
                                        </label>
                                    </div>
                                    
                                    <div class="col-6 col-md-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">R$</span>
                                            <input 
                                                disabled
                                                type="text" 
                                                class="form-control agreement-value-input" 
                                                name="value" 
                                                value="${acordo.value}"
                                                placeholder="0.00"
                                            >
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 col-md-4 d-flex justify-content-end">
                                        <button 
                                            type="button" 
                                            class="btn btn-danger btn-sm" 
                                            onclick="delete_agreement(${acordo.id})"
                                        >
                                            Excluir Regra
                                        </button>
                                    </div>

                                </div>
                            </form>
                        </div>
                    `;
                    $existingAgreementsContainer.append(itemHtml);
                });
            } else {
                $existingAgreementsContainer.html('<p class="text-info">Nenhum acordo encontrado para esta empresa.</p>');
            }
        },
        error: function (xhr) {
            $existingAgreementsContainer.html('<p class="text-danger">Erro ao carregar dados. Verifique a console para detalhes.</p>');
            console.error("Erro ao buscar regras:", xhr.responseText);
        }
    });
}

function post_new_agreement()
{
const collaboratorId = $('#new_collaborator_select').val();
    const value          = $('#new_agreement_value').val();
    const companyId      = $('#company_id').val();
    
    if (!companyId) {
        alert("Por favor, selecione um Estabelecimento.");
        return;
    }

    if (!collaboratorId) {
        alert("Por favor, selecione um Colaborador.");
        return;
    }
    
    const numericValue = parseFloat(value.replace(',', '.'));

    if (!value || isNaN(numericValue) || numericValue <= 0) {
        alert("Por favor, insira um Valor Extra válido e positivo.");
        return;
    }
    
    const $saveButton = $('#btn_save_new_agreement');
    $saveButton.prop('disabled', true);
    $.ajax({
        type: "POST", 
        url: "/rules/acordo-valor-extra/create",
        data: {
            _token: GLOBAL_CSRF_TOKEN,
            collaborator_id: collaboratorId,
            company_id: companyId,
            value: value
        },
        success: function (response) {
            if(response.success){
                alert("Regra salva!");
                $('#new_agreement_value').val('');
                find_new_set_of_rules();
            } else {
                 alert("Falha ao salvar: " + response.message);
            }
        },
        error: function(xhr) {
             alert("Erro ao salvar regra.");
             console.error("Erro no POST:", xhr.responseText);
        }
    });
}

function delete_agreement(agreementId) {
    if (!agreementId) {
        alert("ID do acordo inválido para exclusão.");
        return;
    }

    if (!confirm("Tem certeza que deseja EXCLUIR este acordo de valor extra?")) {
        return;
    }
    
    $.ajax({
        type: "DELETE", 
        url: "/rules/acordo-valor-extra/delete/" + agreementId,
        dataType: "json",
        
        success: function (response) {
            if (response.success) {
                alert("Regra excluída com sucesso!");
                find_new_set_of_rules();
            } else {
                 alert("Falha ao excluir a regra: " + (response.message || "Erro desconhecido."));
            }
        },
        error: function(xhr) {
             let errorMessage = "Erro na comunicação com o servidor durante a exclusão.";
             try {
                 const responseJson = JSON.parse(xhr.responseText);
                 if (responseJson.message) {
                     errorMessage = "Erro: " + responseJson.message;
                 }
             } catch (e) {
             }
             alert(errorMessage);
             console.error("Erro no DELETE:", xhr.responseText);
        }
    });
}

</script>
