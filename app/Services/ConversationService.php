<?php

namespace App\Services;

use App\Providers\JsonStorageService;

class ConversationService extends JsonStorageService
{
    public function __construct()
    {
        parent::__construct('conversations');
    }

    public function getAll()
    {
        $conversations = parent::getAll();

        // Sort conversations by the timestamp of their latest message
        usort($conversations, function($a, $b) {
            $aTimestamp = $this->getLatestTimestamp($a);
            $bTimestamp = $this->getLatestTimestamp($b);
            return $bTimestamp - $aTimestamp; // Descending order (newest first)
        });

        return $conversations;
    }

    private function getLatestTimestamp($conversation)
    {
        if (empty($conversation['messages'])) {
            return $conversation['id'] ?? 0; // Fallback to conversation ID if no messages
        }

        $lastMessage = end($conversation['messages']);
        return $lastMessage['timestamp'] ?? $conversation['id'];
    }

    // public function addMessage($conversationId, $message)
    // {
    //     $conversation = $this->get($conversationId) ?? [
    //         'id' => $conversationId,
    //         'title' => "Conversation $conversationId",
    //         'messages' => []
    //     ];

    //     $conversation['messages'][] = [
    //         'timestamp' => time(),
    //         'prompt' => $message
    //     ];

    //     return $this->save($conversationId, $conversation);
    // }

    // public function addMessage($conversationId, $message)
    // {
    //     $conversation = $this->get($conversationId) ?? [
    //         'id' => $conversationId,
    //         'title' => "Conversation $conversationId",
    //         'messages' => []
    //     ];

    //     // If this is the first message and it's from an assistant,
    //     // store it as the assistant prompt
    //     if (empty($conversation['messages']) && isset($conversation['assistant_id'])) {
    //         $conversation['assistant_prompt'] = $message;
    //         $conversation['assistant_acknowledgment'] = "Thank you for explaining how I should respond to your prompt. I will follow your instructions precisely. I am ready to begin.";
    //         return $this->save($conversationId, $conversation);
    //     }

    //     $conversation['messages'][] = [
    //         'timestamp' => time(),
    //         'prompt' => $message
    //     ];

    //     return $this->save($conversationId, $conversation);
    // }

        public function addMessage($conversationId, $message)
    {
        $conversation = $this->get($conversationId) ?? [
            'id' => $conversationId,
            'title' => "Conversation $conversationId",
            'messages' => []
        ];

        // Always add the message to messages array - no special handling needed
        $conversation['messages'][] = [
            'timestamp' => time(),
            'prompt' => $message
        ];

        return $this->save($conversationId, $conversation);
    }
    // public function updateResponse($conversationId, $response)
    // {
    //     $conversation = $this->get($conversationId);
    //     if (!$conversation || empty($conversation['messages'])) {
    //         return false;
    //     }

    //     $lastIndex = count($conversation['messages']) - 1;
    //     $conversation['messages'][$lastIndex]['response'] = $response;
    //     return $this->save($conversationId, $conversation);
    // }

    public function updateResponse($conversationId, $response)
    {
        $conversation = $this->get($conversationId);
        if (!$conversation) {
            return false;
        }

        // If we have no messages yet but have an assistant prompt,
        // we're updating the acknowledgment
        if (empty($conversation['messages']) && isset($conversation['assistant_prompt'])) {
            $conversation['assistant_acknowledgment'] = $response;
            return $this->save($conversationId, $conversation);
        }

        // Otherwise update the last message's response
        if (empty($conversation['messages'])) {
            return false;
        }

        $lastIndex = count($conversation['messages']) - 1;
        $conversation['messages'][$lastIndex]['response'] = $response;
        return $this->save($conversationId, $conversation);
    }


    // public function getContext($conversationId, $limit = 5)
    // {
    //     $conversation = $this->get($conversationId);
    //     if (!$conversation) {
    //         return '';
    //     }

    //     $messages = [];

    //     // If this is a new conversation, we want to include all messages
    //     // (assistant prompt + confirmation + context + current message)
    //     if (count($conversation['messages']) <= 3) {
    //         $messages = array_slice($conversation['messages'], 0, -1);
    //     } else {
    //         // For ongoing conversations, get the assistant prompt + last few messages
    //         $messages = array_merge(
    //         // Get the first message (assistant prompt)
    //             array_slice($conversation['messages'], 0, 1),
    //             // Get the recent context messages, excluding current message
    //             array_slice($conversation['messages'], -($limit + 1), -1)
    //         );
    //     }

    //     $context = '';
    //     foreach ($messages as $msg) {
    //         $context .= "Prompt: {$msg['prompt']}\n";
    //         if (isset($msg['response'])) {
    //             $context .= "Response: {$msg['response']}\n";
    //         }
    //     }
    //     return $context;
    // }

    public function getContext($conversationId, $limit = 5)
    {
        $conversation = $this->get($conversationId);
        if (!$conversation) {
            return '';
        }

        $context = '';

        // Add assistant prompt and acknowledgment if they exist
        if (isset($conversation['assistant_prompt'])) {
            $context .= "Prompt: {$conversation['assistant_prompt']}\n";
            if (isset($conversation['assistant_acknowledgment'])) {
                $context .= "Response: {$conversation['assistant_acknowledgment']}\n";
            }
        }

        // Add recent messages
        $recentMessages = array_slice($conversation['messages'], -$limit);
        foreach ($recentMessages as $msg) {
            $context .= "Prompt: {$msg['prompt']}\n";
            if (isset($msg['response'])) {
                $context .= "Response: {$msg['response']}\n";
            }
        }

        return $context;
    }

    public function search($query)
    {
        $conversations = $this->getAll();
        return array_filter($conversations, function($conversation) use ($query) {
            // Search in title
            if (stripos($conversation['title'], $query) !== false) {
                return true;
            }

            // Search in messages
            if (isset($conversation['messages']) && is_array($conversation['messages'])) {
                foreach ($conversation['messages'] as $message) {
                    if (stripos($message['prompt'] ?? '', $query) !== false ||
                        stripos($message['response'] ?? '', $query) !== false) {
                        return true;
                    }
                }
            }

            return false;
        });
    }
}
