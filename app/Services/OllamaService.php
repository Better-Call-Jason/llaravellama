<?php

namespace App\Services;

class OllamaService
{
    protected $modelService;
    protected $maxRetries = 3;
    protected $retryDelay = 2; // seconds

    // Default timeouts based on model size
    protected $modelTimeouts = [
        'default' => [
            'connection' => 5,    // Connection timeout in seconds
            'response' => 30      // Response timeout in seconds
        ],
        'large' => [
            'connection' => 10,
            'response' => 120     // 2 minutes for larger models
        ],
        'xlarge' => [
            'connection' => 15,
            'response' => 300     // 5 minutes for very large models
        ]
    ];

    // Models that need extended timeouts
    protected $largeModels = [
        '13b',
        '30b',
        '34b',
        '70b'
    ];

    public function __construct(ModelService $modelService)
    {
        $this->modelService = $modelService;
    }

    protected function getTimeoutsForModel($model)
    {
        // Check model size by looking for common identifiers in the model name
        foreach ($this->largeModels as $size) {
            if (stripos($model, $size) !== false) {
                return $size > '30b' ? $this->modelTimeouts['xlarge'] : $this->modelTimeouts['large'];
            }
        }

        return $this->modelTimeouts['default'];
    }

    protected function makeRequest($prompt, $model)
    {
        $ch = curl_init('http://localhost:11434/api/generate');
        if ($ch === false) {
            throw new \Exception("Failed to initialize cURL");
        }

        $timeouts = $this->getTimeoutsForModel($model);

        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'prompt' => $prompt,
                'stream' => true
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $timeouts['response'],
            CURLOPT_CONNECTTIMEOUT => $timeouts['connection'],
            CURLOPT_TCP_KEEPALIVE => 1
        ]);

        return $ch;
    }

    protected function loadAndCheckModel($model)
    {
        $timeouts = $this->getTimeoutsForModel($model);

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
            CURLOPT_TIMEOUT => $timeouts['response'],
            CURLOPT_CONNECTTIMEOUT => $timeouts['connection']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            \Log::warning("Model health check failed with error: $error");
            return false;
        }

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            return isset($data['response']) && !empty($data['response']);
        }

        return false;
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

}
