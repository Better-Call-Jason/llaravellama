<?php

namespace App\Services;

class AssistantService extends JsonStorageService
{
    public function __construct()
    {
        parent::__construct('assistants');
    }
    
    public function create($name, $prompt)
    {
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
        $assistant = $this->get($id);
        if (!$assistant) {
            return false;
        }

        $assistant['name'] = $name;
        $assistant['prompt'] = $prompt;
        return $this->save($id, $assistant);
    }
}