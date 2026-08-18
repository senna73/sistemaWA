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
        if ($isLeader) {
            return 'Kit Líder';
        }

        if (!$sectionName) {
            return 'Kit Padrão';
        }

        $name = mb_strtolower($sectionName);

        if (str_contains($name, 'açougue') || str_contains($name, 'acougue')) {
            return 'Kit Açougue';
        }

        if (str_contains($name, 'padaria')) {
            return 'Kit Padaria';
        }

        if (str_contains($name, 'líder') || str_contains($name, 'lider')) {
            return 'Kit Líder';
        }

        return 'Kit Padrão';
    }

    private function calculateEntitledUniforms(string $kitType, int $dailyRatesCount): int
    {
        if ($kitType === 'Kit Açougue') {
            return match (true) {
                $dailyRatesCount >= 200 => 3,
                $dailyRatesCount >= 100  => 2,
                $dailyRatesCount >= 2   => 1,
                default                 => 0,
            };
        }

        return match (true) {
            $dailyRatesCount >= 200 => 3,
            $dailyRatesCount >= 100  => 2,
            $dailyRatesCount >= 2  => 1,
            default                 => 0,
        };
    }

    public function index()
    {
        $collaborators = Collaborator::with(['sections', 'uniforms.type', 'uniforms.size'])->get();

        $pending = collect();

        foreach ($collaborators as $collab) {
            // Calcula o total de uniformes entregues ao colaborador via histórico
            $totalDelivered = $collab->uniforms->sum('quantity');

            if ($collab->sections->isNotEmpty()) {
                foreach ($collab->sections as $section) {
                    $item = $this->buildUniformItem($collab, $section, $totalDelivered);
                    if ($item->pending_qty > 0) {
                        $pending->push($item);
                    }
                }
            } else {
                $item = $this->buildUniformItem($collab, null, $totalDelivered);
                if ($item->pending_qty > 0) {
                    $pending->push($item);
                }
            }
        }

        // Listagem da aba "Entregues": busca os registros diretos da tabela collaborator_uniforms
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

    private function buildUniformItem(Collaborator $collab, ?Section $section, int $totalDelivered)
    {
        $dailyRatesCount = $section
            ? $collab->dailyRates()->where('section_id', $section->id)->count()
            : $collab->dailyRates()->count();

        $kitType = $this->resolveKitType($section?->name, (bool) $collab->is_leader);
        $uniformsEntitled = $this->calculateEntitledUniforms($kitType, $dailyRatesCount);
        $pendingQty = max(0, $uniformsEntitled - $totalDelivered);

        return (object) [
            'collab'             => $collab,
            'section'            => $section,
            'kit_type'           => $kitType,
            'daily_rates_count'  => $dailyRatesCount,
            'uniforms_entitled'  => $uniformsEntitled,
            'uniforms_delivered' => $totalDelivered,
            'pending_qty'        => $pendingQty,
        ];
    }

    /**
     * Persiste a entrega na tabela collaborator_uniforms
     */
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

            // Caso o ID não venha do request, busca o ID do tipo padrão (ou cria se não existir)
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
        ini_set('memory_limit', '512M');

        $user = Auth::user();

        $collaborators = Collaborator::where('active', true)
            ->with(['sections', 'dailyRates' => fn($q) => $q->where('active', true), 'uniforms'])
            ->get();

        $pending = collect();

        foreach ($collaborators as $collab) {
            $totalDelivered = $collab->uniforms->sum('quantity');

            if ($collab->sections->isNotEmpty()) {
                foreach ($collab->sections as $section) {
                    $dailyRatesCount = $collab->dailyRates()->where('section_id', $section->id)->count();
                    $lastDailyAt = $collab->dailyRates()->where('section_id', $section->id)->max('start');

                    $kitType = $this->resolveKitType($section->name, (bool) $collab->is_leader);
                    $entitled = $this->calculateEntitledUniforms($kitType, $dailyRatesCount);
                    $pendingQty = max(0, $entitled - $totalDelivered);

                    if ($pendingQty > 0) {
                        $pending->push((object) [
                            'id'                 => $collab->id,
                            'name'               => $collab->name,
                            'uniform_size'       => $collab->uniform_size,
                            'kit_type'           => $kitType,
                            'section_name'       => $section->name,
                            'daily_rates_count'  => $dailyRatesCount,
                            'uniforms_entitled'  => $entitled,
                            'uniforms_delivered' => $totalDelivered,
                            'pending_qty'        => $pendingQty,
                            'last_daily_at'      => $lastDailyAt,
                        ]);
                    }
                }
            } else {
                $dailyRatesCount = $collab->dailyRates()->count();
                $lastDailyAt = $collab->dailyRates()->max('start');

                $kitType = $this->resolveKitType(null, (bool) $collab->is_leader);
                $entitled = $this->calculateEntitledUniforms($kitType, $dailyRatesCount);
                $pendingQty = max(0, $entitled - $totalDelivered);

                if ($pendingQty > 0) {
                    $pending->push((object) [
                        'id'                 => $collab->id,
                        'name'               => $collab->name,
                        'uniform_size'       => $collab->uniform_size,
                        'kit_type'           => $kitType,
                        'section_name'       => 'Geral',
                        'daily_rates_count'  => $dailyRatesCount,
                        'uniforms_entitled'  => $entitled,
                        'uniforms_delivered' => $totalDelivered,
                        'pending_qty'        => $pendingQty,
                        'last_daily_at'      => $lastDailyAt,
                    ]);
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
