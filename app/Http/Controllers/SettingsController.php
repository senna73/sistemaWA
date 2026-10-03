<?php

namespace App\Http\Controllers;

use App\Models\ConfigTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('app.settings.index', [
            'earningsEnabled' => ConfigTable::enabled(ConfigTable::PORTAL_EARNINGS),
            'dailyRatesEnabled' => ConfigTable::enabled(ConfigTable::PORTAL_DAILY_RATES),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        ConfigTable::putBool(ConfigTable::PORTAL_EARNINGS, $request->boolean('collaborator_earnings_enabled'));
        ConfigTable::putBool(ConfigTable::PORTAL_DAILY_RATES, $request->boolean('collaborator_daily_rates_enabled'));

        return back()->with('status', 'Configurações salvas.');
    }
}
