<?php

namespace App\Services;

use App\Providers\JsonStorageService;

class ModelService extends JsonStorageService
{
    public function __construct()
    {
        parent::__construct('models');
    }

    public function switchModel($modelName)
    {
        // Stop current model
        exec('pkill ollama');
        sleep(1);

        // Start new model
        exec("ollama run $modelName > /dev/null 2>&1 &");
        sleep(2); // Give the model time to start

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
        return $current ? $current['name'] : 'llama2';
    }

    public function getInstalledModels()
    {
        exec('ollama list', $output);
        $models = [];
        foreach ($output as $line) {
            if (preg_match('/^(\S+)\s+/', $line, $matches)) {
                $models[] = $matches[1];
            }
        }
        return $models;
    }
}
