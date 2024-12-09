<?php

namespace App\Services;

class OllamaService
{
    protected $modelService;
    protected $maxRetries = 3;
    protected $retryDelay = 2; // seconds

    public function __construct(ModelService $modelService)
    {
        $this->modelService = $modelService;
    }

    public function generateResponse($prompt, $model)
    {
        $attempt = 0;
        while ($attempt < $this->maxRetries) {
            try {
                return $this->makeRequest($prompt, $model);
            } catch (\Exception $e) {
                $attempt++;
                \Log::error("Ollama generation attempt $attempt failed: " . $e->getMessage());

                if ($attempt < $this->maxRetries) {
                    $this->recoverModel($model);
                } else {
                    throw new \Exception("Failed to generate response after $attempt attempts");
                }
            }
        }
    }

    protected function makeRequest($prompt, $model)
    {
        //for testing model failures -- not for production
//        if ($model === 'llama3.2:latest') {
//            throw new \Exception("Simulated model failure for testing");
//        }
        $ch = curl_init('http://localhost:11434/api/generate');
        if ($ch === false) {
            throw new \Exception("Failed to initialize cURL");
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'prompt' => $prompt,
                'stream' => true
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        return $ch;
    }

    protected function recoverModel($model)
    {
        \Log::info("Attempting to recover Ollama model: $model");

        try {
            // First try to unload the model
            $this->unloadModel($model);
            sleep(1);

            // Try to load the model with a simple health check
            if (!$this->loadAndCheckModel($model)) {
                \Log::warning("Model failed health check after initial load attempt");

                // Pull the model again in case it's corrupted
                $this->pullModel($model);

                // Try loading one more time
                if (!$this->loadAndCheckModel($model)) {
                    throw new \Exception("Model recovery failed after repull");
                }
            }

            \Log::info("Recovery complete for model: $model");

        } catch (\Exception $e) {
            \Log::error("Error during model recovery: " . $e->getMessage());
            throw $e;
        }
    }

    protected function unloadModel($model)
    {
        $ch = curl_init('http://localhost:11434/api/generate');
        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'prompt' => '', // Empty prompt
                'keep_alive' => '0s' // Immediate unload
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Failed to unload model (HTTP $httpCode)");
        }
    }

    protected function pullModel($model)
    {
        $ch = curl_init('http://localhost:11434/api/pull');
        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'stream' => false
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Failed to pull model (HTTP $httpCode)");
        }
    }

    protected function loadAndCheckModel($model)
    {
        // Try to load the model with a simple health check prompt
        $ch = curl_init('http://localhost:11434/api/generate');
        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'prompt' => 'test', // Minimal prompt for health check
                'stream' => false
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check if we got a successful response
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            return isset($data['response']) && !empty($data['response']);
        }

        return false;
    }
}
