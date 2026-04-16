<?php

namespace App\Livewire\Operacional;

use App\Models\Collaborator;
use App\Models\Cost;
use App\Models\CostCategory;
use App\Models\User;
use App\Models\CostLeaderShare;
use App\Models\LeaderCostCenter;
use App\Services\Finance\CollaboratorWalletService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Dashboard extends Component
{
    public $selectedCostCenter; 
    public $description, $category_id, $value, $date;
    public $selectedLeaders = [];

    public $payment_method = 'pix';
    public $target_user_id;

    protected $rules = [
        'description'        => 'required|string',
        'category_id'        => 'required',
        'value'              => 'required|numeric|min:0.01',
        'date'               => 'required|date',
        'selectedCostCenter' => 'required',
        'payment_method'     => 'required|in:pix,wallet_transfer',
        'target_user_id'     => 'required_if:payment_method,wallet_transfer',
    ];

    public function mount() 
    { 
        $this->date = date('Y-m-d'); 
    }


    public function store(CollaboratorWalletService $walletService)
    {
        $this->validate();

        try {
            DB::transaction(function () use ($walletService) {
                $userId = Auth::id();

                $totalParticipants = !empty($this->selectedLeaders) ? count($this->selectedLeaders) + 1 : 1;
                $dividedValue = 0;
                $myShare = (float) $this->value;

                if ($totalParticipants > 1) {
                    $dividedValue = floor(($this->value / $totalParticipants) * 100) / 100;
                    $myShare = (float) ($this->value - ($dividedValue * count($this->selectedLeaders)));
                }

                $originCenter = LeaderCostCenter::findOrFail($this->selectedCostCenter);
                
                if ($originCenter->balance < $myShare) {
                    throw new \Exception("Saldo insuficiente no Centro de Custo. Você precisa de R$ " . number_format($myShare, 2, ',', '.') . " para cobrir sua parte.");
                }

                $cost = Cost::create([
                    'leader_id'             => $userId,
                    'category_id'           => $this->category_id,
                    'description'           => $this->description,
                    'value'                 => $this->value,
                    'date'                  => $this->date,
                    'payment_method'        => $this->payment_method,
                    'status'                => 'pending',
                    'paid_at'               => now(),
                    'collaborator_recieve_cost_id' => $this->payment_method === 'wallet_transfer' ? $this->target_user_id : null,
                    'origin_cost_center_id' => $this->selectedCostCenter, 
                ]);

                if ($totalParticipants > 1) {
                    foreach ($this->selectedLeaders as $leaderId) {
                        CostLeaderShare::create([
                            'cost_id'       => $cost->id,
                            'leader_id'     => $leaderId,
                            'divided_value' => $dividedValue,
                            'status'        => 'pendente',
                        ]);
                    }
                }

                $originCenter->decrement('balance', $myShare);

                if ($this->payment_method === 'wallet_transfer') {
                    $walletService->credit(
                        (int) $this->target_user_id,
                        (float) $this->value,
                        "Transferência: " . $this->description,
                        ['cost_id' => $cost->id, 'created_by' => $userId]
                    );
                }
            });

            $this->reset(['description', 'category_id', 'value', 'selectedLeaders', 'selectedCostCenter', 'target_user_id']);
            $this->payment_method = 'pix';
            $this->dispatch('reset-select2'); 
            session()->flash('message', 'Gasto registrado e enviado para divisão!');

        } catch (\Exception $e) {
            Log::error('Falha no store de gasto: ' . $e->getMessage());
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.operacional.dashboard', [
            'myCostCenters' => LeaderCostCenter::all(),
            'categories'    => CostCategory::all(),
            'leaders'       => User::where('id', '!=', Auth::id())->get(),
            'collaborators' => Collaborator::getActive(),
        ]);
    }
}