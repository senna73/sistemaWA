@props(['status'])

@php
    $config = [
        'pending'   => [
            'bg'    => '#fef3c7', // Amarelo pastel
            'text'  => '#b45309', // Marrom/Laranja escuro
            'border'=> '#fde68a',
            'label' => 'Pendente',
            'icon'  => 'bx-time-five'
        ],
        'processed' => [
            'bg'    => '#dcfce7', // Verde pastel
            'text'  => '#15803d', // Verde escuro
            'border'=> '#bbf7d0',
            'label' => 'Processado',
            'icon'  => 'bx-check-double'
        ],
        'cancelled' => [
            'bg'    => '#fee2e2', // Vermelho pastel
            'text'  => '#b91c1c', // Vermelho escuro
            'border'=> '#fecaca',
            'label' => 'Cancelado',
            'icon'  => 'bx-x-circle'
        ],
    ][$status] ?? [
        'bg'    => '#f3f4f6', 
        'text'  => '#374151', 
        'border'=> '#e5e7eb',
        'label' => $status,
        'icon'  => 'bx-info-circle'
    ];
@endphp

<span style="
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    border-radius: 9999px;
    border: 1px solid {{ $config['border'] }};
    background-color: {{ $config['bg'] }};
    color: {{ $config['text'] }};
    letter-spacing: 0.025em;
    white-space: nowrap;
">
    <i class='bx {{ $config['icon'] }}' style="font-size: 12px;"></i>
    {{ $config['label'] }}
</span>