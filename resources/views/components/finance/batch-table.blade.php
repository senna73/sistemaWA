@props(['batches'])

<div style="width: 100%; overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
            <tr style="background-color: #f9fafb;">
                <th style="padding: 16px 24px; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;">Identificação</th>
                <th style="padding: 16px 24px; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;">Data de Criação</th>
                <th style="padding: 16px 24px; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">Valor Total</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nº Nota</th>
                <th style="padding: 16px 24px; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Status</th>
                <th style="padding: 16px 24px; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">Ações</th>
            </tr>
        </thead>
        <tbody style="background-color: #ffffff;">
            @forelse($batches as $batch)
                @php
                    $totalEarned = (float) ($batch->total_earned ?? 0);
                    $totalAmount = (float) $batch->total_amount;
                    $hasDifference = abs($totalAmount - $totalEarned) > 0.01;
                    
                    $createdAt = optional($batch->created_at);
                @endphp

                <tr style="border-top: 1px solid #f3f4f6; transition: background-color 0.2s; {{ $hasDifference ? 'background-color: #fef2f2;' : '' }}" 
                    onmouseover="this.style.backgroundColor='{{ $hasDifference ? '#fee2e2' : '#f9fafb' }}'" 
                    onmouseout="this.style.backgroundColor='{{ $hasDifference ? '#fef2f2' : '#ffffff' }}'">
                    
                    {{-- Identificação --}}
                    <td style="padding: 12px 16px;">
                        <div style="display: flex; flex-direction: column;">
                            <span style="background-color: #2c3e50; color: #fff; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 0.75rem; text-transform: uppercase; width: fit-content; margin-bottom: 4px;">
                                {{ $batch->company->name ?? 'N/A' }}
                            </span>
                            <small style="color: #9ca3af; font-size: 0.7rem;">ID do Lote: #{{ $batch->id }}</small>
                        </div>
                    </td>
                    
                    {{-- Data --}}
                    <td style="padding: 16px 24px;">
                        <div style="display: flex; flex-direction: column;">
                            @if($createdAt)
                                <span style="font-size: 14px; color: #4b5563;">{{ $createdAt->format('d/m/Y') }}</span>
                                <span style="font-size: 10px; color: #d1d5db;">{{ $createdAt->format('H:i') }} h</span>
                            @else
                                <span style="font-size: 14px; color: #9ca3af;">Data N/A</span>
                            @endif
                        </div>
                    </td>
                    
                    {{-- Valor --}}
                    <td style="padding: 16px 24px; text-align: right; font-weight: 800; color: {{ $hasDifference ? '#dc2626' : '#1f2937' }}; font-size: 15px;">
                        <div style="display: flex; flex-direction: column; align-items: flex-end;">
                            <span>R$ {{ number_format($totalAmount, 2, ',', '.') }}</span>
                            @if($hasDifference)
                                <small style="font-size: 0.65rem; color: #ef4444; font-weight: normal;">
                                    Soma Real: R$ {{ number_format($totalEarned, 2, ',', '.') }}
                                </small>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($batch->invoice_number)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                <i class='bx bx-receipt text-sm'></i>
                                {{ $batch->invoice_number }}
                            </span>
                        @else
                            <span class="text-gray-300 text-xs">---</span>
                        @endif
                    </td>
                    {{-- Status --}}
                    <td style="padding: 16px 24px; text-align: center;">
                        <x-finance.batch-status-badge :status="$batch->status" />
                    </td>
                    
                    {{-- Ações --}}
                    <td style="padding: 16px 24px; text-align: right;">
                        <a href="{{ route('admin.batches.show', $batch) }}" style="color: #4f46e5; text-decoration: none; padding: 8px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; transition: background 0.2s;" onmouseover="this.style.background='#eef2ff'" onmouseout="this.style.background='transparent'">
                            <i class='bx bx-chevron-right' style="font-size: 1.5rem;"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="padding: 60px 24px; text-align: center; color: #9ca3af;">
                        <i class='bx bx-package' style="font-size: 3.5rem; margin-bottom: 12px; display: block; color: #e5e7eb;"></i>
                        <p style="margin: 0; font-weight: 500;">Nenhum lote financeiro encontrado.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>