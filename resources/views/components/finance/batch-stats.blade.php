@props(['batches'])

<div style="display: flex; flex-wrap: wrap; width: 100%; gap: 0; margin: 0; box-sizing: border-box; align-items: stretch; border: 1px solid #f3f4f6; background-color: #ffffff; padding: 0;">
    
    {{-- Card 1: Total --}}
    <div style="flex: 1 1 300px; background: #ffffff; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-right: 1px solid #f3f4f6; border-bottom: 1px solid #f3f4f6; min-width: 0;">
        <div style="width: 3rem; height: 3rem; background: #eff6ff; color: #3b82f6; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class='bx bxs-bank' style="font-size: 1.5rem;"></i>
        </div>
        <div style="display: flex; flex-direction: column; justify-content: center; min-width: 0; flex-grow: 1;">
            <span style="font-size: 0.7rem; text-transform: uppercase; color: #9ca3af; font-weight: 700; letter-spacing: 0.05rem; margin-bottom: 0.25rem;">Total</span>
            <span style="font-size: clamp(1rem, 2vw, 1.25rem); font-weight: 800; color: #1f2937; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                R$ {{ number_format($batches->sum('total_amount'), 0, ',', '.') }}
            </span>
        </div>
    </div>

    {{-- Card 2: Saldo --}}
    <div style="flex: 1 1 300px; background: #ffffff; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-right: 1px solid #f3f4f6; border-bottom: 1px solid #f3f4f6; min-width: 0;">
        <div style="width: 3rem; height: 3rem; background: #f0fdf4; color: #22c55e; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class='bx bxs-wallet' style="font-size: 1.5rem;"></i>
        </div>
        <div style="display: flex; flex-direction: column; justify-content: center; min-width: 0; flex-grow: 1;">
            <span style="font-size: 0.7rem; text-transform: uppercase; color: #9ca3af; font-weight: 700; letter-spacing: 0.05rem; margin-bottom: 0.25rem;">Saldo</span>
            <span style="font-size: clamp(1rem, 2vw, 1.25rem); font-weight: 800; color: #1f2937; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                R$ {{ number_format($batches->sum('remaining_amount'), 0, ',', '.') }}
            </span>
        </div>
    </div>

    {{-- Card 3: Pendentes --}}
    <div style="flex: 1 1 300px; background: #ffffff; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-bottom: 1px solid #f3f4f6; min-width: 0;">
        <div style="width: 3rem; height: 3rem; background: #faf5ff; color: #a855f7; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class='bx bx-timer' style="font-size: 1.5rem;"></i>
        </div>
        <div style="display: flex; flex-direction: column; justify-content: center; min-width: 0; flex-grow: 1;">
            <span style="font-size: 0.7rem; text-transform: uppercase; color: #9ca3af; font-weight: 700; letter-spacing: 0.05rem; margin-bottom: 0.25rem;">Pendentes</span>
            <span style="font-size: clamp(1rem, 2vw, 1.25rem); font-weight: 800; color: #1f2937; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                {{ $batches->where('status', 'pending')->count() }} un.
            </span>
        </div>
    </div>
</div>