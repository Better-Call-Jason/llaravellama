<?php

namespace App\Services;

class ConversationService extends JsonStorageService
{
    public function __construct()
    {
        parent::__construct('conversations');
    }
    
    public function addMessage($conversationId, $message)
    {
        $conversation = $this->get($conversationId) ?? [
            'id' => $conversationId,
            'title' => "Conversation $conversationId",
            'messages' => []
        ];
        
        $conversation['messages'][] = [
            'timestamp' => time(),
            'prompt' => $message
        ];
        
        return $this->save($conversationId, $conversation);
    }
    
    public function updateResponse($conversationId, $response)
    {
        $conversation = $this->get($conversationId);
        if (!$conversation || empty($conversation['messages'])) {
            return false;
        }
        
        $lastIndex = count($conversation['messages']) - 1;
        $conversation['messages'][$lastIndex]['response'] = $response;
        return $this->save($conversationId, $conversation);
    }
    
    public function getContext($conversationId, $limit = 5)
    {
        $conversation = $this->get($conversationId);
        if (!$conversation) {
            return '';
        }
        
        $messages = array_slice($conversation['messages'], -$limit);
        $context = '';
        foreach ($messages as $msg) {
            $context .= "Human: {$msg['prompt']}\n";
            if (isset($msg['response'])) {
                $context .= "Assistant: {$msg['response']}\n";
            }
        }
        return $context;
    }
}