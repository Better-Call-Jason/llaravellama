<!-- Model Selection Card -->
<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">Model Selection</h5>
    </div>
    <div class="card-body">
        <select class="form-select model-selector">
            @foreach($models as $model)
                <option value="{{ $model }}" {{ $model === $currentModel ? 'selected' : '' }}>
                    {{ $model }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<!-- Assistant Selection Card -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Assistants</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-small btn-secondary search-toggle" data-target="assistant-search">
                <i class="fas fa-search"></i>
            </button>
            <button class="btn btn-small btn-brand" title="new assistant" onclick="createAssistant()">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="search-container assistant-search-container mb-2" style="display: none;">
            <div class="input-group">
                <input type="text" class="form-control assistant-search-input" placeholder="Search assistants...">
                <button class="btn btn-outline-secondary search-clear" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="search-results mt-1" style="display: none;">
                <small class="text-muted results-count"></small>
            </div>
        </div>
        <div class="assistants-container overflow-y-auto">
            <!-- Assistants will be inserted here -->
        </div>
    </div>
</div>

<!-- Conversations Card -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Conversations</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-small btn-secondary search-toggle" data-target="conversation-search">
                <i class="fas fa-search"></i>
            </button>
            <button class="btn btn-small btn-brand" title="new chat" onclick="startNewConversation()">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>
    <div class="card-body d-flex flex-column gap-2">
        <div class="search-container conversation-search-container mb-2" style="display: none;">
            <div class="input-group">
                <input type="text" class="form-control conversation-search-input" placeholder="Search conversations...">
                <button class="btn btn-outline-secondary search-clear" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="search-results mt-1" style="display: none;">
                <small class="text-muted results-count"></small>
            </div>
        </div>
        <div class="conversations-container overflow-y-auto">
            <!-- Conversations will be inserted here -->
        </div>
    </div>
</div>
