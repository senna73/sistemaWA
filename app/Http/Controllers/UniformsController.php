<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\CollaboratorUniform;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;

class UniformsController extends Controller
{
    private function resolveKitType(?string $sectionName, bool $isLeader = false): string
    {
        if (!$sectionName) {
            return $isLeader ? 'Camisa Liderança' : 'Kit Padrão';
        }

        $name = mb_strtolower($sectionName);

        if ($isLeader || str_contains($name, 'líder') || str_contains($name, 'lider')) {
            return 'Camisa Liderança';
        }

        if (str_contains($name, 'açougue') || str_contains($name, 'acougue')) {
            return 'Kit Açougue';
        }

        if (str_contains($name, 'padaria')) {
            return 'Camisa Branca';
        }

        if (str_contains($name, 'reposição') || str_contains($name, 'reposicao') || 
            str_contains($name, 'caixa') || str_contains($name, 'operador')) {
            return 'Camisa Azul';
        }

        return 'Kit Padrão';
    }

    private function calculateEntitledUniforms(string $kitType, int $dailyRatesCount): int
    {
        if ($kitType === 'Kit Açougue') {
            return match (true) {
                $dailyRatesCount >= 100 => 2,
                $dailyRatesCount >= 1   => 1,
                default                 => 0,
            };
        }

        return match (true) {
            $dailyRatesCount >= 100 => 3,
            $dailyRatesCount >= 30  => 2,
            $dailyRatesCount >= 5   => 1,
            default                 => 0,
        };
    }

    /**
     * Agrupa as seções e calcula o tipo de kit avaliando o is_leader vindo das diárias.
     */
    private function getCollaboratorUniformItems(Collaborator $collab, bool $onlyActiveDailyRates = false)
    {
        $items = collect();

        if ($collab->sections->isEmpty()) {
            $dailyRatesQuery = $collab->dailyRates();
            if ($onlyActiveDailyRates) {
                $dailyRatesQuery->where('active', true);
            }
            
            $dailyRates = $dailyRatesQuery->get();
            $dailyRatesCount = $dailyRates->count();
            $lastDailyAt     = $dailyRates->max('start');

            $hasLeaderDaily = $dailyRates->contains(function ($rate) {
                return (bool) ($rate->is_leader ?? false);
            });

            $kitType = $this->resolveKitType(null, $hasLeaderDaily);

            $totalDelivered = $collab->uniforms->filter(function($u) use ($kitType) {
                return $u->type && $u->type->name === $kitType;
            })->sum('quantity');

            $entitled   = $this->calculateEntitledUniforms($kitType, $dailyRatesCount);
            $pendingQty = max(0, $entitled - $totalDelivered);

            $items->push((object) [
                'id'                 => $collab->id,
                'collab'             => $collab,
                'name'               => $collab->name,
                'uniform_size'       => $collab->uniform_size,
                'section'            => null,
                'section_name'       => 'Geral',
                'kit_type'           => $kitType,
                'daily_rates_count'  => $dailyRatesCount,
                'uniforms_entitled'  => $entitled,
                'uniforms_delivered' => $totalDelivered,
                'pending_qty'        => $pendingQty,
                'last_daily_at'      => $lastDailyAt,
            ]);

            return $items;
        }

        $allDailyRatesQuery = $collab->dailyRates();
        if ($onlyActiveDailyRates) {
            $allDailyRatesQuery->where('active', true);
        }
        $allDailyRates = $allDailyRatesQuery->get();

        $groupedSections = $collab->sections->groupBy(function ($section) use ($allDailyRates) {
            $sectionRates = $allDailyRates->where('section_id', $section->id);
            $hasLeaderDaily = $sectionRates->contains(fn($rate) => (bool) ($rate->is_leader ?? false));

            return $this->resolveKitType($section->name, $hasLeaderDaily);
        });

        foreach ($groupedSections as $kitType => $sections) {
            $sectionIds = $sections->pluck('id');

            $sectionRates = $allDailyRates->whereIn('section_id', $sectionIds);
            $dailyRatesCount = $sectionRates->count();
            $lastDailyAt     = $sectionRates->max('start');

            $totalDelivered = $collab->uniforms->filter(function($u) use ($kitType) {
                return $u->type && $u->type->name === $kitType;
            })->sum('quantity');

            $entitled   = $this->calculateEntitledUniforms($kitType, $dailyRatesCount);
            $pendingQty = max(0, $entitled - $totalDelivered);

            $items->push((object) [
                'id'                 => $collab->id,
                'collab'             => $collab,
                'name'               => $collab->name,
                'uniform_size'       => $collab->uniform_size,
                'section'            => $sections->first(),
                'section_name'       => $sections->pluck('name')->implode(', '),
                'kit_type'           => $kitType,
                'daily_rates_count'  => $dailyRatesCount,
                'uniforms_entitled'  => $entitled,
                'uniforms_delivered' => $totalDelivered,
                'pending_qty'        => $pendingQty,
                'last_daily_at'      => $lastDailyAt,
            ]);
        }

        return $items;
    }

