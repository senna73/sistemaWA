<?php

namespace App\Http\Controllers\finance\admin;

use App\Http\Controllers\Controller;
use App\Models\CostCategory;
use App\Models\User;
use Illuminate\Http\Request;

class LeaderCostCenterController extends Controller
{
    public function render()
    {
        return view('app.finance.admin.leader_center.index');
    }

    public function stats()
    {
        return [
            'data' => [
                'balance' => 1,
                'total'   => 2
            ],
        ];
    }

}
