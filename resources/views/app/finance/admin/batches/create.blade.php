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

    {{-- Valor do Repasse --}}
    <div class="col-md-4">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Valor do Repasse (Soma das Notas)</label>
        <div class="input-group input-group-merge">
            <span class="input-group-text bg-light">R$</span>
            <input type="text" name="total_amount" id="valor-mask" class="form-control bg-light" placeholder="0,00" readonly required>
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

    {{-- Seção Dinâmica de Notas Fiscais --}}
    <div class="col-md-12">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.7rem; display: block;">Notas Fiscais Vinculadas</label>
        
        <div id="invoices-container" class="d-flex flex-column gap-2 rounded p-2" style="background-color: #fafafa; border: 1px solid #eef0f2;">
            
            {{-- Bloco Padrão (Primeira Nota) --}}
            <div class="invoice-block p-3 rounded border">
                {{-- Linha Superior: NºNota | Valor | Botão + --}}
                <div class="row g-2 align-items-center mb-2">
                    <div class="col">
                        <input type="text" name="invoice_numbers[]" class="form-control invoice-mask" placeholder="Nº da Nota (000.000.000.000)">
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" name="invoice_amounts[]" class="form-control invoice-value-mask text-end" placeholder="0,00">
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-secondary btn-add-invoice" title="Adicionar outra nota">
                            <i class='bx bx-plus'></i>
                        </button>
                    </div>
                </div>
                {{-- Linha Inferior: Descrição --}}
                <div class="row">
                    <div class="col-12">
                        <input type="text" name="invoice_descriptions[]" class="form-control form-control-sm" placeholder="Descrição ou observação desta nota específica...">
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Descrição Geral do Lote --}}
    <div class="col-md-12">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Descrição Geral do Lote</label>
        <textarea name="description" class="form-control" rows="2" placeholder="Informações adicionais sobre o lote..."></textarea>
    </div>

    {{-- Botão Enviar --}}
    <div class="col-md-12">
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
            <i class='bx bx-plus-circle'></i> Adicionar Lote
        </button>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.6/dist/inputmask.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
    const maskInvoiceNumber = {
        mask: "999.999.999.999",
        greedy: false,
        placeholder: "",
        removeMaskOnSubmit: true
    };

    const maskInvoiceValue = {
        alias: "decimal",
        groupSeparator: ".",
        radixPoint: ",",
        digits: 2,
        digitsOptional: false,
        placeholder: "0",
        autoGroup: true,
        rightAlign: false,
        numericInput: true
    };

    const totalAmountInput = document.getElementById("valor-mask");
    Inputmask(maskInvoiceValue).mask(totalAmountInput);

    const container = document.getElementById('invoices-container');

    function updateInvoicesState() {
        const blocks = container.querySelectorAll('.invoice-block');
        let totalSum = 0;

        blocks.forEach((block, index) => {
            if (index % 2 === 0) {
                block.style.backgroundColor = '#ffffff';
                block.style.borderColor = '#e4e6fc';
            } else {
                block.style.backgroundColor = '#f4f5f7';
                block.style.borderColor = '#e4e6fc';
            }

            const valueInput = block.querySelector('.invoice-value-mask');
            if (valueInput && valueInput.value) {
                let cleanValue = valueInput.value.replace(/\./g, '').replace(',', '.');
                let parsed = parseFloat(cleanValue);
                if (!isNaN(parsed)) {
                    totalSum += parsed;
                }
            }
        });

        totalAmountInput.value = totalSum.toFixed(2).replace('.', ',');
        totalAmountInput.dispatchEvent(new Event('input'));
    }

    // Inicializa a primeira linha padrão
    Inputmask(maskInvoiceNumber).mask(container.querySelector('.invoice-mask'));
    Inputmask(maskInvoiceValue).mask(container.querySelector('.invoice-value-mask'));
    updateInvoicesState();

    // Ouvinte para recalcular a soma sempre que o usuário terminar de digitar um valor individual
    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('invoice-value-mask')) {
            updateInvoicesState();
        }
    });

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-add-invoice') || e.target.closest('.btn-add-invoice')) {
            e.preventDefault();

            const newBlock = document.createElement('div');
            newBlock.className = 'invoice-block p-3 rounded border animate__animated animate__fadeInUp';
            newBlock.style.animationDuration = '0.2s';
            
            newBlock.innerHTML = `
                <div class="row g-2 align-items-center mb-2">
                    <div class="col">
                        <input type="text" name="invoice_numbers[]" class="form-control invoice-mask" placeholder="Nº da Nota (000.000.000.000)">
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" name="invoice_amounts[]" class="form-control invoice-value-mask text-end" placeholder="0,00">
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-danger btn-remove-invoice" title="Remover esta nota">
                            <i class='bx bx-trash'></i>
                        </button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <input type="text" name="invoice_descriptions[]" class="form-control form-control-sm" placeholder="Descrição ou observação desta nota específica...">
                    </div>
                </div>
            `;

            container.appendChild(newBlock);

            // Aplica as máscaras estritamente nos elementos novos
            Inputmask(maskInvoiceNumber).mask(newBlock.querySelector('.invoice-mask'));
            Inputmask(maskInvoiceValue).mask(newBlock.querySelector('.invoice-value-mask'));
            
            updateInvoicesState();
            newBlock.querySelector('.invoice-mask').focus();
        }

        // Botão Lixeira clicado
        if (e.target.classList.contains('btn-remove-invoice') || e.target.closest('.btn-remove-invoice')) {
            e.preventDefault();
            const block = e.target.closest('.invoice-block');
            block.remove();
            
            // Recalcula tudo imediatamente após sumir com a nota
            updateInvoicesState();
        }
    });
});
</script>