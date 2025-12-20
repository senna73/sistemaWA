<x-app-layout>
    <style>
        .fab-container {
            position: fixed;
            bottom: 30px; 
            right: 30px; 
            z-index: 1000; 
        }

        .fab-button {
            padding: 15px 20px;
            border-radius: 30px;
            
            display: flex;
            align-items: center; 
            justify-content: center;
            white-space: nowrap; 
            font-weight: bold;
            text-decoration: none;
            
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
    </style>

    <div class="fab-container">
        
        
        <a href="{{ route('acordo-valor-extra.index') }}" 
        class="btn btn-primary fab-button" 
        title="Gerenciar Regras de Valor Extra">
        
        <i class="fas fa-money-check-alt fa-lg"></i> 
        
        Valores Extra 
    </a> 
    </div>
    <div class="container">
        <div class="md-3 mb-3">
            
            <a href="{{ route('daily-rate.create') }}" class="btn btn-outline-primary w-100">Registrar Diária</a>
        </div>

        <div class="card accordion-item mb-3">
            <h2 class="accordion-header" id="headingTwo">
              <button
                type="button"
                class="accordion-button collapsed"
                data-bs-toggle="collapse"
                data-bs-target="#accordion-hourly-rate"
                aria-expanded="false"
                aria-controls="accordion-hourly-rate"
              >
                Filtro
              </button>
            </h2>
            <div
              id="accordion-hourly-rate"
              class="accordion-collapse collapse"
              aria-labelledby="headingTwo"
              data-bs-parent="#accordionExample"
            >
              <div class="accordion-body">
                <form id="form-hourly-rate-filter">

                    <div class="mb-3">
                        <label class="form-label" for="collaborator_id">Colaborador</label>
                        <select class="form-control" id="collaborator_id" name="collaborator_id[]" multiple="multiple">
                            @foreach ($collaborators as $colaborator)
                                <option value="{{ $colaborator->id }}">{{ $colaborator->name }}</option>
                            @endforeach
                        </select>
                    </div>
                
                    <div class="mb-3">
                        <label class="form-label" for="company_id">Empresa</label>
                        <select class="form-control" id="company_id" name="company_id[]" multiple="multiple">
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                
                    <div class="mb-3">
                        <label class="form-label" for="start">Inicio</label>
                        <input type="datetime-local" class="form-control" id="start" name="start" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="end">Fim</label>
                        <input type="datetime-local" class="form-control" id="end" name="end" required>
                    </div>

                    <div class="card-footer d-flex justify-content-end align-items-center">
                        <div class="d-flex justify-content-end gap-3">
                            <button type="button" class="btn btn-info right" style="margin-right: 0%" onclick="reportRegisters()">Relatório de Registros</button>
                            <button type="button" class="btn btn-info right" style="margin-right: 0%" onclick="reportDailyRate()">Relatório Diárias</button>
                        </div>
                    </div> 
                </form>
              </div>
            </div>
          </div>

        <div class="card pb-3">
            <h5 class="card-header">Diárias</h5>
            <div class="table-responsive text-nowrap">
                <table id="table-daily-rate" class="table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Setor</th>
                            <th>Collaborador</th>
                            <th>Inicio</th>
                            <th>Fim</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

    </div>
</x-app-layout>

<script>
    $(document).ready(function() {
        $('#table-daily-rate').DataTable({
            processing: true,
            serverSide: false,
            pagingType: 'simple',
            responsive: true,
             
            ajax: {
                url: '{{ route('daily-rate.table') }}',
                data: function(d) {
                    d.collaborator_id = $('#collaborator_id').val();
                    d.company_id = $('#company_id').val();
                    d.start = $('#start').val();
                    d.end = $('#end').val();
                }
            },
            columns: [
                { data: 'company', name: 'company' },
                { data: 'section', name: 'section' },
                { data: 'collaborator', name: 'collaborator' },
                { data: 'start', name: 'start' },
                { data: 'end', name: 'end' },
                { data: 'actions', name: 'actions' },
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json',
            },
        });

        $('#collaborator_id').select2({
            theme: 'bootstrap-5'
        });

        $('#company_id').select2({
            theme: 'bootstrap-5'
        });
    });
    function reportRegisters(){
        window.location.href = "{{ route('report.registers') }}?" + $('#form-hourly-rate-filter').serialize();

    };

    function reportDailyRate() {
        window.location.href = "{{ route('report.daily-rates') }}?" + $('#form-hourly-rate-filter').serialize();
    }


    $('#form-hourly-rate-filter input, #form-hourly-rate-filter select').on('input change', function () {
        $('#table-daily-rate').DataTable().ajax.reload();
    });

    function remove(id){
        Swal.fire({
            title: 'Você tem certeza?',
            text: "Esta ação não pode ser desfeita!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => { 
            if (result.isConfirmed){
                $.ajax({
                    url: "{{ route("daily-rate.destroy", '') }}" + '/' + id,
                    type: "DELETE",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response){
                        Swal.fire({
                            title: response?.title ?? 'Sucesso!',
                            text: response?.message ?? 'Sucesso na ação!',
                            icon: response?.type ?? 'success'
                        }).then((result) => {
                            window.location.reload();
                        });
                    },
                    error: function(response){
                        response = JSON.parse(response.responseText);
                        Swal.fire({
                            title: response?.title ?? 'Oops!',
                            html: response?.message?.replace(/\n/g, '<br>') ?? 'Erro na ação!', // Substitui as quebras de linha por <br>
                            icon: response?.type ?? 'error'    
                        });
                    }
                });
            }
        });
    }
</script>