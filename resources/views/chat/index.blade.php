@extends('layouts.app')


@section('content')
    <style>
        .card-body {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            padding: var(--card-spacing);
            max-width: 100%;
            background-color: transparent !important;
        }
        .card .card-body,
        [data-bs-theme="dark"] .card .card-body {
            background-color: transparent !important;
        }
    </style>
<!-- Main Container -->
<div class="container-fluid h-100">
    <!-- Header Row -->
    <div class="row header-row">
        <div class="col-12">
            <div class="d-flex align-items-center">
                <!-- Mobile Toggle Button -->
                <button class="btn btn-link mobile-nav-toggle me-2"
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#sidebarOffcanvas"
                        aria-controls="sidebarOffcanvas">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo/Title -->
                <h2 class="mb-0 brand-name align-items-center">
                    <span class="brand-l">L</span>larave<span class="brand-l">L</span>lama<i class="fas fa-shield-halved brand-l"></i>
                    </span>
                </h2>
                <button class="btn btn-link ms-auto theme-toggle" type="button" title="Toggle theme">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row g-3 pb-4">
        <!-- Sidebar Column - Desktop -->
        <div class="col-lg-3 sidebar-column pb-4">
            @include('chat.partials.sidebar')
        </div>

        <!-- Chat Column -->
        <div class="col-lg-9 chat-column">
            <div class="card">
                <div class="card-body">
                    <div class="chat-container">
                        <!-- Chat Messages Area -->
                        <div id="chat-messages" class="chat-messages">
                            <!-- Messages will be dynamically inserted here -->
                        </div>

                        <!-- Chat Input Area -->
                        <div class="chat-input-wrapper">
                            <div class="chat-input">
                                <div class="input-group">
                                    <div class="textarea-container">
                                        <textarea id="message-input"
                                                class="form-control"
                                                placeholder="Type your message..."
                                                rows="1"></textarea>
                                    </div>
                                    <button class="btn btn-danger" title="stop generation" onclick="stopGeneration()" id="stopBtn" style="display: none;">
                                        <i class="fa-solid fa-traffic-light"></i>
                                    </button>
                                    <button class="btn btn-brand px-4" title="send message" onclick="sendMessage()">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sidebar Offcanvas - Mobile -->
<div class="offcanvas offcanvas-start"
     tabindex="-1"
     id="sidebarOffcanvas"
     aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Menu</h5>
        <button type="button"
                class="btn-close"
                data-bs-dismiss="offcanvas"
                aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        @include('chat.partials.sidebar')
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
                <form id="assistantForm">
                    <input type="hidden" id="assistant-id">
                    <div class="mb-3">
                        <label for="assistant-name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="assistant-name" required>
                    </div>
                    <div class="mb-3">
                        <label for="assistant-prompt" class="form-label">System Prompt</label>
                        <textarea class="form-control" id="assistant-prompt" rows="4" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-brand" onclick="saveAssistant()">Save</button>
            </div>
        </div>
    </div>
</div>

@endsection

{{--@section('scripts')--}}
{{--    @vite(['resources/js/chat.js'])--}}
{{--@endsection--}}
