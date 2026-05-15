<div class="flex items-center justify-between mb-4">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Novo Lote de Pagamento
    </h2>
</div>
<form id="financial-form" action="{{ route('admin.batches.store') }}" method="POST" class="row g-3 items-end">
    @csrf

    <div class="col-md-8">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Estabelecimento</label>
        <select name="company_id" id="select-digitavel" class="form-select select2" required>
            <option value=""></option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}">
                    {{ $company->name }} {{ $company->city ? "({$company->city})" : '' }}
                </option>
            @endforeach
        </select>
    </div>
    {{-- Valor --}}
    <div class="col-md-4">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Valor do Repasse</label>
        <div class="input-group input-group-merge">
            <span class="input-group-text">R$</span>
            <input type="text" name="total_amount" id="valor-mask" class="form-control" placeholder="0,00" required>
        </div>
    </div>
    {{-- Data Ínicio --}}
    <div class="col-md-6">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Início do processamento</label>
        <input type="date" name="period_start" class="form-control" required>
    </div>
    {{-- Data Final --}}
    <div class="col-md-6">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Fim do processamento</label>
        <input type="date" name="period_end" class="form-control" required>
    </div>
    {{-- Nº da Nota --}}
    <div class="col-md-12">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Nº da Nota</label>
        <input type="text" name="invoice_number" id="invoice-mask" class="form-control" placeholder="000 000 000">
    </div>
    {{-- Descrição --}}
    <div class="col-md-12">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Descrição / Observações</label>
        <textarea name="description" class="form-control" rows="2" placeholder="Informações adicionais sobre o lote..."></textarea>
    </div>

    {{-- Botão --}}
    <div class="col-md-12">
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
            <i class='bx bx-plus-circle'></i> Adicionar
        </button>
    </div>
</form>
<script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.6/dist/inputmask.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof $.fn.select2 !== 'undefined') {
            $('#select-digitavel').select2({
                theme: 'bootstrap-5',
                placeholder: "Digite o nome ou selecione...",
                allowClear: true,
                width: '100%',
                dropdownParent: $('#select-digitavel').parent()
            });
        }

        var valorInput = document.getElementById("valor-mask");
        
        Inputmask({
            alias: "decimal",
            groupSeparator: ".",
            radixPoint: ",",
            digits: 2,
            digitsOptional: false,
            placeholder: "0",
            autoGroup: true,
            rightAlign: false,
            numericInput: true,
            onBeforeMask: function (value, opts) {
                return value.replace(/\./g, '').replace(',', '.');
            }
        }).mask(valorInput);
    });
    document.getElementById('financial-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);

        Swal.fire({
            title: 'Processando...',
            text: 'Criando o lote, aguarde.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            const data = await response.json();

            if (response.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: 'Lançamento feito com sucesso!',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                let errorMessages = '';
                if (data.errors) {
                    errorMessages = Object.values(data.errors).flat().join('<br>');
                } else {
                    errorMessages = data.message || 'Erro desconhecido ao processar.';
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Ops! Algo deu errado',
                    html: errorMessages,
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Erro de Conexão',
                text: 'O servidor não respondeu. Tenta de novo mais tarde.'
            });
        });
    });
    var invoiceInput = document.getElementById("invoice-mask");
    Inputmask({
        mask: "999.999.999.999",
        greedy: false,
        placeholder: "",
        removeMaskOnSubmit: true
    }).mask(invoiceInput);
</script>
