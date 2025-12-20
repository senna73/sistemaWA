<?php
namespace App\Http\Controllers;

use App\Models\AcordoValorExtra;
use App\Models\CityHasCollaborator;
use App\Models\Collaborator;
use App\Models\Company;
use App\Models\CompanyHasCity;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AcordoValorExtraController extends Controller
{
    public function index()
    {
        $companies = Company::getActive();

        return View('app.daily-rate.extra-value.index', [
            'companies' => $companies
        ]);
    }

    public function findExtraValueAgreement(Request $request, $company_id, $collaborator_id)
    {
        $company = Company::findOrFail($company_id);
        $collaborator = Collaborator::findOrFail($collaborator_id);

        if (!$company || !$collaborator) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa ou Colaborador não encontrados.'
            ], 404);
        }

        $agreement = AcordoValorExtra::where('company_id', $company_id)
                                    ->where('collaborator_id', $collaborator_id)
                                    ->where('active', true)
                                    ->first();
        Log::info($agreement);

        if (!$agreement)
        {
            return response()->json([
                'success' => false,
                'message' => 'Sem acordo registrado',
                'value'   => 0.0
            ], 200);
        }
        return response()->json([
            'success' => true,
            'value'   => $agreement->value
        ], 200);
        
        
    }

    public function list(Request $request,  $company_id)
    {
        if (!$company_id)
        {
            return response()->json([
                'success'   => false,
                'data'     => null
            ]);

        }
        if (!Company::where('id', $company_id)->first())
        {
            return response()->json([
                'success'   => false,
                'data'      => null,
                'message'   => 'Essa empresa não existe!'
            ]);

        }
        $acordos = AcordoValorExtra::where('active', true)
                                    ->where('company_id', $company_id)
                                    ->with([
                                        'collaborator' => function ($query) {
                                            $query->select('id', 'name'); 
                                        },
                                        'company' => function ($query) {
                                            $query->select('id', 'name'); 
                                        }
                                    ])->get();
        
        $city_id  = CompanyHasCity::where('company_id', $company_id)->select('city_id');
        
        if (is_null($city_id)) {
            $colaborators_data = collect();
        }else {
            $excludedCollaboratorIds = $acordos->pluck('collaborator_id')->toArray();

            $collaboratorIds = CityHasCollaborator::where('city_id', $city_id)
                ->pluck('collaborator_id');

            $collaborators_data = Collaborator::whereIn('id', $collaboratorIds)
                        ->whereNotIn('id', $excludedCollaboratorIds) 
                        ->select('id', 'name')
                        ->get();

                Log::info($collaborators_data);
        }

        return response()->json([
            'success'   => true,
            'data'      =>  $acordos,
            'collaborators' => $collaborators_data
        ]);
        
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
                'company_id'      => 'required|integer|exists:companies,id',
                'collaborator_id' => 'required|integer|exists:collaborators,id',
                'value'           => 'required|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação.',
                'errors'  => $validator->errors()
            ], 422); 
        }

        try {
            $value = $request->input('value');
            
            $acordo = AcordoValorExtra::create([
                'company_id'    => $request->input('company_id'),
                'collaborator_id'    => $request->input('collaborator_id'),
                'value'    => (float)$value,
            ]);

            return response()->json([
                        'success' => true,
                        'message' => 'Acordo criado com sucesso!',
                        'data'    => $acordo
                    ], 201);

        } catch (\Exception $error) {
            return response()->json([
                'success'       => false,
                'message'       => 'Erro ao salvar acordo no banco de dados',
                'error_detail'  => $error->getMessage()
            ], 500);
        }
    }


    public function delete(Request $request, $agreement_id)
    {
        if (!$agreement_id || !is_numeric($agreement_id)) {
            return response()->json([
                'success' => false,
                'message' => 'ID do acordo inválido.'
            ], 400); 
        }

        try {
            $acordo = AcordoValorExtra::findOrFail($agreement_id);
            
            $acordo->update(['active' => false]);
            
            return response()->json([
                'success' => true,
                'message' => 'Acordo desativado com sucesso (active = false).'
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Acordo de valor extra não encontrado.'
            ], 404);
            
        } catch (\Exception $error) {
            return response()->json([
                'success'      => false,
                'message'      => 'Erro interno ao desativar o acordo.',
                'error_detail' => $error->getMessage()
            ], 500); 
        }
    }

    public function update()
    {
        
    }
}