<style>
    .store-board-wrap { margin: 0 -0.5rem; }
    .store-board {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        padding: 0.25rem 0.5rem 1rem;
        align-items: flex-start;
    }
    .store-board-kanban {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 1.25rem;
    }
    .store-board-kanban .store-col {
        flex: 0 0 280px;
        max-width: 280px;
        min-height: 16rem;
    }
    .store-col {
        flex: 1 1 280px;
        max-width: 320px;
        min-width: 260px;
        background: #f4f6f9;
        border: 1px solid #e8ecf1;
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        min-height: 12rem;
    }
    .store-col-head {
        padding: 0.9rem 1rem 0.75rem;
        border-bottom: 1px solid #e5e7eb;
        background: #f4f6f9;
        border-radius: 14px 14px 0 0;
    }
    .store-col-head h3 {
        font-size: 0.92rem;
        font-weight: 700;
        margin: 0;
        color: #111827;
        line-height: 1.3;
    }
    .store-col-head .city { font-size: 0.72rem; color: #6b7280; margin-top: 0.15rem; }
    .store-col-meta { display: flex; flex-direction: column; align-items: flex-start; gap: 0.35rem; margin-top: 0.65rem; }
    .store-chip {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        background: #fff;
        border: 1px solid #e5e7eb;
        color: #374151;
    }
    .store-chip.open { background: #ecfdf3; border-color: #bbf7d0; color: #166534; }
    .store-chip.opening { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
    .store-chip.occupied { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
    .store-chip.over { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
    .store-col-body { padding: 0.75rem; display: flex; flex-direction: column; gap: 0.65rem; }
    .store-card {
        display: block;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 0.8rem 0.85rem;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    a.store-card:hover,
    button.store-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        border-color: #c7d2fe;
        color: inherit;
    }
    button.store-card {
        width: 100%;
        text-align: left;
        cursor: pointer;
        font: inherit;
    }
    .store-card.open-slot { border-left: 3px solid #22c55e; }
    .store-card.opening-slot { border-left: 3px solid #f59e0b; }
    .store-card.hire-slot { border-left: 3px solid #2563eb; }
    .store-card.excess-slot { border-left: 3px solid #ef4444; }
    .duty-rh { border: 2px solid #eab308 !important; }
    .duty-gestor { border: 2px solid #dc2626 !important; }
    .store-card .store-duty {
        margin-top: 0.45rem;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .duty-rh .store-duty { color: #a16207; }
    .duty-gestor .store-duty { color: #b91c1c; }
    .duty-legend { display: flex; flex-wrap: wrap; gap: 0.75rem 1.25rem; margin: 0 0 0.85rem; font-size: 0.8rem; color: #374151; }
    .duty-swatch {
        display: inline-block;
        width: 0.85rem;
        height: 0.85rem;
        border-radius: 3px;
        margin-right: 0.35rem;
        vertical-align: -1px;
        background: #fff;
    }
    .duty-swatch.rh { border: 2px solid #eab308; }
    .duty-swatch.gestor { border: 2px solid #dc2626; }
    .store-tag {
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 0.3rem;
    }
    .store-card.open-slot .store-tag { color: #15803d; }
    .store-card.opening-slot .store-tag { color: #c2410c; }
    .store-card.hire-slot .store-tag { color: #1d4ed8; }
    .store-card.excess-slot .store-tag { color: #b91c1c; }
    .store-card h4 { font-size: 0.9rem; font-weight: 700; margin: 0 0 0.25rem; color: #111827; }
    .store-card p { margin: 0; font-size: 0.78rem; color: #6b7280; line-height: 1.4; }
    .store-card .follow {
        margin-top: 0.5rem;
        font-size: 0.72rem;
        font-weight: 700;
        color: #4f46e5;
    }
    .store-board-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.85rem;
    }
    .store-board-stats { display: flex; gap: 1rem; flex-wrap: wrap; color: #6b7280; font-size: 0.82rem; }
    .store-board-stats strong { color: #111827; }
    .store-filter { max-width: 260px; }
    .demo-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        z-index: 20000;
        display: none;
        align-items: flex-start;
        justify-content: center;
        padding: 2rem 1rem;
        overflow: auto;
    }
    .demo-overlay.is-open { display: flex; }
    .demo-overlay-panel {
        background: #fff;
        border-radius: 14px;
        width: min(1100px, 100%);
        margin: auto;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
    }
    .demo-overlay-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e5e7eb;
    }
    .demo-overlay-head h5 { margin: 0; font-size: 1.05rem; }
    .demo-overlay-body { padding: 1.25rem; }
    .demo-overlay-close {
        border: 0;
        background: transparent;
        font-size: 1.4rem;
        line-height: 1;
        color: #64748b;
        cursor: pointer;
    }
    .clinic-choices { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .clinic-choice {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin: 0;
        padding: 0.4rem 0.7rem;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        background: #fff;
        cursor: pointer;
        user-select: none;
    }
    .clinic-choice:has(input:checked) {
        border-color: #c7d2fe;
        background: #eef2ff;
    }
    .clinic-choice input { margin: 0; }
</style>
