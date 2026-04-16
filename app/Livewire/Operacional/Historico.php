<?php

namespace App\Livewire\Operacional;

use App\Models\Cost;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;


class Historico extends Component
{
    use WithPagination;
    public function render()
    {
        $userId = auth()->id();
        /* Testes, não to lembrando de quê, mas fica registrado
        $countShare = DB::table('cost_leader_shares')
            ->where('leader_id', $userId)
            ->where('status', 'aceito')
            ->count();

        $sql = DB::table('cost_leader_shares')
            ->join('costs', 'cost_leader_shares.cost_id', '=', 'costs.id')
            ->select('costs.id', 'cost_leader_shares.leader_id', 'cost_leader_shares.status')
            ->where('cost_leader_shares.leader_id', $userId)
            ->toSql();
        */

        $queryDireta = DB::table('costs')
            ->select('id', 'description', 'value', 'date', \DB::raw('0 as is_share'))
            ->where('leader_id', $userId);

        $queryCompartilhada = DB::table('cost_leader_shares')
            ->join('costs', 'cost_leader_shares.cost_id', '=', 'costs.id')
            ->select(
                'costs.id', 
                'costs.description', 
                'cost_leader_shares.divided_value as value', 
                'cost_leader_shares.created_at as date',
                DB::raw('1 as is_share')
            )
            ->where('cost_leader_shares.leader_id', $userId)
            ->where('cost_leader_shares.status', 'aceito');

        $historico = DB::query()
            ->fromSub($queryDireta->unionAll($queryCompartilhada), 'combined_costs')
            ->orderBy('date', 'desc')
            ->paginate(10);

        return view('livewire.operacional.historico', ['custos' => $historico]);
    }
}
