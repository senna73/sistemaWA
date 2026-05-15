<x-app-layout>
    <div style="padding: 20px 20px 0 20px; max-width: 100%; margin: 0 auto;">
        {{-- Mensagem de Erro Crítico (Ex: Saldo insuficiente, Divergência de valores) --}}
        @if(session('error'))
            <div style="background: #fef2f2; color: #b91c1c; padding: 16px; border-radius: 8px; border: 1px solid #fee2e2; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <i class='bx bx-error-alt' style="font-size: 1.5rem;"></i>
                <div>
                    <strong style="display: block;">Falha no Processamento</strong>
                    <span style="font-size: 0.9rem;">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        {{-- Mensagem de Sucesso --}}
        @if(session('success'))
            <div style="background: #f0fdf4; color: #15803d; padding: 16px; border-radius: 8px; border: 1px solid #dcfce7; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <i class='bx bx-check-circle' style="font-size: 1.5rem;"></i>
                <div>
                    <strong style="display: block;">Sucesso!</strong>
                    <span style="font-size: 0.9rem;">{{ session('success') }}</span>
                </div>
            </div>
        @endif
    </div>
<div style="width: 100%; font-family: sans-serif; display: flex; flex-direction: column; gap: 20px; padding: 20px; box-sizing: border-box;">    
    <div style="width: 100%; background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden;">        
        <div style="padding: 20px; border-bottom: 2px dashed #eee;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h5 style="margin: 0; color: #333; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px;">
                        Fechamento 
                        <span style="background-color: #2c3e50; color: #fff; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 0.9em; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                            {{$company->name}}
                        </span>
                    </h5>
                    <small style="color: #888;">Período: {{ \Carbon\Carbon::parse($batch->period_start)->format('d/m') }} a {{ \Carbon\Carbon::parse($batch->period_end)->format('d/m/y') }}</small>
                </div>

                {{-- Exibição do Número da Nota --}}
                @if($batch->invoice_number)
                    <div style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class='bx bx-receipt' style="color: #475569; font-size: 1.1rem;"></i>
                        <span style="color: #475569; font-weight: 700; font-size: 0.85rem; text-transform: uppercase;">
                            Nota: {{ $batch->invoice_number }}
                        </span>
                    </div>
                @endif
            </div>

            {{-- Exibição da Descrição --}}
            @if($batch->description)
                <div style="margin-top: 15px; padding: 12px; background: #f8fafc; border-left: 4px solid #cbd5e1; border-radius: 4px;">
                    <p style="margin: 0; font-size: 0.85rem; color: #64748b; line-height: 1.5;">
                        <strong style="color: #475569; text-transform: uppercase; font-size: 0.75rem; display: block; margin-bottom: 2px;">Descrição do Lote:</strong>
                        {{ $batch->description }}
                    </p>
                </div>
            @endif
        </div>

        <div style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span style="color: #555;">(+) Faturamento Bruto (Receita)</span>
                <span style="font-weight: bold; color: #2ecc71; font-size: 1.1rem;">R$ {{ number_format($financeiro['receita_bruta'], 2, ',', '.') }}</span>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 0.95rem;">
                <span style="color: #777;">(-) Repasse Colaboradores</span>
                <span style="color: #e74c3c; font-weight: 500;">R$ {{ number_format($financeiro['repasse_liquido'], 2, ',', '.') }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 0.95rem;">
                <span style="color: #777;">(-) Comissões de Líderes</span>
                <span style="color: #e74c3c; font-weight: 500;">R$ {{ number_format($financeiro['comissoes_lider'], 2, ',', '.') }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 0.95rem;">
                <span style="color: #777;">(-) Custos Operacionais (T/A)</span>
                <span style="color: #e74c3c; font-weight: 500;">R$ {{ number_format($financeiro['custos_operacao'], 2, ',', '.') }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 0.95rem;">
                <span style="color: #777;">(-) Encargos e Impostos</span>
                <span style="color: #e74c3c; font-weight: 500;">R$ {{ number_format($financeiro['impostos_taxas'], 2, ',', '.') }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee;">
                <span style="color: #555; font-weight: bold;">(=) Total de Saídas</span>
                <span style="font-weight: bold; font-size: 1.1rem;">R$ {{ number_format($extratoFinanceiro->sum('valor') + $financeiro['impostos_taxas'], 2, ',', '.') }}</span>
            </div>
        </div>

        <div style="background: {{ $financeiro['lucro_real'] >= 0 ? '#27ae60' : '#c0392b' }}; padding: 20px; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; color: white; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">Lucro Líquido Real</span>
            <span style="font-size: 1.5rem; font-weight: bold;">R$ {{ number_format($financeiro['lucro_real'], 2, ',', '.') }}</span>
        </div>
    </div>
</div>

<div class="dashboard-container">
    <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #fcfcfc; border-bottom: 1px solid #eee;">
                    <th style="text-align: left; padding: 12px 20px;">Favorecido</th>
                    <th style="text-align: center; padding: 12px 20px;">Natureza</th>
                    <th style="text-align: right; padding: 12px 20px;">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($extratoFinanceiro as $mov)
                <tr>
                    <td data-label="Favorecido" style="padding: 15px 20px; font-weight: 600;">{{ $mov['nome'] }}</td>
                    <td data-label="Natureza" style="padding: 15px 20px; text-align: center;">
                        <span style="background: #e0f2fe; color: #0369a1; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem;">{{ $mov['tipo'] }}</span>
                    </td>
                    <td data-label="Valor" style="padding: 15px 20px; text-align: right; font-weight: bold;">R$ {{ number_format($mov['valor'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
                
                <tr style="background: #f8fafc; border-top: 2px solid #e2e8f0;">
                    <td data-label="Ajuste" style="padding: 15px 20px; font-weight: 700;">Centro de Custo Loja</td>
                    <td data-label="Tipo" style="padding: 15px 20px; text-align: center;">
                        <span style="background: #f1f5f9; padding: 4px 12px; border-radius: 8px; font-size: 0.7rem;">AJUSTE</span>
                    </td>
                    <td data-label="Valor" style="padding: 15px 20px; text-align: right;">
                        <div id="container_input" style="display: inline-flex; align-items: center; background: #fff; border: 2px solid #cbd5e1; border-radius: 10px; padding: 6px 12px; transition: border-color 0.2s; width: 150px; margin-left: auto;">
                            <span style="color: #64748b; font-weight: 800; margin-right: 8px; font-size: 0.9rem;">R$</span>
                            <input type="text" 
                                id="input_centro_custo"
                                name="centro_custo_loja" 
                                form="form-fechamento"
                                inputmode="numeric"
                                placeholder="0,00"
                                style="border: none; outline: none; text-align: right; width: 100%; font-weight: 700; color: #1e293b; font-size: 1rem; background: transparent;">
                        </div>
                    </td>
                </tr>

            <tfoot>
                <tr style="background: #1e293b;">
                    <td colspan="2" class="total-label" style="padding: 20px; color: #fff; font-weight: bold; font-size: 0.85rem; letter-spacing: 1px;">TOTAL DO DESEMBOLSO</td>
                    <td style="padding: 20px; text-align: right; color: #fbbf24; font-size: 1.3rem; font-weight: 800;">
                        R$ <span id="display_desembolso">{{ number_format($extratoFinanceiro->sum('valor') + $financeiro['impostos_taxas'], 2, ',', '.') }}</span>
                    </td>
                </tr>
            </tfoot>
        </table>

        <form action="{{ $batch->status == 'processing' ? route('admin.batches.confirm-receipt') : route('admin.batches.process') }}" method="POST" id="form-fechamento">
            @csrf
            
            <input type="hidden" name="batch_id" value="{{ $batch->id }}">

            <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                @php
                    $isPending = $batch->status == 'pending';
                    $isProcessing = $batch->status == 'processing';
                    $isCompleted = $batch->status == 'completed';
                @endphp

                <button type="submit" 
                    {{ $isCompleted ? 'disabled' : '' }}
                    id="btn-submit"
                    style="
                        background: {{ $isCompleted ? '#64748b' : ($isProcessing ? '#15803d' : '#1e293b') }}; 
                        color: white; 
                        border: none; 
                        padding: 12px 30px; 
                        border-radius: 8px; 
                        font-weight: bold; 
                        cursor: {{ $isCompleted ? 'not-allowed' : 'pointer' }}; 
                        display: flex; 
                        align-items: center; 
                        gap: 8px; 
                        transition: all 0.2s;
                        opacity: {{ $isCompleted ? '0.6' : '1' }};
                    "
                >
                    @if($isCompleted)
                        <i class='bx bx-check-shield'></i> LOTE FINALIZADO (RECEBIDO)
                    @elseif($isProcessing)
                        <i class='bx bx-money'></i> CONFIRMAR RECEBIMENTO DO BOLETO
                    @else
                        <i class='bx bx-check-double'></i> PROCESSAR FECHAMENTO
                    @endif
                </button>
            </div>
        </form>

        {{-- Script para o efeito de carregamento ao clicar --}}
        <script>
            document.getElementById('form-fechamento').addEventListener('submit', function(e) {
                const btn = document.getElementById('btn-submit');
                
                // Evita que o script rode se o botão já estiver desabilitado
                if(btn.disabled) return;

                btn.disabled = true;
                btn.style.opacity = '0.7';
                btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> PROCESSANDO...';
            });
        </script>
    </div>
</div>

</x-app-layout>
<script>

document.getElementById('form-fechamento').addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    const inputCusto = document.getElementById('input_centro_custo');

    if (inputCusto) {
        let valorLimpo = inputCusto.value.replace(/\./g, '').replace(',', '.');
        inputCusto.value = valorLimpo;
    }

    // Feedback visual genérico
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> AGUARDE...';
});

document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('input_centro_custo');
    const container = document.getElementById('container_input');
    const displayDesembolso = document.getElementById('display_desembolso');
    const displayLucro = document.getElementById('display_lucro'); 

    const desembolsoBase = {{ $extratoFinanceiro->sum('valor') + $financeiro['impostos_taxas'] }};
    const lucroMaximo = {{ $financeiro['lucro_real'] }};

    function showToast(message) {
        const existingToast = document.getElementById('custom-toast');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        toast.id = 'custom-toast';
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #ef4444;
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: sans-serif;
            font-weight: 500;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            transition: transform 0.3s ease-out;
        `;
        toast.innerHTML = `<i class='bx bx-error-circle' style="font-size: 1.2rem;"></i> ${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.style.transform = 'translateY(0)', 10);
        setTimeout(() => {
            toast.style.transform = 'translateY(100px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function atualizarTotais(valorNumerico) {
        const novoDesembolso = desembolsoBase + valorNumerico;
        if (displayDesembolso) {
            displayDesembolso.innerText = novoDesembolso.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
        }
    }

    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, "");
        
        let valorNumerico = parseFloat(value / 100) || 0;

        if (valorNumerico > lucroMaximo) {
            valorNumerico = lucroMaximo;
            showToast("Valor limitado ao lucro disponível: R$ " + lucroMaximo.toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
            container.style.borderColor = '#fb7185';
        } else {
            container.style.borderColor = '#3b82f6';
        }

        e.target.value = valorNumerico.toLocaleString('pt-BR', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });

        atualizarTotais(valorNumerico);
    });

    input.addEventListener('focus', function() {
        container.style.borderColor = '#3b82f6';
    });

    input.addEventListener('blur', function() {
        container.style.borderColor = '#cbd5e1';
    });
});
</script>

<style>
    .dashboard-container {
        display: flex; 
        flex-direction: column; 
        gap: 15px;
        padding: 15px; 
        box-sizing: border-box;
        width: 100%;
    }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 10px;
            gap: 10px;
        }
        
        tfoot tr {
            display: flex !important;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .total-label {
            display: none;
        }

        #container_input {
            width: 120px !important;
        }
        thead { display: none; }

        tr {
            display: block;
            margin-bottom: 10px;
            border: 1px solid #eee !important;
            border-radius: 8px;
            background: #fff;
        }

        td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px !important;
            text-align: left !important;
            border-bottom: 1px solid #f9f9f9;
        }

        td:before {
            content: attr(data-label);
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #888;
            font-weight: bold;
        }
    }
</style>