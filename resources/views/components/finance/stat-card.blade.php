@props(['label', 'value', 'icon', 'color', 'sub' => null])

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 relative overflow-hidden transition-all hover:shadow-md h-full">
    {{-- Ícone de Fundo --}}
    <div class="absolute -right-1 -top-1 opacity-[0.03] rotate-12">
        <i class='bx {{ $icon }} text-6xl text-{{ $color }}-600'></i>
    </div>

    <div class="relative z-10">
        {{-- Header --}}
        <div class="flex items-center gap-2 mb-1">
            <div class="w-6 h-6 rounded bg-{{ $color }}-50 text-{{ $color }}-600 flex items-center justify-center">
                <i class='bx {{ $icon }} text-sm'></i>
            </div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ $label }}</p>
        </div>
        
        {{-- Valor Principal --}}
        <h3 class="text-xl font-bold text-gray-800 tracking-tight">{{ $value }}</h3>

        {{-- Sub-info --}}
        @if($sub)
            <div class="mt-1 flex items-center gap-1">
                <span class="w-1 h-1 rounded-full bg-{{ $color }}-400"></span>
                <p class="text-[9px] font-medium text-{{ $color }}-600 uppercase">{{ $sub }}</p>
            </div>
        @endif
    </div>
</div>