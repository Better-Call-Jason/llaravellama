<?php

namespace App\Services;

class JsonStorageService
{
    protected $basePath;
    
    public function __construct($directory)
    {
        $this->basePath = storage_path("app/data/$directory");
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }
    
    public function save($id, $data)
    {
        $filepath = $this->getFilePath($id);
        return file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public function get($id)
    {
        $filepath = $this->getFilePath($id);
        return file_exists($filepath) ? json_decode(file_get_contents($filepath), true) : null;
    }
    
    public function getAll()
    {
        $items = [];
        foreach (glob("{$this->basePath}/*.json") as $file) {
            $item = json_decode(file_get_contents($file), true);
            if ($item) {
                $items[] = $item;
            }
        }
        return array_values($items);
    }
    
    public function delete($id)
    {
        $filepath = $this->getFilePath($id);
        return file_exists($filepath) && unlink($filepath);
    }
    
    protected function getFilePath($id)
    {
        return "{$this->basePath}/{$id}.json";
    }
}