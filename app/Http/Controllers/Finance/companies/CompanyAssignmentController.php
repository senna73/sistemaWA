<?php

namespace App\Http\Controllers\Finance\companies;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\LeaderCostCenter;
use App\Models\User;
use Illuminate\Http\Request;

class CompanyAssignmentController extends Controller
{
    public function index()
    {
        $companies = Company::with('costCenter')->orderBy('name')->get();
        
        $leaders = User::orderBy('name')->get();

        return view('app.finance.admin.cost-centers.index', compact('companies', 'leaders'));
    }

    public function updateLeader(Request $request, Company $company)
    {
        if ($request->filled('leader_id')) {
            LeaderCostCenter::updateOrCreate(
                ['company_id' => $company->id], 
                [
                    'leader_id' => $request->leader_id,
                    'name'      => $company->name,
                    'balance'   => 0
                ]
            );
        } else {
            LeaderCostCenter::where('company_id', $company->id)->delete();
        }

        return back()->with('success', "Líder atualizado!");
    }
}