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
        if (!$conversation) {
            return false;
        }

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

    public function getContext($conversationId, $limit = 10)
    {
        $conversation = $this->get($conversationId);
        if (!$conversation) {
            return '';
        }

        $context = '';

        // Always include assistant prompt and acknowledgment
        if (isset($conversation['assistant_prompt'])) {
            $context .= "Prompt: {$conversation['assistant_prompt']}\n";
            if (isset($conversation['assistant_acknowledgment'])) {
                $context .= "Response: {$conversation['assistant_acknowledgment']}\n";
            }
        }

        // Get messages BEFORE the last one (since it was just added and will be appended later)
        if (!empty($conversation['messages'])) {
            $messageCount = count($conversation['messages']);
            if ($messageCount > 1) { // Only if we have more than just the new message
                $recentMessages = array_slice($conversation['messages'], -($limit), $limit - 1);
                foreach ($recentMessages as $msg) {
                    $context .= "Prompt: {$msg['prompt']}\n";
                    if (isset($msg['response'])) {
                        $context .= "Response: {$msg['response']}\n";
                    }
                }
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
