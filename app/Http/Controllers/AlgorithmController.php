<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AlgorithmController extends Controller
{

    /**
     * @return Renderable
     */
    public function index()
    {
        $urls = explode(',', Config::get('medal.urls.creator_algorithm_url'));

        return view('home', compact('urls'));
    }

    /**
     * @return Renderable
     */
    public function hidden()
    {
        $directory = Config::get('medal.storage.json_extract_dir');
        $storage_files = Storage::files($directory);
        $urls = explode(',', Config::get('medal.urls.creator_algorithm_url'));

        foreach ($storage_files as $file) {
            $id = Storage::json($file)['id'];
            $name = Storage::json($file)['name'];
            $project_name = Storage::json($file)['medal_r_json']['algorithm_name'] ?? Storage::json($file)['medal_r_json']['algorithm_name'];
            $matching_projects = array_filter(config('medal.projects'), function ($project) use ($project_name) {
                return Str::contains($project_name, $project);
            });
            $type = $matching_projects ? key($matching_projects) : 'training';
            $updated_at = date_create(Storage::json($file)['updated_at'])->format('d/m/Y h:i:s');

            $files[] = [
                'id' => $id,
                'name' => $name,
                'project_name' => $project_name,
                'type' => $type,
                'updated_at' => $updated_at,
            ];
        }


        return view('hidden-home', compact('files', 'urls'));
    }

    /**
     * @param  int  $id
     * @return Renderable
     */
    public function process(int $id, $patient_id = null)
    {
        return view('algo')->with([
            'id' => $id,
            'patient_id' => $patient_id,
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
