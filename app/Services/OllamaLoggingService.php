<?php

namespace App\Services;

class OllamaLoggingService
{
    protected $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs/ollama/');
        if (!file_exists($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    public function logRequest($model, $prompt, $requestData)
    {
        $timestamp = date('Y-m-d_H-i-s');
        $logEntry = [
            'timestamp' => $timestamp,
            'model' => $model,
            'prompt' => $prompt,
            'request' => $requestData,
            'type' => 'request'
        ];

        $filename = "{$timestamp}_{$model}_request.json";
        file_put_contents(
            $this->logPath . $filename,
            json_encode($logEntry, JSON_PRETTY_PRINT)
        );

        return $filename;
    }

    public function logResponse($filename, $response, $error = null)
    {
        $logData = json_decode(file_get_contents($this->logPath . $filename), true);
        $logData['response'] = $response;
        if ($error) {
            $logData['error'] = $error;
        }
        $logData['type'] = 'complete';

        file_put_contents(
            $this->logPath . $filename,
            json_encode($logData, JSON_PRETTY_PRINT)
        );
    }
}
