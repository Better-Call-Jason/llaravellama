<?php

namespace App\Http\Controllers;

use App\Services\ConversationService;
use App\Services\AssistantService;
use App\Services\ModelService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected $conversations;
    protected $assistants;
    protected $models;
    
    public function __construct(
        ConversationService $conversations,
        AssistantService $assistants,
        ModelService $models
    ) {
        $this->conversations = $conversations;
        $this->assistants = $assistants;
        $this->models = $models;
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
        $conversationId = $request->input('conversationId', time());
        $message = $request->input('message');
        $assistantId = $request->input('assistantId');
        
        // Save the initial message
        $this->conversations->addMessage($conversationId, $message);
        
        // Get conversation context
        $context = $this->conversations->getContext($conversationId);
        
        // Add assistant context if selected
        $fullPrompt = '';
        if ($assistantId) {
            $assistant = $this->assistants->get($assistantId);
            if ($assistant) {
                // Prepend assistant's prompt as system instruction
                $fullPrompt = "System: {$assistant['prompt']}\n\n";
            }
        }
        
        // Add the conversation context and current message
        $fullPrompt .= $context . "Human: $message\nAssistant:";
        
        // Get current model
        $currentModel = $this->models->getCurrentModel();
        
        // Stream response
        return response()->stream(function() use ($fullPrompt, $conversationId, $currentModel) {
            $ch = curl_init('http://localhost:11434/api/generate');
            
            // Collect the full response
            $fullResponse = '';
            
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => $currentModel,
                'prompt' => $fullPrompt,
                'stream' => true
            ]));
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
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
            
            curl_exec($ch);
            curl_close($ch);
        }, 200, [
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Content-Type' => 'text/event-stream',
        ]);
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