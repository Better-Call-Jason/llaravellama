@extends('layouts.app')

@section('styles')
<style>
    .message {
        margin: 10px 0;
        padding: 10px;
        border-radius: 5px;
        line-height: 1.4;
    }

    .user-message {
        background-color: var(--bs-primary-bg-subtle);
        margin-left: 20%;
        border-radius: 15px 15px 0 15px;
    }

    .ai-message {
        background-color: var(--bs-secondary-bg-subtle);
        margin-right: 20%;
        border-radius: 15px 15px 15px 0;
    }

    .conversation-item {
        padding: 10px;
        margin: 5px 0;
        cursor: pointer;
        border-radius: 5px;
        transition: background-color 0.2s ease;
    }

    .conversation-item:hover {
        background-color: var(--bs-secondary-bg-subtle);
    }

    .conversation-item.active {
        background-color: var(--bs-primary-bg-subtle);
    }

    #input-container {
        transition: transform 0.3s ease;
    }

    #input-container:focus-within {
        transform: scale(1.02);
    }

    .conversation-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }

    .typing-indicator {
        display: flex;
        gap: 4px;
        padding: 12px 16px;
        background-color: var(--bs-secondary-bg-subtle);
        border-radius: 15px 15px 15px 0;
        margin-right: 20%;
        width: fit-content;
    }

    .typing-indicator span {
        width: 8px;
        height: 8px;
        background-color: var(--bs-secondary);
        border-radius: 50%;
        animation: typing 1s infinite ease-in-out;
    }

    .typing-indicator span:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-indicator span:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }

    .message ul {
        list-style-type: disc;
        margin-left: 20px;
        margin-bottom: 10px;
    }

    .message ul ul {
        list-style-type: circle;
    }

    .message strong {
        font-weight: 600;
    }

    .message p {
        margin-bottom: 10px;
    }

    .message pre {
        background-color: #f6f8fa;
        border-radius: 6px;
        padding: 16px;
        margin: 10px 0;
        overflow-x: auto;
    }

    .message code {
        font-family: monospace;
    }

    .message {
    position: relative;
    }

    .copy-container {
        position: absolute;
        top: 8px;
        right: 8px;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .message:hover .copy-container {
        opacity: 1;
    }

    .copy-button, .code-copy-button {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 4px 8px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s ease;
    }

    .copy-button:hover, .code-copy-button:hover {
        background: #f0f0f0;
    }

    .copied {
        background: #28a745 !important;
        color: white;
        border-color: #28a745;
    }

    pre {
        position: relative;
    }

    .code-copy-button {
        position: absolute;
        top: 8px;
        right: 8px;
        opacity: 0;
    }

    pre:hover .code-copy-button {
        opacity: 1;
    }
    .message {
    position: relative;
    padding-right: 40px; /* Add padding for the copy button */
    }

    .copy-container {
        position: absolute;
        top: 8px;
        right: 8px;
        opacity: 0;
        transition: opacity 0.2s ease;
        z-index: 2; /* Ensure it's above code blocks */
    }

    /* Add spacing between copy buttons when both are present */
    .message pre {
        margin-right: 20px; /* Give space for the main copy button */
    }

    /* When code block takes full message width */
    .message:has(pre:only-child) {
        padding-right: 60px; /* Extra padding for code-only messages */
    }
    h2 b {
    color: #ff0000; /* or #dc3545 for Bootstrap's danger red */
    }
</style>
@endsection

@section('content')
<div class="container-fluid py-4">
        <!-- Logo Header -->
        <div class="row py-3">
        <div class="col-12">
            <div class="d-flex align-items-center">
                <h2 class="mb-0">
                    <b>L</b>larava<b>L</b>lama
                    <i class="fas fa-comments text-primary ms-2"></i>
                </h2>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <!-- Sidebar Column -->
        <div class="col-3">
            <!-- Model Selection -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Model Selection</h5>
                </div>
                <div class="card-body">
                    <select id="model-select" class="form-select">
                        @foreach($models as $model)
                            <option value="{{ $model }}" {{ $model === $currentModel ? 'selected' : '' }}>
                                {{ $model }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Assistant Selection -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Assistants</h5>
                    <button class="btn btn-sm btn-primary" onclick="createAssistant()">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="card-body">
                    <select id="assistant-select" class="form-select">
                        <option value="">No Assistant</option>
                        @foreach($assistants as $assistant)
                            <option value="{{ $assistant['id'] }}">{{ $assistant['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Conversations List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Conversations</h5>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <button class="btn btn-primary w-100" onclick="startNewConversation()">
                        <i class="fas fa-plus me-2"></i>New Chat
                    </button>
                    <div id="conversations" class="overflow-y-auto" style="max-height: calc(100vh - 200px);">
                        <!-- Conversations will be inserted here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Column -->
        <div class="col-9">
            <div class="card h-100">
                <div class="card-body d-flex flex-column" style="height: calc(100vh - 100px);">
                    <div id="chat-messages" class="flex-grow-1 overflow-y-auto mb-3">
                        <!-- Messages will be inserted here -->
                    </div>
                    <div id="input-container" class="mt-auto">
                        <div class="d-flex gap-2">
                            <textarea id="message-input" class="form-control" 
                                    placeholder="Type your message..." 
                                    rows="1" 
                                    style="resize: vertical; min-height: 38px; max-height: 200px;"></textarea>
                            <button class="btn btn-primary px-4" onclick="sendMessage()">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assistant Modal -->
<div class="modal fade" id="assistantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Assistant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assistant-id">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" id="assistant-name" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">System Prompt</label>
                    <textarea id="assistant-prompt" class="form-control" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveAssistant()">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    @vite(['resources/js/chat.js'])
@endsection