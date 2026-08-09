<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Collaborator;
use Illuminate\Http\Request;

class UniformsController extends Controller
{
    public function index()
    {
        // Traz colaboradores ativos e faz a contagem de diárias ativas
        $collaborators = Collaborator::where('active', true)
            ->withCount(['dailyRates' => function ($q) {
                $q->where('active', true);
            }])
            ->get();

        // Calcular o direito atual a uniformes, valores hardcoded
        $collaborators = $collaborators->map(function ($collab) {
            $count = $collab->daily_rates_count;
            
            if ($count >= 100) {
                $collab->uniforms_entitled = 3;
            } elseif ($count >= 50) {
                $collab->uniforms_entitled = 2;
            } elseif ($count >= 20) {
                $collab->uniforms_entitled = 1;
            } else {
                $collab->uniforms_entitled = 0;
            }

            return $collab;
        });

        $pending = $collaborators->filter(function ($c) {
            return $c->uniforms_entitled > 0 && $c->uniforms_delivered < $c->uniforms_entitled;
        });

        $delivered = $collaborators->filter(function ($c) {
            return $c->uniforms_entitled > 0 && $c->uniforms_delivered >= $c->uniforms_entitled;
        });

        $totalEntregues = Collaborator::sum('uniforms_delivered');
        $totalPendentes = $pending->count();
        $totalRegularizados = $delivered->count();

        return view('app.admin.uniforms.CollaboratorsGiveUniform', compact(
            'pending',
            'delivered',
            'totalEntregues',
            'totalPendentes',
            'totalRegularizados'
        ));
    }
    
    public function deliver(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:3'
        ]);

        $collaborator = Collaborator::findOrFail($id);
        
        $collaborator->uniforms_delivered = $request->quantity;
        $collaborator->save();

        return response()->json([
            'type' => 'success',
            'title' => 'Sucesso!',
            'message' => 'Status de uniformes atualizado com sucesso!'
        ]);
    }

    public function deliverBatch(Request $request)
    {
        $request->validate([
            'collaborators' => 'required|array',
            'collaborators.*' => 'exists:collaborators,id'
        ]);

        $collaborators = Collaborator::whereIn('id', $request->collaborators)
            ->withCount(['dailyRates' => function ($q) {
                $q->where('active', true);
            }])
            ->get();

        foreach ($collaborators as $collab) {
            $count = $collab->daily_rates_count;
            $entitled = 0;
            if ($count >= 100) $entitled = 3;
            elseif ($count >= 50) $entitled = 2;
            elseif ($count >= 20) $entitled = 1;

            if ($entitled > 0) {
                $collab->uniforms_delivered = $entitled;
                $collab->save();
            }
        }

        return response()->json([
            'type' => 'success',
            'title' => 'Sucesso!',
            'message' => count($request->collaborators) . ' colaboradores foram regularizados!'
        ]);
    }
}
