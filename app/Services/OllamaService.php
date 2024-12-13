<?php

namespace App\Services;

class OllamaService
{
    protected $modelService;


    protected $maxRetries = 5;
    protected $retryDelay = 3; // seconds
    protected $streamTimeout = 300; // seconds

    // Default timeouts based on model size
    protected $modelTimeouts = [
        'default' => [
            'connection' => 60,
            'response' => 180
        ],
        'large' => [
            'connection' => 90,
            'response' => 300
        ],
        'xlarge' => [
            'connection' => 120,
            'response' => 600
        ]
    ];

    // Models that need extended timeouts
    protected $largeModels = [
        '13b',
        '30b',
        '34b',
        '70b'
    ];

    public function __construct(ModelService $modelService, OllamaLoggingService $logger)
    {
        $this->modelService = $modelService;
        $this->logger = $logger;
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

    protected function makeRequest($prompt, $model) {
        $baseUrl = env('OLLAMA_BASE_URL', 'http://localhost:11434');
        $ch = curl_init($baseUrl . '/api/generate');

//        $timeouts = $this->getTimeoutsForModel($model);
        // Log the request

        $requestData = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => true
        ];

        $logFile = $this->logger->logRequest($model, $prompt, $requestData);

        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'prompt' => $prompt,
                'stream' => true
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $this->streamTimeout,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 180,
            CURLOPT_TCP_KEEPINTVL => 40,
        ]);

        // Store log filename in curl handle using custom header
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Log-File: ' . $logFile
        ]);

        return $ch;
    }

    protected function loadAndCheckModel($model)
    {
        $timeouts = $this->getTimeoutsForModel($model);

        $baseUrl = env('OLLAMA_BASE_URL', 'http://localhost:11434');
        $ch = curl_init($baseUrl . '/api/generate');
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
        $lastError = null;
        $logFile = null;

        while ($attempt < $this->maxRetries) {
            try {
                $ch = $this->makeRequest($prompt, $model);

                // Extract log filename from custom header
                $headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
                if (preg_match('/X-Log-File: (.+)/', $headers, $matches)) {
                    $logFile = $matches[1];
                }

                // Wrap the curl handle to capture and log the full response
                return $this->wrapCurlHandle($ch, $logFile);

            } catch (\Exception $e) {
                $attempt++;
                $lastError = $e;
                \Log::warning("Stream attempt $attempt failed: " . $e->getMessage());

                if ($logFile) {
                    $this->logger->logResponse($logFile, null, [
                        'attempt' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                }

                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelay);
                    $this->recoverModel($model);
                }
            }
        }

        throw new \Exception("Failed after {$this->maxRetries} attempts. Last error: " . $lastError->getMessage());
    }
    protected function wrapCurlHandle($ch, $logFile)
    {
        $fullResponse = '';

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$fullResponse, $logFile) {
            if ($jsonData = json_decode($data, true)) {
                if (isset($jsonData['response'])) {
                    $fullResponse .= $jsonData['response'];
                    // Log intermediate response
                    $this->logger->logResponse($logFile, [
                        'streaming' => true,
                        'current_response' => $fullResponse,
                        'metadata' => $jsonData // This will include any additional fields from the response
                    ]);
                }

                // Check if this is the final response
                if (isset($jsonData['done']) && $jsonData['done'] === true) {
                    // Log final complete response
                    $this->logger->logResponse($logFile, [
                        'streaming' => false,
                        'final_response' => $fullResponse,
                        'total_length' => strlen($fullResponse),
                        'completion_timestamp' => date('Y-m-d H:i:s'),
                        'metadata' => $jsonData
                    ]);
                }
            }
            return strlen($data);
        });

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
        $baseUrl = env('OLLAMA_BASE_URL', 'http://localhost:11434');
        $ch = curl_init($baseUrl . '/api/generate');
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
        $baseUrl = env('OLLAMA_BASE_URL', 'http://localhost:11434');
        $ch = curl_init($baseUrl . '/api/pull');
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
