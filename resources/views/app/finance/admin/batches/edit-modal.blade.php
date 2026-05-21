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

        <form action="{{ route('admin.batches.update', $batch->id) }}" method="POST" id="form-editar-lote" style="padding: 20px; display: flex; flex-direction: column; gap: 16px; margin: 0;">
            @csrf
            @method('PUT')

            <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 2; min-width: 250px;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Estabelecimento</label>
                    <select name="company_id" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #334155; font-size: 0.95rem;" required>
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                        {{-- Se você passar uma lista de empresas do controller, pode iterar aqui --}}
                    </select>
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Valor do Repasse</label>
                    <input type="text" name="total_amount" value="{{ number_format($batch->total_amount, 2, ',', '.') }}" id="input_edit_total_amount" inputmode="numeric" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.95rem; font-weight: 600;" required>
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

            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Nº da Nota</label>
                <input type="text" name="invoice_number" value="{{ $batch->invoice_number }}" placeholder="000.000.000" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.95rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Descrição / Observações</label>
                <textarea name="description" rows="3" placeholder="Informações adicionais sobre o lote..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.95rem; resize: vertical;">{{ $batch->description }}</textarea>
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

    // Máscara de moeda para o campo Valor do Repasse idêntica à que você já usa
    document.getElementById('input_edit_total_amount').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, "");
        let valorNumerico = parseFloat(value / 100) || 0;
        e.target.value = valorNumerico.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });

    // Limpar pontos e vírgulas antes do envio para o banco aceitar o float decimal
    document.getElementById('form-editar-lote').addEventListener('submit', function(e) {
        const btn = document.getElementById('btn-submit-edit');
        const inputValor = document.getElementById('input_edit_total_amount');

        if (inputValor) {
            let valorLimpo = inputValor.value.replace(/\./g, '').replace(',', '.');
            inputValor.value = valorLimpo;
        }

        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> SALVANDO...';
    });
</script>