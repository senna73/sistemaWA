<x-app-layout>
    <style>
        .relative.z-0 svg {
            width: 20px !important;
            height: 20px !important;
            display: inline;
        }
        
        nav[role="navigation"] svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        div[flex="1"] {
            display: none !important;
        }
        
        @media (min-width: 640px) {
            div[flex="1"] {
                display: flex !important;
            }
        }
    </style>
    <div style="padding: 20px; max-width: 100%; margin: 0 auto; font-family: sans-serif;">
        
        {{-- Header --}}
        <div style="margin-bottom: 25px;">
            <h2 style="margin: 0; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                <i class='bx bx-book-content' style="color: #3b82f6;"></i> Livro Caixa (Ledger)
            </h2>
            <p style="color: #64748b; margin: 5px 0 0 0;">Controle de fluxo real e saldos bancários</p>
        </div>

        {{-- Cards de Saldo --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            @foreach($accounts as $account)
                <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="color: #64748b; font-weight: 600; font-size: 0.85rem; text-transform: uppercase;">{{ $account->name }}</span>
                        <i class='bx bx-wallet' style="color: #3b82f6; font-size: 1.2rem;"></i>
                    </div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #1e293b;">
                        R$ {{ number_format($account->balance, 2, ',', '.') }}
                    </div>
                    <div style="margin-top: 10px; display: flex; gap: 10px; font-size: 0.75rem;">
                        <span style="color: #10b981;">↑ R$ {{ number_format($account->total_added, 2, ',', '.') }}</span>
                        <span style="color: #ef4444;">↓ R$ {{ number_format($account->total_spent, 2, ',', '.') }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tabela de Histórico --}}
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; background: #f8fafc;">
                <h3 style="margin: 0; font-size: 1rem; color: #475569;">Movimentações Recentes</h3>
            </div>
            
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="text-align: left; padding: 12px 20px; color: #64748b;">Data</th>
                        <th style="text-align: left; padding: 12px 20px; color: #64748b;">Categoria / Descrição</th>
                        <th style="text-align: left; padding: 12px 20px; color: #64748b;">Vínculo</th>
                        <th style="text-align: right; padding: 12px 20px; color: #64748b;">Valor</th>
                        <th style="text-align: right; padding: 12px 20px; color: #64748b;">Saldo Após</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 15px 20px; color: #64748b;">
                                {{ $entry->created_at->format('d/m/Y') }}<br>
                                <small>{{ $entry->created_at->format('H:i') }}</small>
                            </td>
                            <td style="padding: 15px 20px;">
                                <span style="display: block; font-weight: 600; color: #334155; font-size: 0.75rem; text-transform: uppercase;">{{ $entry->category }}</span>
                                <span style="color: #64748b;">{{ $entry->description }}</span>
                            </td>
                            <td style="padding: 15px 20px;">
                                @if($entry->collaboratorWallet)
                                    <span style="background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">
                                        Colaborador: {{ $entry->collaboratorWallet->collaborator->name }}
                                    </span>
                                @elseif($entry->costCenter)
                                    <span style="background: #fff7ed; color: #9a3412; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">
                                        CC: {{ $entry->costCenter->name }}
                                    </span>
                                @else
                                    <span style="color: #cbd5e1;">--</span>
                                @endif
                            </td>
                            <td style="padding: 15px 20px; text-align: right; font-weight: 700; color: {{ $entry->entry_type == 'credit' ? '#10b981' : '#ef4444' }};">
                                {{ $entry->entry_type == 'credit' ? '+' : '-' }} R$ {{ number_format($entry->amount, 2, ',', '.') }}
                            </td>
                            <td style="padding: 15px 20px; text-align: right; color: #1e293b; font-weight: 600;">
                                R$ {{ number_format($entry->balance_after, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 40px; text-align: center; color: #94a3b8;">Nenhuma movimentação encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Paginação --}}
            <div style="padding: 15px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                {{ $entries->links() }}
            </div>
        </div>
    </div>
</x-app-layout>