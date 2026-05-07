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
            'end'
        ));
    }

    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '512M');
        $user = Auth::user();
        $months = (int) $request->get('months', 1);
        
        $start = now()->subMonths($months)->startOfDay();
        $end = now()->endOfDay();

        $workedIds = DailyRate::whereBetween('start', [$start, $end])
            ->where('active', true)
            ->distinct()
            ->pluck('collaborator_id');

        $nonWorking = Collaborator::whereNotIn('id', $workedIds)
            ->where('active', true)
            ->with(['dailyRates' => fn($q) => $q->latest('start')])
            ->get()
            ->map(function ($collab) {
                $lastWork = $collab->dailyRates->first();
                return [
                    'name' => $collab->name,
                    'last_date' => $lastWork ? Carbon::parse($lastWork->start)->format('d/m/Y') : 'Nunca',
                    'days_count' => $lastWork ? Carbon::parse($lastWork->start)->diffInDays(now()) : 'Inatividade Total'
                ];
            });

        $html = View::make('app.finance.analytics.inactiveCollaborators', [
            'title'  => "Relatório de Inatividade",
            'data'   => $nonWorking,
            'months' => $months,
            'user'   => $user,
            'date'   => now()->format('d/m/Y H:i')
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream("relatorio.pdf", ['Attachment' => false]);
    }
}