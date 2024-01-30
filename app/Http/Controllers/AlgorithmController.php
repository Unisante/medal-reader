<?php

namespace App\Http\Controllers;

use App\Services\FHIRService;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRPatient;
use Exception;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AlgorithmController extends Controller
{

    protected $fhirService;

    public function __construct(FHIRService $fhirService)
    {
        $this->fhirService = $fhirService;
    }


    /**
     * @return Renderable
     */
    public function index()
    {
        $directory = Config::get('medal.storage.json_extract_dir');
        $files = Storage::files($directory);
        $urls = explode(',', Config::get('medal.urls.creator_algorithm_url'));
        $patients = new FHIRPatient;
        $this->fhirService->sendToRemoteFHIRServer([]);

        return view('home', compact('files', 'urls'));
    }

    /**
     * @param  int  $id
     * @return Renderable
     */
    public function process(int $id)
    {
        return view('algo')->with([
            'id' => $id,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $extract_dir = Config::get('medal.storage.json_extract_dir');
        Storage::makeDirectory($extract_dir);
        Validator::make($request->all(), [
            'id' => 'required|integer|max:500',
            'url' => 'required|string|max:500',
        ])->validate();

        try {
            $data = Http::acceptJson()
                ->get($request->url . $request->id)
                ->throw();
        } catch (Exception $e) {
            $error['error'] = $e->getMessage();
            Log::error('Error occurred in get request', $error);
            return back()->withErrors($error);
        }

        Storage::disk('local')->put("$extract_dir/$request->id.json", $data);

        return redirect()->route('home.process', ['id' => $request->id]);
    }
}