    public function index()
    {
        $collaborators = Collaborator::with(['sections', 'uniforms.type', 'uniforms.size'])->get();

        $pending = collect();

        foreach ($collaborators as $collab) {
            $items = $this->getCollaboratorUniformItems($collab);
            foreach ($items as $item) {
                if ($item->pending_qty > 0) {
                    $pending->push($item);
                }
            }
        }

        $delivered = CollaboratorUniform::with(['collaborator', 'type', 'size'])
            ->latest('delivered_at')
            ->get();

        $totalPendentes     = $pending->count();
        $totalEntregues     = $delivered->count();
        $totalRegularizados = $delivered->pluck('collaborator_id')->unique()->count();
        $totalGeral         = $totalPendentes + $totalEntregues;

        return view('app.admin.uniforms.CollaboratorsGiveUniform', compact(
            'pending',
            'delivered',
            'totalPendentes',
            'totalEntregues',
            'totalRegularizados',
            'totalGeral'
        ));
    }

    public function deliver(Request $request, string $id)
    {
        $request->validate([
            'quantity'        => ['required', 'integer', 'min:1'],
            'uniform_type_id' => ['nullable', 'integer', 'exists:uniform_types,id'],
            'uniform_size_id' => ['nullable', 'integer'],
            'observation'     => ['nullable', 'string', 'max:255'],
        ]);

        try {
            DB::beginTransaction();

            $collaborator = Collaborator::findOrFail($id);

            $uniformTypeId = $request->uniform_type_id;

            if (!$uniformTypeId) {
                $uniformType = \App\Models\UniformType::firstOrCreate(
                    ['name' => 'Kit Padrão']
                );
                $uniformTypeId = $uniformType->id;
            }

            CollaboratorUniform::create([
                'collaborator_id' => $collaborator->id,
                'uniform_type_id' => $uniformTypeId,
                'uniform_size_id' => $request->uniform_size_id ?? $collaborator->uniform_size_id ?? null,
                'quantity'        => (int) $request->quantity,
                'delivered_at'    => Carbon::now(),
                'observation'     => $request->observation,
            ]);

            DB::commit();

            return response()->json([
                'type'    => 'success',
                'title'   => 'Sucesso!',
                'message' => 'Entrega de uniforme registrada com sucesso!'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'type'    => 'error',
                'title'   => 'Erro',
                'message' => 'Falha ao registrar entrega: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateReportPdf()
    {
        get_class($this); // Apenas mantendo padrão
        ini_set('memory_limit', '512M');

        $user = Auth::user();

        $collaborators = Collaborator::where('active', true)
            ->with(['sections', 'dailyRates' => fn($q) => $q->where('active', true), 'uniforms'])
            ->get();

        $pending = collect();

        foreach ($collaborators as $collab) {
            $items = $this->getCollaboratorUniformItems($collab, true);
            foreach ($items as $item) {
                if ($item->pending_qty > 0) {
                    $pending->push($item);
                }
            }
        }

        $pending = $pending->sortBy('name')->values();

        $html = View::make('reports.pdf-pending', [
            'pending'     => $pending,
            'user'        => $user,
            'generatedAt' => Carbon::now()->format('d/m/Y H:i')
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="relatorio_uniformes_pendentes.pdf"'
        ]);
    }
}