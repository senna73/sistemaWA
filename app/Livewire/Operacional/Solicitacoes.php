<?php

namespace App\Livewire\Operacional;

use App\Models\CostLeaderShare;
use App\Models\LeaderCostCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Solicitacoes extends Component
{
    public $selectedCostCenters = [];

    public function render()
    {
        $solicitacoes = CostLeaderShare::with(['cost.leader'])
            ->where('leader_id', auth()->id())
            ->where('status', 'pendente')
            ->get();

        return view('livewire.operacional.solicitacoes', [
            'solicitacoes' => $solicitacoes,
            'myCostCenters' => LeaderCostCenter::where('leader_id', auth()->id())->get()
        ]);
    }
    public function decidir($shareId, $aceitar)
    {
        
        $share = CostLeaderShare::with('cost')->findOrFail($shareId);

        if ($share->status !== 'pendente') {
            session()->flash('error', 'Esta solicitação já foi processada.');
            return;
        }

        try {
            DB::transaction(function () use ($share, $aceitar) {
                $cost = $share->cost;
                $centroOrigem = LeaderCostCenter::findOrFail($cost->origin_cost_center_id);

                if (!$aceitar) {
                    $centroOrigem->increment('balance', $share->divided_value);

                    $share->update([
                        'status' => 'rejeitado',
                        'rejected_at' => now(),
                    ]);

                    Log::info("Partilha rejeitada. Estornado R$ {$share->divided_value} para o centro {$centroOrigem->name}");
                    return;
                }

                $costCenterId = $this->selectedCostCenters[$shareId] ?? null;

                if (!$costCenterId) {
                    throw new \Exception('Selecione um centro de custo para aceitar.');
                }

                $meuCentro = LeaderCostCenter::findOrFail($costCenterId);

                if ($meuCentro->balance < $share->divided_value) {
                    throw new \Exception("Saldo insuficiente no seu centro de custo: {$meuCentro->name}");
                }
                
                $meuCentro->decrement('balance', $share->divided_value);

                $centroOrigem->increment('balance', $share->divided_value);

                $share->update([
                    'status' => 'pago',
                    'accepted_at' => now(),
                    'used_cost_center_id' => $costCenterId
                ]);
            });

            $message = $aceitar ? 'Custo aceito e saldo estornado!' : 'Solicitação rejeitada e valor estornado ao criador.';
            session()->flash('message', $message);
            
        } catch (\Exception $e) {
            Log::error("Erro ao decidir partilha: " . $e->getMessage());
            session()->flash('error', $e->getMessage());
        }
    }
}