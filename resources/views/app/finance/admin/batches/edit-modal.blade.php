<div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
    <button type="button" onclick="document.getElementById('modal-editar-lote').style.display='flex'" style="background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s;">
        <i class='bx bx-edit-alt'></i> Editar Dados do Lote
    </button>
</div>

<div id="modal-editar-lote" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center; padding: 20px; box-sizing: border-box;">
    <div style="background: #f8fafc; width: 100%; max-width: 800px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; font-family: sans-serif;">
        
        <div style="padding: 20px; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #1e293b; font-size: 1.25rem; font-weight: 700;">Editar Lote de Pagamento #{{ $batch->id }}</h3>
            <button type="button" onclick="document.getElementById('modal-editar-lote').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>

        <form action="{{ route('admin.batches.update', $batch->id) }}" method="POST" id="form-editar-lote" style="padding: 20px; display: flex; flex-direction: column; gap: 16px; margin: 0; max-height: calc(100vh - 150px); overflow-y: auto;">
            @csrf
            @method('PUT')

            <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 2; min-width: 250px;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Estabelecimento</label>
                    <select name="company_id" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #334155; font-size: 0.95rem;" required>
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @foreach($companies as $c)
                            @if($c->id !== $company->id)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Valor do Repasse (Soma das Notas)</label>
                    <input type="text" name="total_amount" value="{{ number_format($batch->total_amount, 2, ',', '.') }}" id="input_edit_total_amount" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; color: #475569; background: #f1f5f9; font-size: 0.95rem; font-weight: 600;" readonly required>
                </div>
            </div>

            <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Início do Processamento</label>
                    <input type="date" name="period_start" value="{{ \Carbon\Carbon::parse($batch->period_start)->format('Y-m-d') }}" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.95rem;" required>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Fim do Processamento</label>
                    <input type="date" name="period_end" value="{{ \Carbon\Carbon::parse($batch->period_end)->format('Y-m-d') }}" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.95rem;" required>
                </div>
            </div>

            {{-- Seção Dinâmica de Notas Fiscais no Modal --}}
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin: 0;">Notas Fiscais Vinculadas</label>
                    <button type="button" id="btn-add-edit-invoice" style="background: #10b981; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <i class='bx bx-plus'></i> Nova Nota
                    </button>
                </div>
                
                <div id="edit-invoices-container" style="display: flex; flex-direction: column; gap: 10px; padding: 10px; background: #f1f5f9; border-radius: 8px; border: 1px solid #e2e8f0;">
                    
                    {{-- Loop pelas notas que já pertencem a este lote no banco --}}
                    @forelse($batch->invoices as $invoice)
                        <div class="edit-invoice-block" style="padding: 12px; border-radius: 6px; border: 1px solid #cbd5e1; display: flex; flex-direction: column; gap: 8px;">
                            {{-- Linha Superior: Número e Valor --}}
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <div style="flex: 1;">
                                    <input type="text" name="invoice_numbers[]" value="{{ $invoice->invoice_number }}" class="edit-invoice-mask" placeholder="Nº da Nota" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.9rem;" required>
                                </div>
                                <div style="width: 180px; display: flex; align-items: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding-left: 8px;">
                                    <span style="font-size: 0.9rem; color: #64748b;">R$</span>
                                    <input type="text" name="invoice_amounts[]" value="{{ number_format($invoice->amount, 2, ',', '.') }}" class="edit-invoice-value-mask" placeholder="0,00" style="width: 100%; padding: 8px; border: none; background: transparent; font-size: 0.9rem; text-align: right;" required>
                                </div>
                                <button type="button" class="btn-remove-edit-invoice" style="background: #ef4444; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                    <i class='bx bx-trash' style="font-size: 1.1rem;"></i>
                                </button>
                            </div>
                            {{-- Linha Inferior: Descrição --}}
                            <div>
                                <input type="text" name="invoice_descriptions[]" value="{{ $invoice->description }}" placeholder="Descrição desta nota..." style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.85rem;">
                            </div>
                        </div>
                    @empty
                        {{-- Linha padrão vazia se o lote não tiver nenhuma nota criada --}}
                        <div class="edit-invoice-block" style="padding: 12px; border-radius: 6px; border: 1px solid #cbd5e1; display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <div style="flex: 1;">
                                    <input type="text" name="invoice_numbers[]" class="edit-invoice-mask" placeholder="Nº da Nota" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.9rem;">
                                </div>
                                <div style="width: 180px; display: flex; align-items: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding-left: 8px;">
                                    <span style="font-size: 0.9rem; color: #64748b;">R$</span>
                                    <input type="text" name="invoice_amounts[]" class="edit-invoice-value-mask" placeholder="0,00" style="width: 100%; padding: 8px; border: none; background: transparent; font-size: 0.9rem; text-align: right;">
                                </div>
                                <button type="button" class="btn-remove-edit-invoice" style="background: #ef4444; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                    <i class='bx bx-trash' style="font-size: 1.1rem;"></i>
                                </button>
                            </div>
                            <div>
                                <input type="text" name="invoice_descriptions[]" placeholder="Descrição desta nota..." style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.85rem;">
                            </div>
                        </div>
                    @endforelse

                </div>
            </div>

            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Descrição Geral / Observações do Lote</label>
                <textarea name="description" rows="2" placeholder="Informações adicionais sobre o lote..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.95rem; resize: vertical;">{{ $batch->description }}</textarea>
            </div>

            <div style="margin-top: 10px; padding-top: 15px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('modal-editar-lote').style.display='none'" style="background: #cbd5e1; color: #475569; border: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; cursor: pointer;">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-edit" style="background: #4f46e5; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    <i class='bx bx-save'></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Fechar modal ao clicar fora dele
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('modal-editar-lote');
        if (e.target === modal) modal.style.display = 'none';
    });

    document.addEventListener("DOMContentLoaded", function() {
        const editContainer = document.getElementById('edit-invoices-container');
        const inputTotalBatch = document.getElementById('input_edit_total_amount');

        // Configurações das máscaras (Inputmask) reutilizadas do seu padrão anterior
        const maskNumberOptions = { mask: "999.999.999.999", greedy: false, placeholder: "", removeMaskOnSubmit: true };
        const maskValueOptions = { alias: "decimal", groupSeparator: ".", radixPoint: ",", digits: 2, digitsOptional: false, placeholder: "0", autoGroup: true, rightAlign: false, numericInput: true };

        // Função para atualizar visual zebrado e somar valores no Modal de Edição
        function updateEditInvoicesState() {
            const blocks = editContainer.querySelectorAll('.edit-invoice-block');
            let totalSum = 0;

            blocks.forEach((block, index) => {
                // Intercala cores de fundo (Branco / Cinza Claro)
                if (index % 2 === 0) {
                    block.style.backgroundColor = '#ffffff';
                } else {
                    block.style.backgroundColor = '#f8fafc';
                }

                // Soma o valor individual
                const valInput = block.querySelector('.edit-invoice-value-mask');
                if (valInput && valInput.value) {
                    let cleanValue = valInput.value.replace(/\./g, '').replace(',', '.');
                    let parsed = parseFloat(cleanValue);
                    if (!isNaN(parsed)) {
                        totalSum += parsed;
                    }
                }
            });

            // Formata a soma e joga de volta no input totalizador de leitura do lote
            inputTotalBatch.value = totalSum.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Inicializa máscaras nas notas que vieram prontas do banco
        Inputmask(maskNumberOptions).mask(editContainer.querySelectorAll('.edit-invoice-mask'));
        Inputmask(maskValueOptions).mask(editContainer.querySelectorAll('.edit-invoice-value-mask'));
        updateEditInvoicesState();

        // Recalcula o valor toda vez que alterarem o valor de alguma nota individual no Modal
        editContainer.addEventListener('input', function(e) {
            if (e.target.classList.contains('edit-invoice-value-mask')) {
                updateEditInvoicesState();
            }
        });

        // Adicionar nova nota dentro do Modal de Edição
        document.getElementById('btn-add-edit-invoice').addEventListener('click', function(e) {
            e.preventDefault();

            const newBlock = document.createElement('div');
            newBlock.className = 'edit-invoice-block';
            newBlock.style = 'padding: 12px; border-radius: 6px; border: 1px solid #cbd5e1; display: flex; flex-direction: column; gap: 8px;';
            
            newBlock.innerHTML = `
                <div style="display: flex; gap: 10px; align-items: center;">
                    <div style="flex: 1;">
                        <input type="text" name="invoice_numbers[]" class="edit-invoice-mask" placeholder="Nº da Nota" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.9rem;" required>
                    </div>
                    <div style="width: 180px; display: flex; align-items: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding-left: 8px;">
                        <span style="font-size: 0.9rem; color: #64748b;">R$</span>
                        <input type="text" name="invoice_amounts[]" class="edit-invoice-value-mask" placeholder="0,00" style="width: 100%; padding: 8px; border: none; background: transparent; font-size: 0.9rem; text-align: right;" required>
                    </div>
                    <button type="button" class="btn-remove-edit-invoice" style="background: #ef4444; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                        <i class='bx bx-trash' style="font-size: 1.1rem;"></i>
                    </button>
                </div>
                <div>
                    <input type="text" name="invoice_descriptions[]" placeholder="Descrição desta nota..." style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.85rem;">
                </div>
            `;

            editContainer.appendChild(newBlock);

            // Vincula as máscaras
            Inputmask(maskNumberOptions).mask(newBlock.querySelector('.edit-invoice-mask'));
            Inputmask(maskValueOptions).mask(newBlock.querySelector('.edit-invoice-value-mask'));

            updateEditInvoicesState();
            newBlock.querySelector('.edit-invoice-mask').focus();
        });

        // Remover nota dentro do Modal de Edição
        editContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remove-edit-invoice') || e.target.closest('.btn-remove-edit-invoice')) {
                e.preventDefault();
                const block = e.target.closest('.edit-invoice-block');
                block.remove();
                updateEditInvoicesState();
            }
        });

        // Evento de submissão do formulário de edição
        document.getElementById('form-editar-lote').addEventListener('submit', function(e) {
            const btn = document.getElementById('btn-submit-edit');
            
            // Trata o campo do valor do repasse antes do envio
            if (inputTotalBatch) {
                inputTotalBatch.value = inputTotalBatch.value.replace(/\./g, '').replace(',', '.');
            }

            btn.disabled = true;
            btn.style.opacity = '0.7';
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> SALVANDO...';
        });
    });
</script>