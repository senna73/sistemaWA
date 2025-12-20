<?php

namespace App\Http\Controllers;

use App\BlueUtils\Money;
use App\BlueUtils\Time;
use App\Http\Controllers\Controller;
use App\Models\CityHasCollaborator;
use App\Models\Collaborator;
use App\Models\Company;
use App\Models\CompanyHasCity;
use App\Models\CompanyHasSection;
use App\Models\ConfigTable;
use App\Models\DailyRate;
use App\Models\Section;
use App\Models\UserHasCompany;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Mpdf\Mpdf;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Validator;

class DailyRateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return View('app.daily-rate.index', [
            'collaborators' => Collaborator::getActive(),
            'companies' => Company::getActive()
        ]);
    }
    
    public function table(Request $request) {
        $user = Auth::user();
        //Em ordem decrescente == Mais Recente
        $query = DailyRate::where('active', true)
            ->orderBy('created_at', 'desc');

        if ($request->collaborator_id) {
            $query->whereIn('collaborator_id', $request->collaborator_id);
        }

        if ($request->company_id) {
            $query->whereIn('company_id', $request->company_id);
        }

        if ($request->start) {
            $query->where('start', '>=', $request->start);
        }

        if ($request->end) {
            $query->where('start', '<=', $request->end);
        }

        // Aplica limite, solução temporária para o delay
        $dailyRate = $query->limit(150)->get();

        
        return DataTables::of($dailyRate)
            ->addColumn('company', function ($daily) {
                return mb_strimwidth($daily->company->name ?? 'Não Informado', 0, 20, '...');
            })
            ->addColumn('section', function ($daily) {
                return mb_strimwidth($daily->section->name ?? 'Não Informado', 0, 20, '...');
            })
            ->addColumn('collaborator', function ($daily) {
                return mb_strimwidth($daily->collaborator->name ?? 'Não Informado', 0, 20, '...');
            })
            ->addColumn('start', function ($daily) {
                return $daily->start ? \Carbon\Carbon::parse($daily->start)->format('d/m/Y H:i') : 'Não Informado';
            })
            ->addColumn('end', function ($daily) {
                return $daily->end ? \Carbon\Carbon::parse($daily->end)->format('d/m/Y H:i') : 'Não Informado';
            })
                     
            ->addColumn('actions', function ($daily) {
                return '
                    <div class="demo-inline-spacing">
                        <a type="button" class="btn btn-icon btn-primary" href="'. route('daily-rate.edit', [$daily->id]) . '">
                            <span class="tf-icons bx bx-pencil"></span>
                        </a>
                        <a type="button" class="btn btn-icon btn-danger" href="#" onclick="remove(' . $daily->id . ')">
                            <span class="tf-icons bx bx-trash"></span>
                        </a>
                    </div>
                ';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $allowedCompanyIds = UserHasCompany::where('user_id', Auth::id())
            ->where('active', true)
            ->pluck('company_id');

        $companies = Company::whereIn('id', $allowedCompanyIds)
            ->where('active', true)
            ->get();

        $companyCityIds = CompanyHasCity::whereIn('company_id', $allowedCompanyIds)
            ->pluck('city_id');

        $collaboratorIds = CityHasCollaborator::whereIn('city_id', $companyCityIds)
            ->pluck('collaborator_id')
            ->unique(); 
            

        $collaborators = Collaborator::whereIn('id', $collaboratorIds)
            ->where('active', true)
            ->get();
            
        return View('app.daily-rate.edit', [
            'collaborators' => $collaborators,
            'companies' => $companies,
            'sections' => Section::all(),
            'dailyRate' => null,
            'inss_pago' => ConfigTable::getValue('inss_default'),
            'imposto_pago' => ConfigTable::getValue('tax_default'),
        ]);
    }
 
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        try {
            $requested_section = CompanyHasSection::where('company_id', $request->company_id)->where('section_id', $request->sectionSelect_id)->firstOrFail();
            
            $duplicate = DailyRate::where('collaborator_id', $request->collaborator_id)
                ->where('company_id', $request->company_id)
                ->where('active', true)
                ->where('start', $request->start)->first();
                
            if($duplicate){
                return response()->json([
                    'type' => 'error',
                    'message' => 'Já existe outro registro com o mesmo colaborador, empresa e horário.',
                ], 422);
            }

            if ($requested_section->perHour === 1 && is_null($request->end)) {
                return response()->json([
                    'type' => 'error',
                    'message' => 'Selecione um horário de saída.',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'collaborator_id' => ['required',],
                'company_id' => ['required',],
                'sectionSelect_id' => ['required',],
                'start' => ['required',],
                'end' => [ 'nullable', 'after:start'],
                ], [
                'collaborator_id.required' => 'O colaborador é obrigatório.',
                'company_id.required' => 'A empresa é obrigatória.',
                'sectionSelect_id.required' => 'O setor é obrigatório.',
                'start.required' => 'O horário de início é obrigatório.',
                'end.after' => 'O horário de saída deve ser maior que o horário de início.',
                'end.exists' => 'Insira uma saída.',
                
            ]);
                        
            if ($validator->fails()) {
                return response()->json([
                    'message' => implode("\n", $validator->errors()->all()),
                ], 422);
            }

            DB::beginTransaction();

            if ($request->company_id) {
                $company = Company::find($request->company_id);
            }

            if ($request->collaborator_id) {
                $collaborator = Collaborator::find($request->collaborator_id);
            }

            if ($request->sectionSelect_id) {
                $section = CompanyHasSection::where('company_id', $request->company_id)->where('section_id', $request->sectionSelect_id)->firstOrFail();
            }


            $inss = $request->inss_id;
            $tax = $request->imposto_id;
            if (!$company->not_flashing) {
                ConfigTable::where('id', 'inss_default')->update(['value' => $request->inss_id]);
                ConfigTable::where('id', 'tax_default')->update(['value' => $request->imposto_id]);
            } else {
                $inss = 0;
            }

            $hourlyRate = 0.00;
            if ($collaborator && $section) {
                if ($collaborator->is_leader) {
                    $hourlyRate = $section->leaderPay;
                } elseif ($collaborator->is_extra) {
                    $hourlyRate = $section->extra;
                } else {
                    $hourlyRate = $section->employeePay;
                }
            }

            DailyRate::create([
                'collaborator_id' => $request->collaborator_id,
                'section_id' => $request->sectionSelect_id,
                'company_id' => $request->company_id,
                'user_id' => $request->user_id,

                'hourly_rate' => $hourlyRate,
                
                'start' => $request->start,
                'end' => $request->end,
                'total_time' => $request->total_time,

                'leader_comission' => !empty($request->leaderComission_id) ? Money::unformat($request->leaderComission_id) : 0,
                'transportation' => !empty($request->transport_id) ? Money::unformat($request->transport_id) : 0,
                'feeding' => !empty($request->feeding_id) ? 10.00 : 0,
                'addition' => !empty($request->addition) ? Money::unformat($request->addition) : 0,
                'pay_amount' => Money::unformat($request->employee_pay_id),
                
                'inss_paid' => !empty($inss) ? Money::unformat($inss) : 0,
                'tax_paid' => !empty($tax) ? Money::unformat($tax) : 0,
                
                'earned' => Money::unformat($request->total),
                'profit' => Money::unformat($request->total_liq),

                'employee_discount' => !empty($request->employee_discount) ? Money::unformat($request->employee_discount) : 0,
                'discount_description' => $request->discount_description ?? '',

                'observation' => $request->observation,
            ]);


            DB::commit();

            return response()->json(['type' => 'success', 'message' => 'Cadastro realizado com sucesso!'], 201);
        } catch (Exception $e) {

            DB::rollBack();

            return response()->json(['type' => 'false', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return View('app.daily-rate.edit', [
            'dailyRate' => DailyRate::find($id),
            'collaborators' => Collaborator::getActive(),
            'companies' => Company::getActive(),
            'sections' => Section::all(),
            'inss_pago' => ConfigTable::getValue('inss_default'),
            'imposto_pago' => ConfigTable::getValue('tax_default'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $requested_section = CompanyHasSection::where('company_id', $request->company_id)->where('section_id', $request->sectionSelect_id)->firstOrFail();
                
        $duplicate = DailyRate::where('collaborator_id', $request->collaborator_id)
            ->where('company_id', $request->company_id)
            ->where('active', true)
            ->where('start', $request->start)
            ->where('id', '!=', $id)
            ->first();

        if ($duplicate) {
            return response()->json([
                'type' => 'error',
                'message' => 'Já existe outro registro com o mesmo colaborador, empresa e horário.',
            ], 422);
        }


        if ($requested_section->perHour === 1 && is_null($request->end)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Selecione um horário de saída.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'collaborator_id' => ['required',],
            'company_id' => ['required',],
            'sectionSelect_id' => ['required',],
            'start' => ['required',],
            'end' => [ 'nullable', 'after:start'],
            ], [
            'collaborator_id.required' => 'O colaborador é obrigatório.',
            'company_id.required' => 'A empresa é obrigatória.',
            'sectionSelect_id.required' => 'O setor é obrigatório.',
            'start.required' => 'O horário de início é obrigatório.',
            'end.after' => 'O horário de saída deve ser maior que o horário de início.',
            'end.exists' => 'Insira uma saída.',
            
        ]);

        try {
            DB::beginTransaction();

            if ($request->company_id) {
                $company = Company::find($request->company_id);
            }

            if ($request->collaborator_id) {
                $collaborator = Collaborator::find($request->collaborator_id);
            }

            if ($request->sectionSelect_id) {
                $section = CompanyHasSection::where('company_id', $request->company_id)->where('section_id', $request->sectionSelect_id)->firstOrFail();
            }

            $inss = $request->inss_id;
            $tax = $request->imposto_id;
            if (!$company->not_flashing) {
                ConfigTable::where('id', 'inss_default')->update(['value' => $request->inss_id]);
                ConfigTable::where('id', 'tax_default')->update(['value' => $request->imposto_id]);
            } else {
                $inss = 0;
            }

            $hourlyRate = 0.00;
            if ($collaborator && $section) {
                if ($collaborator->is_leader) {
                    $hourlyRate = $section->leaderPay;
                } elseif ($collaborator->is_extra) {
                    $hourlyRate = $section->extra;
                } else {
                    $hourlyRate = $section->employeePay;
                }
            }

            
            DailyRate::findOrFail($id)->update([    
                'collaborator_id' => $request->collaborator_id,
                'section_id' => $request->sectionSelect_id,
                'company_id' => $request->company_id,
                'user_id' => $request->user_id,
                
                'start' => $request->start,
                'end' => $request->end,
                'total_time' => $request->total_time,

                'hourly_rate' => $request->hourly_rate,

                'leader_comission' => !empty($request->leaderComission_id) ? Money::unformat($request->leaderComission_id) : 0,
                'transportation' => !empty($request->transport_id) ? Money::unformat($request->transport_id) : 0,
                'feeding' => !empty($request->feeding_id) ? 10.00 : 0,
                'addition' => !empty($request->addition) ? Money::unformat($request->addition) : 0,
                'pay_amount' => Money::unformat($request->employee_pay_id),
                
                'inss_paid' => !empty($inss) ? Money::unformat($inss) : 0,
                'tax_paid' => !empty($tax) ? Money::unformat($tax) : 0,
                
                'employee_discount' => !empty($request->employee_discount) ? Money::unformat($request->employee_discount) : 0,
                
                'discount_description' => $request->discount_description ?? '',
                
                'earned' => Money::unformat($request->total),
                'profit' => Money::unformat($request->total_liq),
                'observation' => $request->observation,
            ]);

            
            DB::commit();

            return response()->json(['type' => 'success', 'message' => 'Cadastro realizado com sucesso!'], 201);
        } catch (Exception $e) {

            DB::rollBack();

            return response()->json(['type' => 'false', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {

            DB::beginTransaction();

            $dailyRate = DailyRate::find($id);
            $dailyRate->active = false;
            $dailyRate->save();

            DB::commit();

            return response()->json([
                'message' => 'Estabelecimento removido com sucesso!',
            ], 201);

        } catch(Exception $exception) {

            DB::rollBack();

            return response()->json([
                'title' => 'Erro na ação',
                'message' => $exception->getMessage(),
                'type' => 'error'
            ], 500);
        }
    }

    public function getCompanySections($companyId)
    {
        $sections = CompanyHasSection::where('company_id', $companyId)->where('active', true)->get();
        
        if ($sections->isEmpty()) {
            return response()->json(['message' => 'Nenhum setor encontrado.'], 404);
        }

        return response()->json($sections);
    }

}
