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
    
    // public function sendMessage(Request $request)
    // {
    //     $conversationId = $request->input('conversationId', time());
    //     $message = $request->input('message');
    //     $assistantId = $request->input('assistantId');
        
    //     // Save the initial message
    //     $this->conversations->addMessage($conversationId, $message);
        
    //     // Get conversation context
    //     $context = $this->conversations->getContext($conversationId);
        
    //     // Add assistant context if selected
    //     if ($assistantId) {
    //         $assistant = $this->assistants->get($assistantId);
    //         if ($assistant) {
    //             $context = $assistant['prompt'] . "\n\n" . $context;
    //         }
    //     }
        
    //     $fullPrompt = $context . "Human: $message\nAssistant:";
        
    //     // Get current model
    //     $currentModel = $this->models->getCurrentModel();
        
    //     // Stream response exactly as in original script
    //     return response()->stream(function() use ($fullPrompt, $conversationId, $currentModel) {
    //         $ch = curl_init('http://localhost:11434/api/generate');
            
    //         // Collect the full response
    //         $fullResponse = '';
            
    //         curl_setopt($ch, CURLOPT_POST, 1);
    //         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    //             'model' => $currentModel,
    //             'prompt' => $fullPrompt,
    //             'stream' => true
    //         ]));
            
    //         curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
    //         curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$fullResponse, $conversationId) {
    //             if ($jsonData = json_decode($data, true)) {
    //                 if (isset($jsonData['response'])) {
    //                     $fullResponse .= $jsonData['response'];
    //                     // Update the stored conversation
    //                     $this->conversations->updateResponse($conversationId, $fullResponse);
    //                     // Echo the exact chunk for streaming
    //                     echo $data;
    //                     flush();
    //                     ob_flush();
    //                 }
    //             }
    //             return strlen($data);
    //         });
            
    //         curl_exec($ch);
    //         curl_close($ch);
    //     }, 200, [
    //         'Cache-Control' => 'no-cache',
    //         'X-Accel-Buffering' => 'no',
    //         'Content-Type' => 'text/event-stream',
    //     ]);
    // }

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
    public function createAssistant(Request $request)
    {
        $assistant = $this->assistants->create(
            $request->input('name'),
            $request->input('prompt')
        );
        return response()->json($assistant);
    }
    
    public function updateAssistant(Request $request)
    {
        $success = $this->assistants->update(
            $request->input('id'),
            $request->input('name'),
            $request->input('prompt')
        );
        return response()->json(['success' => $success]);
    }
    
    public function deleteAssistant($id)
    {
        return response()->json([
            'success' => $this->assistants->delete($id)
        ]);
    }
}