<?php

namespace App\Http\Controllers\Finance\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\DailyRate;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class AnalyticsController extends Controller
{
    private function applyCityFilter($query, $selectedCities)
    {
        if (!empty($selectedCities)) {
            $query->where(function($q) use ($selectedCities) {
                $cityIds = array_filter($selectedCities, fn($v) => $v !== 'null');
                
                if (!empty($cityIds)) {
                    $q->whereHas('cities', function($cityQuery) use ($cityIds) {
                        $cityQuery->whereIn('city.id', $cityIds); 
                    });
                }

                if (in_array('null', $selectedCities)) {
                    $q->orWhereDoesntHave('cities');
                }
            });
        }
        return $query;
    }

    public function index(Request $request)
    {
        $cities = City::where('active', true)->orderBy('name')->get();
        $selectedCities = $request->get('city_ids', []);

        $campoData = 'start'; 
        $campoValor = 'pay_amount';
        $start = $request->filled('start_date') ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $end = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : now()->endOfMonth();

        $baseQuery = Collaborator::where('active', true);
        $this->applyCityFilter($baseQuery, $selectedCities);
        $totalCollaborators = $baseQuery->count();

        $months = (int) $request->get('months', 1);
        
        // Se for menor que 0, não filtra por data de início (All Time)
        if ($months <= 0) {
            $startDate = DailyRate::min('start') ?? now()->subMonth();
        } else {
            $startDate = now()->subMonths($months)->startOfDay();
        }
        
        $startDateChart = now()->subMonths($months)->startOfDay();
        $chartLabels = []; $chartActive = []; $chartInactive = [];
        $days = $startDateChart->diffInDays(now()->startOfDay()); 

        for ($i = $days; $i >= 0; $i--) {
            $currentDay = now()->subDays($i)->startOfDay();
            if ($currentDay->isFuture()) continue;

            $dayActive = DailyRate::whereDate('start', $currentDay->toDateString())
                ->whereHas('collaborator', function($q) use ($selectedCities) {
                    $q->where('active', true);
                    $this->applyCityFilter($q, $selectedCities);
                })
                ->distinct('collaborator_id')
                ->count('collaborator_id');

            $chartLabels[] = $currentDay->format('d/m');
            $chartActive[] = $dayActive;
            $chartInactive[] = max(0, $totalCollaborators - $dayActive);
        }

        $limitDate = now()->subDays(45);
        $recentWorkedIds = DailyRate::where('start', '>=', $limitDate)
            ->whereHas('collaborator', function($q) use ($selectedCities) {
                $this->applyCityFilter($q, $selectedCities);
            })
            ->distinct()->pluck('collaborator_id');

        $countAtivos45 = $recentWorkedIds->count();
        $percentAtivos45 = $totalCollaborators > 0 ? ($countAtivos45 / $totalCollaborators) * 100 : 0;
        $countInativos45 = $totalCollaborators - $countAtivos45;
        $percentInativos45 = $totalCollaborators > 0 ? ($countInativos45 / $totalCollaborators) * 100 : 0;

        return view('app.finance.analytics.index', compact(
            'cities', 'selectedCities', 'chartLabels', 'chartActive', 'chartInactive',
            'months', 'totalCollaborators', 'countAtivos45', 'percentAtivos45',
            'countInativos45', 'percentInativos45', 'start', 'end'
        ));
    }

    public function exportPdf(Request $request)
    {
        $headerCityNames = !empty($selectedCities) 
            ? City::whereIn('id', array_filter($selectedCities, fn($v) => $v !== 'null'))->pluck('name')->toArray() 
            : ['Todas as cidades'];
            
        ini_set('memory_limit', '512M');
        $type = $request->get('type', 'long_term');
        $selectedCities = $request->get('city_ids', []);
        $now = now();
        
        $day15 = $now->copy()->subDays(15);
        $day45 = $now->copy()->subDays(45);
        $day135 = $now->copy()->subDays(135);

        $query = Collaborator::where('active', true);
        $this->applyCityFilter($query, $selectedCities);

        if ($type === 'long_term') {
            $title = "Inativos há mais de 45 dias";
            $query->whereDoesntHave('dailyRates', fn($q) => $q->where('start', '>=', $day45));
        } elseif ($type === 'new_inactive') {
            $title = "Novos Inativos (Cadastro < 135 dias)";
            $query->whereDoesntHave('dailyRates', fn($q) => $q->where('start', '>=', $day45))
                ->where('created_at', '>=', $day135);
        } elseif ($type === 'warning') {
            $title = "Alerta: Entre 15 e 45 dias sem atividade";
            $query->whereHas('dailyRates', fn($q) => $q->where('start', '>=', $day45))
                ->whereDoesntHave('dailyRates', fn($q) => $q->where('start', '>=', $day15));
        }

        $results = $query->with(['dailyRates' => fn($q) => $q->latest('start'), 'cities'])->get();

        $headerCityNames = !empty($selectedCities) 
            ? City::whereIn('id', array_filter($selectedCities, fn($v) => $v !== 'null'))->pluck('name')->toArray() 
            : ['Todas as cidades'];
        
        if (in_array('null', $selectedCities)) $headerCityNames[] = 'Sem Cidade';

        $data = $results->map(function ($collab) use ($now) {
                $lastWork = $collab->dailyRates->first();
                $rawDays = $lastWork ? $lastWork->start->diffInDays($now) : -1;

                return [
                    'name'           => $collab->name,
                    'mobile'         => $collab->mobile ?: 'Sem número cadastrado',
                    'city'           => $collab->cities->pluck('name')->implode(', ') ?: 'N/D',
                    'created_at_fmt' => $collab->created_at->format('d/m/Y'), 
                    'last_date'      => $lastWork ? $lastWork->start->format('d/m/Y') : 'Sem registro',
                    'days_count'     => $rawDays === -1 ? 'Inatividade Total' : $rawDays,
                    'raw_days'       => $rawDays
                ];
            })->sortBy([['raw_days', 'asc'], ['created_at', 'asc']]);

        $html = View::make('app.finance.analytics.inactiveCollaborators', [
            'title'      => $title,
            'filterCity' => implode(', ', $headerCityNames),
            'data'       => $data,
            'date'       => $now->format('d/m/Y H:i'),
            'user'       => Auth::user()
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream("relatorio_inativos.pdf", ['Attachment' => false]);
    }
}