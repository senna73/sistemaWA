<?php

namespace App\Http\Controllers\Finance\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\DailyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $campoData = 'start'; 
        $campoValor = 'pay_amount';

        $start = $request->filled('start_date') ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $end = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : now()->endOfMonth();

        $collaboratorStats = Collaborator::withCount(['dailyRates as total_diarias' => function($query) use ($start, $end, $campoData) {
                $query->whereBetween($campoData, [$start, $end]);
            }])
            ->withAvg(['dailyRates as media_valor' => function($query) use ($start, $end, $campoData) {
                $query->whereBetween($campoData, [$start, $end]);
            }], $campoValor)
            ->orderBy('total_diarias', 'desc')
            ->limit(10)
            ->get();

        $months = (int) $request->get('months', 1);
        $startDateChart = now()->subMonths($months)->startOfDay();
        
        $chartLabels = [];
        $chartActive = [];
        $chartInactive = [];
        $totalUsers = Collaborator::where('active', true)->count();

        $days = $startDateChart->diffInDays(now()->startOfDay()); 
        
        for ($i = $days; $i >= 0; $i--) {
            $currentDay = now()->subDays($i)->startOfDay();
            
            if ($currentDay->isFuture()) continue;

            $dayActive = DailyRate::whereDate('start', $currentDay->toDateString())
                ->where('active', true)
                ->distinct('collaborator_id')
                ->count('collaborator_id');

            $chartLabels[] = $currentDay->format('d/m');
            $chartActive[] = $dayActive;
            $chartInactive[] = max(0, $totalUsers - $dayActive);
        }


        $workedIds = DailyRate::whereBetween($campoData, [$start, $end])
            ->distinct()
            ->pluck('collaborator_id');

        $nonWorkingCollaborators = Collaborator::whereNotIn('id', $workedIds)
            ->where('active', true)
            ->with(['dailyRates' => fn($q) => $q->latest('start')])
            ->get();

        $inactiveCount = $nonWorkingCollaborators->count();

        $systemAverage = DailyRate::whereBetween($campoData, [$start, $end])->avg($campoValor);
        $activeCount = Collaborator::where('active', true)->count();
        $topPerformer = $collaboratorStats->first();

        $distribution = DB::table('daily_rate')
            ->select('collaborator_id', DB::raw('count(*) as total'))
            ->whereBetween($campoData, [$start, $end])
            ->groupBy('collaborator_id')
            ->get()
            ->groupBy('total')
            ->map(fn($group) => $group->count())
            ->sortKeys();
        $mediaAtivos = count($chartActive) > 0 ? array_sum($chartActive) / count($chartActive) : 0;
        $mediaOciosos = count($chartInactive) > 0 ? array_sum($chartInactive) / count($chartInactive) : 0;
        

        $limitDate = now()->subDays(45);
        $totalCollaborators = Collaborator::where('active', true)->count();

        $recentWorkedIds = DailyRate::where('start', '>=', $limitDate)
            ->distinct()
            ->pluck('collaborator_id');

        $countAtivos45 = $recentWorkedIds->count();
        $percentAtivos45 = $totalCollaborators > 0 ? ($countAtivos45 / $totalCollaborators) * 100 : 0;

        $countInativos45 = $totalCollaborators - $countAtivos45;
        $percentInativos45 = $totalCollaborators > 0 ? ($countInativos45 / $totalCollaborators) * 100 : 0;
        return view('app.finance.analytics.index', compact(
            'chartLabels',
            'chartActive',
            'chartInactive',
            'mediaAtivos',
            'mediaOciosos',
            'months',
            'inactiveCount',
            'activeCount',
            'systemAverage',
            'topPerformer',
            'nonWorkingCollaborators',
            'distribution',
            'start',
            'end',
            'totalCollaborators',
            'countAtivos45',
            'percentAtivos45',
            'countInativos45',
            'percentInativos45'
        ));
    }

    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '512M');
        $type = $request->get('type', 'long_term');
        $now = now();
        
        $day15 = $now->copy()->subDays(15);
        $day45 = $now->copy()->subDays(45);
        $day135 = $now->copy()->subDays(135);

        $query = Collaborator::where('active', true);

        if ($type === 'long_term') {
            $title = "Inativos há mais de 45 dias";
            $query->whereDoesntHave('dailyRates', function($q) use ($day45) {
                $q->where('start', '>=', $day45);
            });
        } 
        elseif ($type === 'new_inactive') {
            $title = "Novos Inativos (Cadastro < 135 dias)";
            $query->whereDoesntHave('dailyRates', function($q) use ($day45) {
                $q->where('start', '>=', $day45);
            })->where('created_at', '>=', $day135);
        } 
        elseif ($type === 'warning') {
            $title = "Alerta: Entre 15 e 45 dias sem atividade";
            $query->whereHas('dailyRates', function($q) use ($day45) {
                $q->where('start', '>=', $day45);
            })->whereDoesntHave('dailyRates', function($q) use ($day15) {
                $q->where('start', '>=', $day15);
            });
        }

        $results = $query->with(['dailyRates' => fn($q) => $q->latest('start')])->get();

        $data = $results->map(function ($collab) use ($now) {
            $lastWork = $collab->dailyRates->first();
            
            $rawDays = $lastWork ? $lastWork->start->diffInDays($now) : -1;

            return [
                'name'          => $collab->name,
                'created_at'    => $collab->created_at,
                'created_at_fmt'=> $collab->created_at->format('d/m/Y'), 
                'last_date'     => $lastWork ? $lastWork->start->format('d/m/Y') : 'Sem registro',
                'days_count'    => $rawDays === -1 ? 'Inatividade Total' : $rawDays,
                'raw_days'      => $rawDays
            ];
        })
        ->sortBy([
            ['raw_days', 'asc'],
            ['created_at', 'asc'],
        ]);

        $html = View::make('app.finance.analytics.inactiveCollaborators', [
            'title' => $title,
            'data'  => $data,
            'date'  => $now->format('d/m/Y H:i'),
            'user'  => Auth::user()
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream("relatorio.pdf", ['Attachment' => false]);
    }
}