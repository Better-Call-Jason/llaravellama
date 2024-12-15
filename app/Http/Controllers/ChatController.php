<?php

namespace App\Http\Controllers;

use App\Services\ConversationService;
use App\Services\AssistantService;
use App\Services\ModelService;
use App\Services\OllamaService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected $conversations;
    protected $assistants;
    protected $models;

    public function __construct(
        ConversationService $conversations,
        AssistantService $assistants,
        ModelService $models,
        OllamaService $ollama

    ) {
        $this->conversations = $conversations;
        $this->assistants = $assistants;
        $this->models = $models;
        $this->ollama = $ollama;
    }

    public function index()
    {
        return view('chat.index', [
            'conversations' => $this->conversations->getAll(),
            'assistants' => $this->assistants->getAll(),
            'models' => $this->models->getInstalledModels(),
            'currentModel' => $this->models->getCurrentModel()
        ]);
    }

    public function sendMessage(Request $request)
    {

        try {
            $conversationId = $request->input('conversationId', time());
            $message = $request->input('message');
            $assistantId = $request->input('assistantId');
            $conversation = $this->conversations->get($conversationId);
            if (!$conversation) {
                if (!empty($assistantId)) {
                    $assistant = $this->assistants->get($assistantId);
                }
                // Create the conversation with assistant_id and prompt first
                $conversation = [
                    'id' => $conversationId,
                    'title' => "Conversation $conversationId",
                    'assistant_id' => $assistantId,
                    'assistant_prompt' => $assistant['prompt'] ?? "You are a knowledgeable, friendly assistant",
                    'assistant_acknowledgment' => "Thank you for explaining how I should respond to your prompt. I will follow your instructions precisely. I am ready to begin.",
                    'messages' => []
                ];
                $this->conversations->save($conversationId, $conversation);
            }

//            // Save the current message
//            $this->conversations->addMessage($conversationId, $message);
//
//            $context = $this->conversations->getContext($conversationId);
//
//            $fullPrompt = $context . "Prompt: $message\nResponse:";

            $context = $this->conversations->getContext($conversationId);

            $fullPrompt = $context . "Prompt: $message\nResponse:";

            // Save the current message after preparing context
            $this->conversations->addMessage($conversationId, $message);

            $currentModel = $this->models->getCurrentModel();

            return response()->stream(function() use ($fullPrompt, $conversationId, $currentModel) {
                try {
                    $ch = $this->ollama->generateResponse($fullPrompt, $currentModel);

                    $fullResponse = '';

                    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$fullResponse, $conversationId) {
                        if ($jsonData = json_decode($data, true)) {
                            if (isset($jsonData['response'])) {
                                $fullResponse .= $jsonData['response'];
                                $this->conversations->updateResponse($conversationId, $fullResponse);
                                echo $data;
                                flush();
                                ob_flush();
                            }
                        }
                        return strlen($data);
                    });

                    $success = curl_exec($ch);
                    $error = curl_error($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if (!$success) {
                        throw new \Exception("Model response failed: " . ($error ?: 'Unknown error'));
                    }

                } catch (\Exception $e) {
                    $errorMessage = [
                        'message' => 'Model Error',
                        'details' => $e->getMessage(),
                        'type' => 'model_error',
                        'code' => $httpCode ?? 500,
                        'retryable' => true,
                        'recovery_message' => 'The AI model is experiencing issues. Please try your message again in a few moments.'
                    ];

                    echo json_encode($errorMessage);
                    flush();
                    ob_flush();
                }

            }, 200, [
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Content-Type' => 'text/event-stream',
            ]);

        } catch (\Exception $e) {
            \Log::error('ChatController error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Model Error',
                'details' => $e->getMessage(),
                'type' => 'system_error',
                'code' => 500,
                'retryable' => true,
                'recovery_message' => 'The system encountered an error. Please try again in a few moments.'
            ], 500);
        }
    }
    // Conversation Management

    public function getConversations()
    {
        return response()->json(['conversations' => $this->conversations->getAll()]);
    }

    public function loadConversation(Request $request)
    {
        try {
            $id = $request->input('id');
            $conversation = $this->conversations->get($id);

            if (!$conversation) {
                return response()->json(['error' => 'Conversation not found'], 404);
            }

            return response()->json($conversation);
        } catch (\Exception $e) {
            \Log::error('Error loading conversation: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading conversation'], 500);
        }
    }

    public function updateTitle(Request $request)
    {
        $conversation = $this->conversations->get($request->id);
        if ($conversation) {
            $conversation['title'] = $request->title;
            $success = $this->conversations->save($request->id, $conversation);
            return response()->json(['success' => $success]);
        }
        return response()->json(['success' => false]);
    }

    public function deleteConversation($id)
    {
        try {
            $success = $this->conversations->delete($id);
            return response()->json(['success' => $success]);
        } catch (\Exception $e) {
            \Log::error('Error deleting conversation: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    // Model Management
    public function switchModel(Request $request)
    {
        $success = $this->models->switchModel($request->model);
        return response()->json(['success' => $success]);
    }

    // Assistant Management
    public function updateAssistant(Request $request)
    {
        try {
            // Validate request
            if (empty($request->input('name')) || empty($request->input('prompt'))) {
                return response()->json([
                    'success' => false,
                    'error' => 'Name and prompt are required'
                ], 422);
            }

            $id = $request->input('id');
            $name = $request->input('name');
            $prompt = $request->input('prompt');

            // Check for duplicate names
            $existingAssistants = $this->assistants->getAll();
            foreach ($existingAssistants as $existing) {
                if ($existing['id'] != $id && strtolower($existing['name']) === strtolower($name)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'An assistant with this name already exists'
                    ], 422);
                }
            }

            // Get existing assistant
            $assistant = $this->assistants->get($id);
            if (!$assistant) {
                return response()->json([
                    'success' => false,
                    'error' => 'Assistant not found'
                ], 404);
            }

            // Update assistant
            $success = $this->assistants->update($id, $name, $prompt);

            $updatedAssistant = $this->assistants->get($id);

            return response()->json([
                'success' => $success,
                'assistant' => $updatedAssistant
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating assistant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while updating the assistant'
            ], 500);
        }
    }

    public function createAssistant(Request $request)
    {
        try {
            // Validate request
            if (empty($request->input('name')) || empty($request->input('prompt'))) {
                return response()->json([
                    'success' => false,
                    'error' => 'Name and prompt are required'
                ], 422);
            }

            $name = $request->input('name');
            $prompt = $request->input('prompt');

            // Check for duplicate names
            $existingAssistants = $this->assistants->getAll();
            foreach ($existingAssistants as $existing) {
                if (strtolower($existing['name']) === strtolower($name)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'An assistant with this name already exists'
                    ], 422);
                }
            }

            $assistant = $this->assistants->create($name, $prompt);

            return response()->json([
                'success' => true,
                'assistant' => $assistant
            ]);
        } catch (\Exception $e) {
            \Log::error('Error creating assistant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while creating the assistant'
            ], 500);
        }
    }

    public function deleteAssistant($id)
    {
        return response()->json([
            'success' => $this->assistants->delete($id)
        ]);
    }

    public function getAssistants()
    {
        return response()->json(['assistants' => $this->assistants->getAll()]);
    }

    public function searchConversations(Request $request)
    {
        try {
            $query = $request->input('query');
            $results = $this->conversations->search($query);

            return response()->json([
                'success' => true,
                'conversations' => $results
            ]);
        } catch (\Exception $e) {
            \Log::error('Error searching conversations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error searching conversations'
            ], 500);
        }
    }

    public function searchAssistants(Request $request)
    {
        try {
            $query = $request->input('query');
            $results = $this->assistants->search($query);

            return response()->json([
                'success' => true,
                'assistants' => $results
            ]);
        } catch (\Exception $e) {
            \Log::error('Error searching assistants: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error searching assistants'
            ], 500);
        }
    }
}
