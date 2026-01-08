<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\Cost;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\DailyRate;
use App\Models\User;
use Dompdf\Dompdf;

use Mpdf\Mpdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    
    public function registers(Request $request)
    {
        $user = Auth::user();
        
        $dailyRate = DailyRate::query()
        ->leftJoin('collaborators', 'collaborators.id', '=', 'daily_rate.collaborator_id')
        ->leftJoin('companies', 'companies.id', '=', 'daily_rate.company_id')
        ->leftJoin('sections', 'sections.id', '=', 'daily_rate.section_id')
        ->where('daily_rate.active', true)
        ->orderBy('daily_rate.company_id')
        ->orderBy('daily_rate.section_id')
        ->orderBy('daily_rate.start')
        ->select([
            'daily_rate.collaborator_id as collaborator_id',
            'daily_rate.company_id as company_id',
            'daily_rate.section_id as section_id',
            'collaborators.name as collaborators_name',
            'companies.name as company_name',
            'sections.name as section_name',
            'daily_rate.start as start',
            'daily_rate.end as end',
            'daily_rate.total_time as total_time',
            'daily_rate.pay_amount as pay_amount',
            'collaborators.pix_key as pix_key'
        ]);
        if ($request->collaborator_id) {
            $dailyRate->whereIn('daily_rate.collaborator_id', $request->collaborator_id);
        }
        
        if ($request->company_id) {
            $dailyRate->whereIn('daily_rate.company_id', $request->company_id);
        }
        
        if ($request->start) {
            $dailyRate->where('daily_rate.start', '>=', $request->start);
        }

        if ($request->end) {
            $dailyRate->where('daily_rate.start', '<=', $request->end);
        }

        $dailyRate = $dailyRate->get();
        
        $groupedDailyRates = $dailyRate->groupBy('company_id');

        $html = View::make('reports.registers-layout', ['dailyRate' => $groupedDailyRates, 'user' => $user])->render();
    
        $dompdf = new Dompdf();

        $dompdf->loadHtml($html);

        // Define o tamanho e orientação da página
        $dompdf->setPaper('A4', 'portrait');

        // Renderiza o HTML para PDF
        $dompdf->render();

        // Envia o PDF para o navegador com opção de baixar
        $dompdf->stream('arquivo.pdf', ['Attachment' => false]);

        exit();

    }

    public static function extratoFinanceiro($start, $end)
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);
    
        $dias = [];
        $totalGanhos = 0;
        $totalCustos = 0;
    
        for ($data = $start->copy(); $data->lte($end); $data->addDay()) {
    
            $dataStr = $data->toDateString();
    
            $movimentacoes = [
                'data' => $data->format('d/m/Y'),
                'itens' => [],
            ];
    
            $ganhosDia = 0;
            $custosDia = 0;
    
            $diarias = DB::table('daily_rate')
                ->whereDate('start', $dataStr)
                ->get();
    
            foreach ($diarias as $d) {
                $earned = floatval($d->earned ?? 0);
                $addition = floatval($d->addition ?? 0);
    
                if ($earned > 0) {
                    $movimentacoes['itens'][] = [
                        'nome' => 'Recebido',
                        'valor' => number_format($earned, 2, ',', '.'),
                        'descricao' => '',
                        'tipo' => 'ganho',
                    ];
                    $ganhosDia += $earned;
                }
    
                if ($addition > 0) {
                    $movimentacoes['itens'][] = [
                        'nome' => 'Adicional',
                        'valor' => number_format($addition, 2, ',', '.'),
                        'descricao' => '',
                        'tipo' => 'ganho',
                    ];
                    $ganhosDia += $addition;
                }
    
                $earnedTotal = $earned + $addition;
                $tax = $earnedTotal * (($d->tax_paid ?? 0) / 100);
                $pay = ($d->pay_amount ?? 0) - ($d->feeding ?? 0);
    
                $custos = [
                    'Alimentação' => floatval($d->feeding ?? 0),
                    'Transporte' => floatval($d->transportation ?? 0),
                    'Pagamento ao Colaborador' => floatval($pay),
                    'Comissão do Líder' => floatval($d->leader_comission ?? 0),
                    'INSS' => floatval($d->inss_paid ?? 0),
                    'Impostos' => floatval($tax),
                ];
    
                foreach ($custos as $nome => $valor) {
                    if ($valor > 0) {
                        $movimentacoes['itens'][] = [
                            'nome' => $nome,
                            'valor' => number_format($valor, 2, ',', '.'),
                            'descricao' => '',
                            'tipo' => 'custo',
                        ];
                        $custosDia += $valor;
                    }
                }
            }
    
            $custosSoltos = DB::table('costs')
                ->leftJoin('cost_categories', 'costs.category_id', '=', 'cost_categories.id')
                ->select('costs.value', 'costs.description', 'cost_categories.name as categoria')
                ->whereDate('costs.date', $dataStr)
                ->get();
    
            foreach ($custosSoltos as $c) {
                $valor = floatval($c->value);
                $movimentacoes['itens'][] = [
                    'nome' => $c->categoria ?? 'Sem Categoria',
                    'valor' => number_format($valor, 2, ',', '.'),
                    'descricao' => $c->description,
                    'tipo' => 'custo',
                ];
                $custosDia += $valor;
            }
    
            $lucroDia = $ganhosDia - $custosDia;
    
            if (count($movimentacoes['itens']) > 0) {
                $movimentacoes['total_lucro'] = number_format($lucroDia, 2, ',', '.');
                $dias[] = $movimentacoes;
            } else {
                $dias[] = [
                    'data' => $data->format('d/m/Y'),
                    'itens' => [['nome' => 'Sem movimentações', 'valor' => '', 'descricao' => '', 'tipo' => '']],
                    'total_lucro' => '0,00'
                ];
            }
    
            $totalGanhos += $ganhosDia;
            $totalCustos += $custosDia;
        }
    
        $totais = [
            'ganhos' => number_format($totalGanhos, 2, ',', '.'),
            'custos' => number_format($totalCustos, 2, ',', '.'),
            'lucro' => number_format($totalGanhos - $totalCustos, 2, ',', '.'),
        ];
    
        $periodo = $start->format('d/m/Y') . ' até ' . $end->format('d/m/Y');
    
        $html = View::make('reports.financial-layout', [
            'dias' => $dias,
            'periodo' => $periodo,
            'totais' => $totais,
        ])->render();
    
        $dompdf = new Dompdf();

        $dompdf->loadHtml($html);

        // Define o tamanho e orientação da página
        $dompdf->setPaper('A4', 'portrait');

        // Renderiza o HTML para PDF
        $dompdf->render();

        // Envia o PDF para o navegador com opção de baixar
        $dompdf->stream('arquivo.pdf', ['Attachment' => false]);

        exit();
    }
        
    

    public function dailyRates(Request $request) {
        $user = Auth::user();

        $costsQuery = Cost::with('collaborator')
            ->leftJoin('collaborators', 'collaborators.id', '=', 'costs.collaborator_recieve_cost_id')
            ->whereNotNull('collaborator_recieve_cost_id');

        if ($request->collaborator_id) {
            $costsQuery->whereIn('collaborator_recieve_cost_id', $request->collaborator_id);
        }

        if ($request->start) {
            $costsQuery->where('date', '>=', $request->start);
        }

        if ($request->end) {
            $costsQuery->where('date', '<=', $request->end);
        }

        $costs = $costsQuery
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy('collaborator_recieve_cost_id'); 


        $groupedCosts = [];

        foreach ($costs as $collaboratorId => $costItems) {
            $firstCost = $costItems->first();
            
            $groupedCosts[$collaboratorId] = [
                'collaborator_id' => $collaboratorId,
                'collaborator_name' => $firstCost->collaborator->name ?? 'Não Informado',
                'pix_key' => $firstCost->collaborator->pix_key ?? '-',
                'costs' => [],
                'total_value' => 0,
            ];

            foreach ($costItems as $cost) {
                $groupedCosts[$collaboratorId]['costs'][] = [
                    'date' => $cost->date,
                    'value' => $cost->value,
                    'description' => $cost->description,
                    'company_name' => $cost->company->name ?? '-',
                ];

                $groupedCosts[$collaboratorId]['total_value'] += $cost->value;
            }
        }

        $dailyRate = DailyRate::query()
            ->leftJoin('collaborators', 'collaborators.id', '=', 'daily_rate.collaborator_id')  // Colaborador que trabalhou na diária
            ->leftJoin('companies', 'companies.id', '=', 'daily_rate.company_id')
            ->leftJoin('sections', 'sections.id', '=', 'daily_rate.section_id')
            ->leftJoin('users', 'users.id', '=', 'daily_rate.user_id')  // Associando a tabela users ao campo user_id da daily_rate
            ->leftJoin('collaborators as user_collaborator', 'user_collaborator.id', '=', 'users.collaborator_id') // Colaborador do usuário (responsável pelo registro)
            ->where('daily_rate.active', true)
            ->select([
                'daily_rate.collaborator_id as collaborator_id', // Colaborador da diária
                'daily_rate.company_id as company_id',
                'daily_rate.section_id as section_id',
                'daily_rate.user_id as user_id',
                'collaborators.name as collaborators_name',
                'companies.name as company_name',
                'sections.name as section_name',
                'daily_rate.start as start',
                'daily_rate.end as end',
                'daily_rate.addition as addition', //VAlor de acréscimo
                'daily_rate.pay_amount as pay_amount',
                'collaborators.pix_key as pix_key',  // Chave PIX do colaborador que trabalhou
                'users.collaborator_id as user_collaborator_id',  // Colaborador do usuário que registrou
                'user_collaborator.pix_key as user_pix_key',  // Chave PIX do colaborador responsável pelo registro
                'daily_rate.leader_comission as leader_comission',
                'users.name as user_name',  // Nome do usuário que fez o registro
                'user_collaborator.pix_key as leader_pix_key' // Chave PIX do líder (usuário que registrou a diária)
            ]);
    
        if ($request->collaborator_id) {
            $dailyRate->whereIn('daily_rate.collaborator_id', $request->collaborator_id);
        }
    
        if ($request->company_id) {
            $dailyRate->whereIn('daily_rate.company_id', $request->company_id);
        }
    
        if ($request->start) {
            $dailyRate->where('daily_rate.start', '>=', $request->start);
        }

        if ($request->end) {
            $dailyRate->where('daily_rate.start', '<=', $request->end);
        }

        $leaderCommissions = (clone $dailyRate)
            ->where('collaborators.is_leader', '=', false) //  não recebe caso o colaborador que trabalhe na diária seja o próprio ou outro líder
            ->select([
                'users.name as leader_name',
                'user_collaborator.pix_key as leader_pix_key',
                DB::raw('SUM(daily_rate.leader_comission) as total_leader_comission')
            ])
            ->groupBy('daily_rate.user_id', 'user_collaborator.pix_key')
            ->orderBy('total_leader_comission', 'desc')
            ->get();

        $dailyRate = $dailyRate->get();
        $groupedData = [];
        
        foreach ($dailyRate as $rate) {
            $companyId = $rate->company_id;
            $collaboratorId = $rate->collaborator_id;
            $sectionId = $rate->section_id;
            // Agrupando por empresa
            if (!isset($groupedData[$companyId])) {
                $groupedData[$companyId] = [
                    'company_id' => $companyId,
                    'company_name' => $rate->company_name,
                    'collaborators' => [],
                ];
            }
        
            // Agrupando por colaborador dentro da empresa
            if (!isset($groupedData[$companyId]['collaborators'][$collaboratorId])) {
                $groupedData[$companyId]['collaborators'][$collaboratorId] = [
                    'collaborator_id' => $collaboratorId,
                    'collaborator_name' => $rate->collaborators_name,
                    'pix_key' => $rate->pix_key,
                    'sections' => [],
                    'total_pay' => 0, // Inicializando o total por colaborador
                ];
            }
        
            // Agrupando por setor dentro do colaborador
            if (!isset($groupedData[$companyId]['collaborators'][$collaboratorId]['sections'][$sectionId])) {
                $groupedData[$companyId]['collaborators'][$collaboratorId]['sections'][$sectionId] = [
                    'section_id' => $sectionId,
                    'section_name' => $rate->section_name,
                    'daily_rates' => [],
                ];
            }
                    
            // Adicionando a diária ao setor
            $groupedData[$companyId]['collaborators'][$collaboratorId]['sections'][$sectionId]['daily_rates'][] = [
                'start' => $rate->start,
                'end' => $rate->end,
                'pay_amount' => $rate->pay_amount,
                'leader_comission' => $rate->leader_comission,
                'user' => [
                    'user_id' => $rate->user_id,
                    'user_name' => $rate->user_name,
                    'user_pix_key' => $rate->user_pix_key,
                    'leader_pix_key' => $rate->leader_pix_key,
                ],
                'total_time' => ($rate->end)
                    ? Carbon::parse($rate->start)->diff(Carbon::parse($rate->end))->format('%Hh %Im')
                    : '0h 0m',
            ];

        
            // Somando o total de pagamentos do colaborador
            $groupedData[$companyId]['collaborators'][$collaboratorId]['total_pay'] += $rate->pay_amount;
        }
        
        // Convertendo arrays indexados para valores numéricos organizados
        $finalData = array_values($groupedData);
        foreach ($finalData as &$company) {
            $company['collaborators'] = array_values($company['collaborators']);
            foreach ($company['collaborators'] as &$collaborator) {
                $collaborator['sections'] = array_values($collaborator['sections']);
            }
        }
    
        $html = View::make('reports.daily-rate-layout', 
                                ['finalData' => $finalData,
                                 'user' => $user,
                                'leaderCommissions' => $leaderCommissions, 
                                'costs' => $groupedCosts
                                ])->render();

        $dompdf = new Dompdf();

        $dompdf->loadHtml($html);

        // Define o tamanho e orientação da página
        $dompdf->setPaper('A4', 'portrait');

        // Renderiza o HTML para PDF
        $dompdf->render();

        // Envia o PDF para o navegador com opção de baixar
        $dompdf->stream('arquivo.pdf', ['Attachment' => false]);

        exit();
    }

    public function financial(Request $request) {

        $user = Auth::user();

        $dailyRate = DailyRate::query()
        ->leftJoin('collaborators', 'collaborators.id', '=', 'daily_rate.collaborator_id')
        ->leftJoin('companies', 'companies.id', '=', 'daily_rate.company_id')
        ->leftJoin('sections', 'sections.id', '=', 'daily_rate.section_id')
        ->where('daily_rate.active', true)
        ->orderBy('daily_rate.company_id')
        ->orderBy('daily_rate.section_id')
        ->orderBy('daily_rate.collaborator_id') 
        ->select([
            'daily_rate.collaborator_id as collaborator_id',
            'daily_rate.company_id as company_id',
            'daily_rate.section_id as section_id',
            'collaborators.name as collaborators_name',
            'companies.name as companies_name',
            'sections.name as section_name',
            'daily_rate.start as start',
            'daily_rate.pay_amount as pay_amount',
            'collaborators.pix_key as pix_key'
        ]); 

            if ($request->collaborator_id) {
                $dailyRate->whereIn('daily_rate.collaborator_id', $request->collaborator_id);
            }
            
            if ($request->company_id) {
                $dailyRate->whereIn('daily_rate.company_id', $request->company_id);
            }
            
            if ($request->start) {
                $dailyRate->where('daily_rate.start', '>=', $request->start);
            }
            
            if ($request->end) {
                $dailyRate->where('daily_rate.end', '<=', $request->end);
            }


        $dailyRate = $dailyRate->get();

        $groupedDailyRates = $dailyRate->groupBy('collaborator_id');

        $html = View::make('reports.financial-layout', ['dailyRate' => $groupedDailyRates, 'user' => $user])->render();
        
        $dompdf = new Dompdf();

        $dompdf->loadHtml($html);

        // Define o tamanho e orientação da página
        $dompdf->setPaper('A4', 'portrait');

        // Renderiza o HTML para PDF
        $dompdf->render();

        // Envia o PDF para o navegador com opção de baixar
        $dompdf->stream('arquivo.pdf', ['Attachment' => false]);

        exit();
    }
}
