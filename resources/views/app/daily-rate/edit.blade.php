<x-app-layout>

    <div class="container">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Cadastrando Diária</h5>
            </div> 
            <div class="card-body">
                <form id="form-hourly-rate">

                    <div class="mb-3">
                        <label class="form-label" for="collaborator_id">Colaborador</label>
                        <select class="form-control" id="collaborator_id" name="collaborator_id">
                            <option value="" disabled selected>Selecione um colaborador</option>
                            @foreach ($collaborators as $colaborator)
                                <option value="{{ $colaborator->id }}" {{ ($dailyRate?->collaborator_id ?? 0) == $colaborator->id ? 'selected' : '' }}>
                                    {{ $colaborator->name }}
                                </option>                            
                            @endforeach
                        </select>
                    </div>
                
                    <div class="mb-3">
                        <label class="form-label" for="company_id">Empresa</label>
                        <select class="form-control" id="company_id" name="company_id">
                            <option value="" disabled selected>Selecione uma empresa</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" {{ ($dailyRate?->collaborator_id ?? 0) == $company->id ? 'selected' : '' }}>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sectionSelect_id">Setor Trabalhado</label>
                        <select class="form-control" id="sectionSelect_id" name="sectionSelect_id" disabled>

                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="start">Chegada</label>
                        <input type="datetime-local" class="form-control" id="start" name="start" value="{{ $dailyRate?->start ?? '' }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="end">Saída</label>
                        <input type="datetime-local" class="form-control" id="end" disabled name="end" value="{{ $dailyRate?->end ?? '' }}">
                    </div>
                    <div class="mb-3">
                            <label class="form-label" for="feeding_id">Alimentação</label>
                            <input type="checkbox" class="" id="feeding_id" name="feeding_id" {{ isset($dailyRate) && $dailyRate?->feeding != 0 ? 'checked' : ''}}> R$10,00
                        </div>

                    <div class="mb-3">
                        <label class="form-label" for="total_time">Horas Trabalhadas</label>
                        <input type="text" class="form-control" id="total_time" name="total_time" data-mask="00:00" readonly value="{{ $dailyRate?->total_time ?? '' }}">
                    </div>
                    
                    <input type="text" class="form-control" id="imposto_paid_id" name="imposto_paid_id" hidden readonly value="0">
<!-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- -->
<!-- Abaixo está a parte da tela reservada para usuários com permissão de acesso e registro de dados financeiros -->
                    <div {{ Auth::user()->hasPermissionTo('Visualizar e inserir informações financeiras nas diárias') ? '' : 'hidden' }}>
                        
                        
                        <div class="d-flex">
                            <div class="mb-3 me-3">
                                <label class="form-label" for="employee_pay_id">Colaborador</label>
                                <input type="text" class="form-control money" id="employee_pay_id" readonly name="employee_pay_id" value="">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="leaderComission_id">Comissão</label>
                                <input type="text" class="form-control money" id="leaderComission_id" readonly name="leaderComission_id" value="">
                            </div>
                        </div>
                        <div class="d-flex">
                            <input type="text" name="user_id" hidden value="{{auth()->user()->id}}" />
                            <div class="mb-3 me-3 flex-grow-1">
                                <label class="form-label" for="inss_id">INSS Pago</label>
                                <input type="text" class="form-control" id="inss_id" name="inss_id" value="{{ $inss_pago ?? '' }}">
                            </div>

                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="transport_id">Transporte</label>
                            <input type="text" class="form-control money" id="transport_id" name="transport_id" value="{{ $dailyRate?->transportation ?? '' }}">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="addition">Acréscimos</label>
                            <input type="text" class="form-control money" id="addition" name="addition" value="{{ $dailyRate?->addition ?? '' }}">
                        </div>

                        <div class="d-flex w-100">
                            <div class="flex-grow-1 me-2">
                                <x-fast-input name="employee_discount" label="Desconto de Funcionário" class="money" value="{{ $dailyRate?->employee_discount ?? '' }}" placeholder="R$" />
                            </div>

                            <div class="flex-grow-1">
                                <label class="form-label" for="discount_description">Descrição do Desconto</label>
                                <textarea class="form-control" id="discount_description" name="discount_description" rows="1">{!! $dailyRate?->discount_description ?? '' !!}</textarea>
                            </div>
                        </div>

                        <div class="d-flex ml-0">
                            <div class="mb-3 me-3">
                                <label class="form-label" for="total">Valor Total Bruto</label>
                                <input type="text" class="form-control money" id="total" name="total" readonly value="{{ $dailyRate?->total ?? '' }}">
                            </div>
                            <div class="mb-3 me-3">
                                <label class="form-label" for="imposto_id">Imposto (%)</label>
                                <input type="number" class="form-control percentage" id="imposto_id" name="imposto_id" value="{{$imposto_pago ?? 0}}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="total_liq">Valor Total Liquido</label>
                                <input type="text" class="form-control" x-mask:dynamic="$money($input, 'R$')" id="total_liq" name="total_liq" readonly value="{{ $dailyRate?->total ?? '' }}">
                            </div>

                        </div>
                        
                    </div>
