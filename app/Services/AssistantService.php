<?php

namespace App\Services;

use App\Providers\JsonStorageService;

class AssistantService extends JsonStorageService
{
    public function __construct()
    {
        parent::__construct('assistants');
    }

    public function getAll()
    {
        $assistants = parent::getAll();

        // Sort assistants by created_at timestamp
        usort($assistants, function($a, $b) {
            $aTimestamp = $a['created_at'] ?? 0;
            $bTimestamp = $b['created_at'] ?? 0;
            return $bTimestamp - $aTimestamp; // Descending order (newest first)
        });

        return $assistants;
    }

    public function create($name, $prompt)
    {
        // Check for duplicate names
        $existingAssistants = $this->getAll();
        foreach ($existingAssistants as $existing) {
            if (strtolower($existing['name']) === strtolower($name)) {
                throw new \Exception('An assistant with this name already exists');
            }
        }

        $id = time();
        $assistant = [
            'id' => $id,
            'name' => $name,
            'prompt' => $prompt,
            'created_at' => time()
        ];

        $this->save($id, $assistant);
        return $assistant;
    }

    public function update($id, $name, $prompt)
    {
        // Check for duplicate names, excluding current assistant
        $existingAssistants = $this->getAll();
        foreach ($existingAssistants as $existing) {
            if ($existing['id'] != $id && strtolower($existing['name']) === strtolower($name)) {
                throw new \Exception('An assistant with this name already exists');
            }
        }

        $assistant = $this->get($id);
        if (!$assistant) {
            return false;
        }

        $assistant['name'] = $name;
        $assistant['prompt'] = $prompt;
        return $this->save($id, $assistant);
    }

    public function search($query)
    {
        $assistants = $this->getAll();
        return array_filter($assistants, function($assistant) use ($query) {
            return stripos($assistant['name'], $query) !== false ||
                stripos($assistant['prompt'], $query) !== false;
        });
    }
}
