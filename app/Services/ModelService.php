<?php

namespace App\Services;

use App\Providers\JsonStorageService;

class ModelService extends JsonStorageService
{

    public function __construct()
    {
        parent::__construct('models');
        $this->baseUrl = env('OLLAMA_BASE_URL', 'http://localhost:11434');
    }

    public function switchModel($modelName)
    {
        // Save current model to json
        $this->save('current', [
            'name' => $modelName,
            'last_switched' => time()
        ]);
        return true;
    }

    public function getCurrentModel()
    {
        $current = $this->get('current');
        return $current ? $current['name'] : 'llama3.2:3b';
    }

    public function getInstalledModels()
    {
        $ch = curl_init($this->baseUrl . '/api/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            return array_map(function($model) {
                return $model['name'];
            }, $data['models'] ?? []);
        }

        return [];
    }
}