<!-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- -->

                    <div class="mb-3">
                        <label class="form-label" for="observation">Observação</label>
                        <textarea class="form-control" id="observation" name="observation" rows="4">{!! $dailyRate?->observation ?? '' !!}</textarea>
                    </div>
                </form>
            </div>
            <div class="card-footer d-flex justify-content-end align-items-center">
                @if ($dailyRate?->id ?? false)
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-primary right" style="margin-right: 0%" onclick="update({{ $dailyRate?->id ?? null }})">Salvar</button>
                    </div>
                @else
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-primary right" style="margin-right: 0%"onclick="post()">Salvar</button>
                    </div>
                @endif
            </div> 
        </div>
    </div>

</x-app-layout>



<script>
    let companySections = [];
    let selectedSection;
    let selectedCollaborator;
    let selectedCompany;
    $(document).ready(function () {

        loadSectionInfo();
        calcular();

        $('#addition').on('input change', function(){
            calcular();
        });
        $('#employee_discount').on('input change', function(){
            calcular();
        });
        $('#inss_id').on('input change', function(){
            calcular();
        });
        $('#collaborator_id').on('input change', function(){
            getSelectedColaborator($(this).val());

            verificarRegraValorExtra();
        });

        $('#transport_id').on('input change', function(){
            calcular();
        });
        $('#imposto_id').on('input change', function(){
            calcular();
        });
        $('#form-hourly-rate input:not([name="company_id"])').on('input change', function () {
          //  calcular();
        });

        $('#company_id').on('input change', function () {
            getSelectedCompany($(this).val());
            getCompanySections($(this).val());
            //getHourlyRate();

            verificarRegraValorExtra();
        });
        $('#sectionSelect_id').on('input change', function () {
            selectedSection = companySections.find(item => item.section_id === Number($(this).val()));
            loadSectionInfo();
            //getHourlyRate();
            calcular();

        });
        $('#feeding_id').on('input change', function () {
            calcular();

        });
        $('#start').on('input change', function () {
            if (selectedSection.perHour === 1){
                calcular();

            }
        });
        $('#end').on('input change', function () {
            if (selectedSection.perHour === 1){
                calcular();

            }
        });


        $('#collaborator_id').select2({
            theme: 'bootstrap-5'
        });

        $('#company_id').select2({
            theme: 'bootstrap-5'
        });

        let moneyMask = new Inputmask("R$ 99999,99", {
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
    });

    function verificarRegraValorExtra() {
        const empresaId = $('#company_id').val();
        const colaboradorId = $('#collaborator_id').val();
        
        // Campo para valor extra
        const $additionField = $('#addition');
        $additionField.val(0);

        if (empresaId && colaboradorId) {
            $.ajax({
                url: `/rules/acordo-valor-extra/find/${empresaId}/${colaboradorId}`, 
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response && response.value !== undefined) {
                        $additionField.val(response.value);
                        
                        calcular(); 
                        
                        console.log(`Valor extra carregado: ${response.value}`);
                    } else {
                        $additionField.val(0);
                        calcular();
                        console.error("Resposta do servidor não continha 'value'. Definido como 0.");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro ao buscar regra de valor extra:", status, error);
                    
                    $additionField.val(0);
                    calcular();
                }
            });

        } else {
            $additionField.val(0);
            calcular();
        }
    }

    function post() {
        $.ajax({
            url: '{{ route('daily-rate.store') }}',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: $('#form-hourly-rate').serialize(),
            success: function(response) {
                Swal.fire({
                    title: response?.title ?? 'Sucesso!',
                    text: response?.message ?? 'Sucesso na ação!',
                    icon: response?.type ?? 'success'
                }).then((result) => {
                    $('#form-hourly-rate')[0].reset();

                    window.location.reload();
                });
            },
            error: function(response) {
                response = JSON.parse(response.responseText);
                Swal.fire({
                    title: response?.title ?? 'Oops!',
                    html: response?.message?.replace(/\n/, '<br>') ?? 'Erro na ação!',
                    icon: response?.type ?? 'error'
                });
            }
        });
    }

    function update(id) {
        $.ajax({
            url: "{{ route('daily-rate.update', '') }}" + '/' + id,
            type: 'PUT',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: $('#form-hourly-rate').serialize(),
            success: function(response) {
                Swal.fire({
                    title: response?.title ?? 'Sucesso!',
                    text: response?.message ?? 'Sucesso na ação!',
                    icon: response?.type ?? 'success'
                }).then((result) => {
                    $('#form-hourly-rate')[0].reset();

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

    function loadSectionInfo(){
    if (selectedSection){
        if (selectedSection.perHour === 1){
            if ($('#end').prop('disabled')){  
                $('#end').prop('disabled', false);
            }
            
            document.getElementById("employee_pay_id").value = 0;
        } else {
            if (!$('#end').prop('disabled')) {
                $('#end').prop('disabled', true);
            }
            $('#end').val('');
        }
    } else {
        if (@json($dailyRate) === null){
            document.getElementById("employee_pay_id").value = '';
            document.getElementById("transport_id").value = '';
            document.getElementById("leaderComission_id").value = '';
            document.getElementById("total").value = '';
            document.getElementById("total_liq").value = '';
        }
    }
}

    function getSectionNameById(id) {
        let sections = @json($sections);
        const section = sections.find(item => item.id === id);
        return section ? section.name : 'ID não encontrado';
}
    function getSelectedColaborator(colaboratorId){
        $.ajax({
            url: "/get-colaborator/" + colaboratorId,
            type: "GET",
            dataType: "json",
            success: function (colaborador) {
                selectedCollaborator = colaborador;
                calcular();
            },
            error: function (xhr) {
                console.error("Erro ao buscar setores:", xhr.responseText);
            }
        });
    }

    function getSelectedCompany(companyId){
        $.ajax({
            url: "/get-company/" + companyId,
            type: "GET",
            dataType: "json",
            success: function (company) {
                selectedCompany = company;
                calcular();
            },
            error: function (xhr) {
                console.error("Erro ao buscar setores:", xhr.responseText);
            }
        });
    }

    function getCompanySections(companyId){
  
        $.ajax({
            url: "/get-company-sections/" + companyId,
            type: "GET",
            dataType: "json",
            success: function (sections) {
                let select = $("#sectionSelect_id");
                select.empty();
                select.append('<option value="" disabled selected>Selecione um setor</option>');
                companySections = sections;
                if (sections.length > 0) {
                    sections.forEach(function (section) {
                        select.append(`<option value="${section.section_id}">${getSectionNameById(section.section_id)}</option>`);
                    });
                    selectedSection = null;
                    select.prop("disabled", false); 

                    let dailyRate = @json($dailyRate);

                    if (dailyRate) { 
                        let sectionId = Number(dailyRate.section_id) || null;

                        if (sectionId) {
                            $('#sectionSelect_id').val(sectionId);
                            selectedSection = companySections.find(item => item.section_id === sectionId);
                        }
                    }
                    calcular();
                } else {
                    select.append('<option value="" disabled>Nenhum setor encontrado</option>');
                    select.prop("disabled", true);
                }
            },
            error: function (xhr) {
                console.error("Erro ao buscar setores:", xhr.responseText);
            }
        });
    }

    function calculate_pay_perHour(value_per_hour){
        let startDate = $('#form-hourly-rate input[name="start"]').val();
        let endDate = $('#form-hourly-rate input[name="end"]').val();
        
        let workedHourly = difHourly(startDate, endDate);
        if (workedHourly <= 0){
            $('#total_time').val(formatTime(0));
            if (workedHourly < 0){
                $('#total_time').val("Horarios invalidos, Saída antecede entrada");

            }

            return 0;
        } else{
            $('#total_time').val(formatTime(workedHourly));

            return workedHourly * value_per_hour;
        }

    }

    function calcular() {
        loadSectionInfo();
        if (selectedSection == null || selectedCollaborator == null) return;
        
        let transport = Number(((parseFloat(document.getElementById('transport_id').inputmask.unmaskedvalue()) || 0) / 100).toFixed(2));
        let feeding = (document.getElementById('feeding_id').checked ? 10 : 0);
        let addition = Number(((parseFloat(document.getElementById('addition').inputmask.unmaskedvalue()) || 0) / 100).toFixed(2));
        let leaderComission = selectedSection.leaderComission;
        let earned = selectedSection.earned;
        let employee_discount = Number(((parseFloat(document.getElementById('employee_discount').inputmask.unmaskedvalue()) || 0) / 100).toFixed(2));
        
        let pay_amount = selectedSection.employeePay;
        if (selectedCollaborator.is_leader === 1) {
            leaderComission = 0;
            pay_amount = selectedSection.leaderPay;
        }else if (selectedCollaborator.is_extra === 1) {
            pay_amount = selectedSection.extra;
        } else if (selectedCollaborator.is_supervisor === 1) {
            pay_amount = selectedSection.supervisorPay;
        }
        if (selectedSection.perHour === 1)  {
            pay_amount = calculate_pay_perHour(pay_amount);
            earned = calculate_pay_perHour(selectedSection.earned);
        }
        pay_amount += addition;

        $('#employee_pay_id').val((pay_amount + feeding - employee_discount).toFixed(2));
        let inss_discount = $('#inss_id').val();
        if (selectedCompany.not_flashing) {
            inss_discount = 0;
        }
        
        let tax = ((parseFloat(document.getElementById('imposto_id').value) || 0) / 100);
        console.log("imposto: ", tax);
        
        let total = ((earned)).toFixed(2);
        let total_liq = (total * (1-tax) - (pay_amount + feeding - employee_discount) - transport - inss_discount - leaderComission).toFixed(2);

        $("#leaderComission_id").val(leaderComission.toFixed(2));
        $('#total').val(parseFloat(total).toFixed(2));
        $('#imposto_paid_id').val((total * tax).toFixed(2));
        $('#total_liq').val(parseFloat(total_liq).toFixed(2));
    }

    function difHourly(start, end) {
        try {
            if (start == "" || end == "") return 0;
            
            let startDate = new Date(start);
            let endDate = new Date(end);  

            let diffInMilliseconds = endDate - startDate; 

            let diffInSeconds = diffInMilliseconds / 1000;
            let diffInMinutes = diffInSeconds / 60;
            let diffInHours = diffInMinutes / 60;

            return diffInHours ?? 0;
        } catch {
            return 0;
        }
    }

    function formatTime(value) {
        let hours = Math.floor(value); // Obtém a parte inteira como horas
        let minutes = Math.round((value % 1) * 60); // Converte a parte decimal para minutos

        // Garante que o formato seja sempre HH:MM
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
    }
    $(document).ready(function() {
        $('start').disabled = false;
        let dailyRate = @json($dailyRate);

        if (dailyRate) { 
            let companyId = dailyRate.company_id || null;
            let collaboratorId = dailyRate.collaborator_id || null;

            if (companyId) {
                $('#company_id').val(companyId).trigger('change');
                getCompanySections(companyId);
            }

            if (collaboratorId) {
                getSelectedColaborator(collaboratorId);
            }
        }
    });
    
</script>