<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index'])->name('chat.index');

// Chat functionality
Route::post('/send-message', [ChatController::class, 'sendMessage'])->name('chat.send');
Route::post('/conversations', [ChatController::class, 'getConversations'])->name('conversations.get');
Route::post('/conversation/load', [ChatController::class, 'loadConversation'])->name('conversation.load');
Route::post('/conversation/update-title', [ChatController::class, 'updateTitle'])->name('conversation.update-title');
Route::post('/conversation/delete/{id}', [ChatController::class, 'deleteConversation'])->name('conversation.delete');

// Model management
Route::post('/model/switch', [ChatController::class, 'switchModel'])->name('model.switch');

// Assistant management
Route::post('/assistant/create', [ChatController::class, 'createAssistant'])->name('assistant.create');
Route::post('/assistant/update', [ChatController::class, 'updateAssistant'])->name('assistant.update');
Route::post('/assistant/delete/{id}', [ChatController::class, 'deleteAssistant'])->name('assistant.delete');