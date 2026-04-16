<?php

namespace App\Http\Controllers\Finance\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\DailyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        return view('app.finance.analytics.index', compact(
            'collaboratorStats', 
            'systemAverage', 
            'topPerformer', 
            'activeCount',
            'distribution',
            'start',
            'end'
        ));

    }
}