// Global variables
const DEBUG = window.DEBUG_PANEL;
let currentConversationId = null;
let currentAjaxRequest = null;


//DEBUG
function debugLog(message) {
    if (!DEBUG) return;

    const debugInfo = document.getElementById('debug-info');
    if (debugInfo) {
        const timestamp = new Date().toLocaleTimeString();
        debugInfo.innerHTML += `[${timestamp}] ${message}<br>`;
    }
    console.log(`[${new Date().toLocaleTimeString()}] ${message}`);
}

//mobile keyboard dismsisal:
function dismissMobileKeyboard() {
    // First blur any focused elements
    if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
    }

    // Force blur on the send button and message input
    $('#message-input').blur();
    $('.btn-brand').blur();

    // Remove any focus classes that might be stuck
    $('.btn-brand').removeClass('focus active');

    // Force hide keyboard on iOS
    document.documentElement.style.height = '100%';
    document.body.style.height = '100%';
    window.scrollTo(0, 0);
}


//assistants ops
window.createAssistant = function() {
    closeOffcanvas();
    stopGeneration();
    $('#assistant-id').val('');
    $('#assistant-name').val('');
    $('#assistant-prompt').val('');
    $('.modal-title').text('Create Assistant');
    $('#assistantModal').modal('show');
};

window.saveAssistant = function() {
    const id = $('#assistant-id').val();
    const name = $('#assistant-name').val();
    const prompt = $('#assistant-prompt').val();

    const url = id ? '/assistant/update' : '/assistant/create';
    const method = id ? 'PUT' : 'POST';
    const data = { name, prompt };
    if (id) data.id = id;

    $.ajax({
        url: url,
        method: method,
        data: data,
        success: function(response) {
            if (response.success) {
                const assistantModal = document.getElementById('assistantModal');
                const bsModal = bootstrap.Modal.getInstance(assistantModal);
                if (bsModal) {
                    bsModal.hide();

                    setTimeout(() => {
                        $('.modal-backdrop').remove();
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('overflow');
                        document.body.style.removeProperty('padding-right');
                    }, 300);
                }

                // Set the selected assistant ID (either from response or existing id)
                window.selectedAssistantId = response.assistant ? response.assistant.id : id;

                loadAssistants().then(() => {
                    // Scroll to the active assistant in both containers
                    $('.assistants-container').each(function() {
                        const activeAssistant = $(this).find('.assistant-item.active');
                        if (activeAssistant.length) {
                            $(this).animate({
                                scrollTop: activeAssistant.offset().top - $(this).offset().top + $(this).scrollTop()
                            }, 500);
                        }
                    });
                });

                // Show appropriate toast message
                if (!id) {
                    showToast(`Assistant "${name}" created and selected`);
                } else {
                    showToast(`Assistant "${name}" updated and selected`);
                }

                $('#message-input').focus();
            } else {
                showToast(response.error || 'Error saving assistant', 'error');
            }
        },
        error: function(xhr) {
            let errorMessage = 'Error saving assistant';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMessage = xhr.responseJSON.error;
            }
            showToast(errorMessage, 'error');
        }
    });
};

window.deleteAssistant = function(id) {
    $.ajax({
        url: `/assistant/delete/${id}`,
        method: 'POST',
        success: function(response) {
            if (response.success) {
                loadAssistants();
                // If this was the currently selected assistant, clear the selection
                if ($('.assistant-selector').val() == id) {
                    $('.assistant-selector').val('');
                }
                showToast('Assistant deleted successfully');
            } else {
                showToast('Error deleting assistant', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error deleting assistant:', error);
            showToast('Error deleting assistant', 'error');
        }
    });
};

function loadAssistants() {
    debugLog('Loading assistants...');
    return new Promise((resolve) => {
        $.post('/assistants', function(data) {
            const containers = $('.assistants-container');
            renderAssistants(data.assistants, containers);
            resolve();
        });
    });
}

function searchAssistants(query) {
    $.post('/assistant/search', { query }, function(response) {
        const containers = $('.assistants-container');
        containers.empty();

        if (!response.success) {
            containers.append($('<div>').addClass('p-3 text-muted').text('An error occurred while searching'));
            return;
        }

        // Check if we have any results
        if (!response.assistants ||
            (Array.isArray(response.assistants) && response.assistants.length === 0) ||
            (typeof response.assistants === 'object' && Object.keys(response.assistants).length === 0)) {
            containers.append($('<div>').addClass('p-3 text-muted').text('No results found'));
            return;
        }

        // handle object /array issue
        const assistants = Array.isArray(response.assistants) ?
            response.assistants :
            Object.values(response.assistants);

        renderAssistants(assistants, containers);

    }).fail(function() {
        const containers = $('.assistants-container');
        containers.empty();
        containers.append($('<div>').addClass('p-3 text-muted').text('An error occurred while searching'));
    });
}

function renderAssistants(data, containers) {
    containers.empty();

    if (data && Array.isArray(data)) {
        data.forEach(assistant => {
            const div = $('<div>')
                .addClass('assistant-item')
                .attr('data-id', assistant.id)
                .attr('title', assistant.prompt)
                .attr('data-bs-toggle', 'tooltip')
                .attr('data-bs-placement', 'bottom');

            const titleDiv = $('<div>')
                .addClass('assistant-title d-flex align-items-center justify-content-between');

            const titleSpan = $('<span>')
                .text(assistant.name)
                .addClass('assistant-name')
                .css({
                    'min-width': '100px',
                    'white-space': 'pre-wrap',
                    'word-break': 'break-word'
                });

            const btnContainer = $('<div>').addClass('d-flex gap-2');

            const editBtn = $('<button>')
                .addClass('btn btn-secondary btn-sm')
                .html('<i class="fas fa-pencil"></i>')
                .on('click', function(e) {
                    e.stopPropagation();
                    $('#assistant-id').val(assistant.id);
                    $('#assistant-name').val(assistant.name);
                    $('#assistant-prompt').val(assistant.prompt);
                    $('.modal-title').text('Edit Assistant');
                    $('#assistantModal').modal('show');
                    closeOffcanvas();
                });

            const deleteBtn = $('<button>')
                .addClass('btn btn-danger btn-sm')
                .html('<i class="fas fa-trash"></i>')
                .attr('data-delete-state', 'initial')
                .on('click', function(e) {
                    e.stopPropagation();
                    const btn = $(this);
                    const currentState = btn.attr('data-delete-state');

                    if (currentState === 'initial') {
                        btn.html('Sure?');
                        btn.attr('data-delete-state', 'confirm');

                        setTimeout(() => {
                            if (btn.attr('data-delete-state') === 'confirm') {
                                btn.html('<i class="fas fa-trash"></i>');
                                btn.attr('data-delete-state', 'initial');
                            }
                        }, 3000);
                    } else {
                        deleteAssistant(assistant.id);
                    }
                });

            btnContainer.append(editBtn, deleteBtn);
            titleDiv.append(titleSpan, btnContainer);
            div.append(titleDiv);

            if (assistant.id === window.selectedAssistantId) {
                div.addClass('active');
            }

            div.on('click', function() {
                const assistantId = $(this).data('id');
                stopGeneration();
                if (window.selectedAssistantId === assistantId) {
                    window.selectedAssistantId = null;
                    $(this).removeClass('active');
                    showToast('Assistant deselected');
                } else {
                    $('.assistant-item').removeClass('active');
                    $(this).addClass('active');
                    window.selectedAssistantId = assistantId;
                    showToast(`Assistant "${titleSpan.text()}" selected`);
                }

                closeOffcanvas();
                $('#message-input').focus();
            });

            containers.each(function() {
                $(this).append(div.clone(true));
            });
        });
    }
}



// conversation ops
window.startNewConversation = function() {
    currentConversationId = Date.now().toString();
    $('#chat-messages').empty();
    loadConversations();
    closeOffcanvas();
    stopGeneration();
    $('#message-input').focus();
};

window.sendMessage = function()
{
    if (!currentConversationId) {
        startNewConversation();
    }

    const messageInput = $('#message-input');
    const message = messageInput.val().trim();
    const assistantId = window.selectedAssistantId;

    if (!message) return;

    // Clear the input and dismiss keyboard before sending
    messageInput.val('');
    dismissMobileKeyboard();

    appendMessage(message, true);
    messageInput.val('').focus();

    const loadingIndicator = showTypingIndicator();
    let responseDiv = null;
    let fullContent = '';
    $('#stopBtn').show();

    currentAjaxRequest =  $.ajax({
        url: '/send-message',
        method: 'POST',
        data: {
            message: message,
            conversationId: currentConversationId,
            assistantId: assistantId
        },
        xhrFields: {
            onprogress: function(e) {
                const response = e.currentTarget.response;
                const newContent = response.slice(this.lastResponseLength || 0);
                this.lastResponseLength = response.length;

                newContent.split('\n').forEach(line => {
                    if (line.trim()) {
                        try {
                            const data = JSON.parse(line);

                            // Check for error message in stream
                            if (data.type === 'model_error' || (data.message && data.message.includes('Error'))) {
                                // Handle the error
                                showToast(data.details || 'Error With AI Model. Please Try Again', 'error');
                                if (loadingIndicator) {
                                    loadingIndicator.remove();
                                }
                                $('#stopBtn').hide();
                                return;
                            }

                            // Normal response handling
                            if (data.response) {
                                if (!responseDiv) {
                                    loadingIndicator.remove();
                                    responseDiv = $('<div>')
                                        .addClass('message ai-message')
                                        .appendTo('#chat-messages');
                                }
                                fullContent += data.response;
                                responseDiv.text(fullContent);
                                $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
                            }
                        } catch (error) {
                            // Ignore parsing errors for incomplete chunks
                        }
                    }
                });
            }
        },
        complete: function() {
            currentAjaxRequest = null;
            if (loadingIndicator) {
                loadingIndicator.remove();
            }
            $('#stopBtn').hide();
            $('.btn-brand').blur().removeClass('focus active');
            loadConversation(currentConversationId);
            loadConversations();

        },
        error: function(xhr, status, error) {
            console.error('Ajax error:', status, error);
            if (loadingIndicator) {
                loadingIndicator.remove();
            }
            $('#stopBtn').hide();

            try {
                const errorData = JSON.parse(xhr.responseText);
                showToast(errorData.recovery_message || 'Error With AI Model. Please Try Again', 'error');

            } catch (e) {
                showToast('Error With AI Model. Please Try Again', 'error');
            }
        }
    });
};



window.deleteConversation = function(id) {
    $.ajax({
        url: `/conversation/delete/${id}`,
        method: 'POST',
        success: function(response) {
            if (response.success) {
                if (currentConversationId === id) {
                    currentConversationId = null;
                    $('#chat-messages').empty();
                }
                loadConversations();
                showToast('Conversation deleted successfully');
            } else {
                showToast('Error deleting conversation', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error deleting conversation:', error);
            showToast('Error deleting conversation', 'error');
        }
    });
};

window.updateConversationTitle = function(id, newTitle) {
    $.post('/conversation/update-title', {
        id: id,
        title: newTitle
    }, function(response) {
        if (response.success) {
            loadConversations();
        }
    });
};

window.stopGeneration = function() {
    if (currentAjaxRequest) {
        currentAjaxRequest.abort();
        currentAjaxRequest = null;
        if (loadingIndicator) {
            loadingIndicator.remove();
        }
        $('#stopBtn').hide();
        showToast('Generation stopped');
    }
    return;
};

function loadConversations() {
    debugLog('Loading conversations...');
    $.post('/conversations', function(data) {
        const containers = $('.conversations-container');
        containers.empty();

        if (!data.conversations || !Array.isArray(data.conversations)) {
            return;
        }

        renderConversations(data.conversations, containers);
    });
}

function searchConversations(query) {
    $.post('/conversation/search', { query }, function(response) {
        const containers = $('.conversations-container');
        containers.empty();

        if (!response.success) {
            containers.append($('<div>').addClass('p-3 text-muted').text('An error occurred while searching'));
            return;
        }

        // Check if we have any results
        if (!response.conversations ||
            (Array.isArray(response.conversations) && response.conversations.length === 0) ||
            (typeof response.conversations === 'object' && Object.keys(response.conversations).length === 0)) {
            containers.append($('<div>').addClass('p-3 text-muted').text('No results found'));
            return;
        }

        // handle object/array issue
        const conversations = Array.isArray(response.conversations) ?
            response.conversations :
            Object.values(response.conversations);

        renderConversations(conversations, containers);

    }).fail(function() {
        const containers = $('.conversations-container');
        containers.empty();
        containers.append($('<div>').addClass('p-3 text-muted').text('An error occurred while searching'));
    });
}

function renderConversations(conversations, containers) {
    conversations.forEach(conv => {
        const div = $('<div>')
            .addClass('conversation-item')
            .attr('data-id', conv.id);

        const titleDiv = $('<div>')
            .addClass('conversation-title d-flex align-items-center justify-content-between');

        const titleSpan = $('<span>')
            .text(conv.title)
            .addClass('conversation-name')
            .css({
                'min-width': '100px',
                'white-space': 'pre-wrap',
                'word-break': 'break-word'
            });

        const btnContainer = $('<div>').addClass('d-flex gap-2');

        // Edit button
        const editBtn = $('<button>')
            .addClass('btn btn-secondary btn-sm')
            .html('<i class="fas fa-pencil"></i>')
            .on('click', function(e) {
                e.stopPropagation();

                const currentTitleSpan = $(this).closest('.conversation-title').find('.conversation-name');
                currentTitleSpan
                    .attr('contenteditable', 'true')
                    .focus();

                const range = document.createRange();
                const sel = window.getSelection();
                range.selectNodeContents(currentTitleSpan[0]);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);

                const saveEdit = () => {
                    const newTitle = currentTitleSpan.text().trim();
                    if (newTitle && newTitle !== conv.title) {
                        updateConversationTitle(conv.id, newTitle);
                    } else {
                        currentTitleSpan.text(conv.title);
                    }
                    currentTitleSpan.removeAttr('contenteditable');
                    currentTitleSpan.off('blur keydown');
                };

                currentTitleSpan
                    .on('blur', saveEdit)
                    .on('keydown', function(e) {
                        e.stopPropagation();
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            saveEdit();
                            currentTitleSpan.blur();
                        } else if (e.key === 'Escape') {
                            currentTitleSpan.text(conv.title);
                            currentTitleSpan.removeAttr('contenteditable');
                            currentTitleSpan.off('blur keydown');
                            currentTitleSpan.blur();
                        }
                    });
            });

        // Delete button
        const deleteBtn = $('<button>')
            .addClass('btn btn-danger btn-sm')
            .html('<i class="fas fa-trash"></i>')
            .attr('data-delete-state', 'initial')
            .on('click', function(e) {
                e.stopPropagation();
                const btn = $(this);
                const currentState = btn.attr('data-delete-state');

                if (currentState === 'initial') {
                    btn.html('Sure?');
                    btn.attr('data-delete-state', 'confirm');

                    setTimeout(() => {
                        if (btn.attr('data-delete-state') === 'confirm') {
                            btn.html('<i class="fas fa-trash"></i>');
                            btn.attr('data-delete-state', 'initial');
                        }
                    }, 3000);
                } else {
                    deleteConversation(conv.id);
                }
            });

        btnContainer.append(editBtn, deleteBtn);
        titleDiv.append(titleSpan, btnContainer);
        div.append(titleDiv);

        if (conv.id === currentConversationId) {
            div.addClass('active');
        }

        div.on('click', function(e) {
            if (!$(this).find('.conversation-name').attr('contenteditable')) {
                stopGeneration();
                closeOffcanvas();
                loadConversation(conv.id);
            }
        });

        // Append to each container (for desktop and mobile views)
        containers.each(function() {
            $(this).append(div.clone(true));
        });
    });
}

//load an individual conversation
function loadConversation(id) {
    currentConversationId = id;
    $.post('/conversation/load', { id }, function(conversation) {
        $('#chat-messages').empty();
        if (conversation && conversation.messages) {
            conversation.messages.forEach(msg => {
                appendMessage(msg.prompt, true);  // User message
                if (msg.response) {
                    appendMessage(msg.response, false);  // AI response
                }
            });
        }
        loadConversations();
        closeOffcanvas();
    });
}

//formatting and adding copy to rendered finished ai messages
function appendMessage(content, isUser) {
    const messageDiv = $('<div>')
        .addClass('message')
        .addClass(isUser ? 'user-message' : 'ai-message');

    // Add copy button container
    const copyContainer = $('<div>')
        .addClass('copy-container')
        .append(
            $('<button>')
                .addClass('copy-button')
                .html('<i class="fa-regular fa-clipboard"></i>')
                .attr('title', 'Copy message')
                .on('click', function() {
                    copyToClipboard(content, this);
                })
        );

    if (isUser) {
        messageDiv.text(content);
    } else {
        const formattedContent = parseAndFormatContent(content);
        messageDiv.html(formattedContent);

        // Add copy buttons to code blocks
        messageDiv.find('pre').each(function() {
            const preElement = $(this);
            const codeContent = preElement.find('code').text();

            const codeCopyBtn = $('<button>')
                .addClass('code-copy-button')
                .html('<i class="fa-regular fa-clipboard"></i>')
                .attr('title', 'Copy code')
                .on('click', function(e) {
                    e.stopPropagation();
                    copyToClipboard(codeContent, this);
                });

            preElement.append(codeCopyBtn);
        });

        // Initialize syntax highlighting
        messageDiv.find('pre code').each((i, el) => {
            hljs.highlightElement(el);
        });
    }

    messageDiv.append(copyContainer);
    $('#chat-messages').append(messageDiv);
    $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
}


//theme ops

function initializeTheme() {
    debugLog('Initializing theme...');
    // Check for saved theme preference or default to light
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
    updateThemeIcon(savedTheme);
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-bs-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

    document.documentElement.setAttribute('data-bs-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcon(newTheme);
}

function updateThemeIcon(theme) {
    const icon = document.querySelector('.theme-toggle i');
    icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
}

// Helper functions
function showToast(message, type = 'success') {
    // Map 'error' to Bootstrap's 'danger' class
    const bgType = type === 'error' ? 'danger' : type;

    const toast = $(`
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div class="toast align-items-center text-white bg-${bgType} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    `);

    $('body').append(toast);
    const toastEl = toast.find('.toast');
    const bsToast = new bootstrap.Toast(toastEl);
    bsToast.show();

    toastEl.on('hidden.bs.toast', function() {
        toast.remove();
    });
}

function showTypingIndicator() {
    const indicator = $(`
        <div class="message ai-message" style="padding: 0;">
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    `);
    $('#chat-messages').append(indicator);
    $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
    return indicator;
}

async function copyToClipboard(text, buttonElement) {
    try {
        // First try the modern Clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            // Fallback for non-HTTPS or browsers that don't support Clipboard API
            const textArea = document.createElement('textarea');
            textArea.value = text;

            // Make the textarea invisible
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);

            // Select and copy
            textArea.select();

            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
                throw new Error('Copy failed');
            } finally {
                document.body.removeChild(textArea);
            }
        }

        // Visual feedback
        const $button = $(buttonElement);
        const originalHtml = $button.html();

        $button.html('<i class="fas fa-check"></i>');
        $button.addClass('copied');

        setTimeout(() => {
            $button.html(originalHtml);
            $button.removeClass('copied');
        }, 2000);

    } catch (err) {
        console.error('Failed to copy:', err);
        // Show error feedback to user
        const $button = $(buttonElement);
        const originalHtml = $button.html();

        $button.html('<i class="fas fa-times"></i>');
        $button.addClass('copy-error');

        setTimeout(() => {
            $button.html(originalHtml);
            $button.removeClass('copy-error');
        }, 2000);

        // Show toast notification
        showToast('Failed to copy to clipboard', 'error');
    }
}

function parseAndFormatContent(content) {
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Check if content appears to be code
    const codePattern = /^(import|class|function|def|var|const|let|public|private|#include|package)\b/;

    if (codePattern.test(content.trim())) {
        const escapedCode = escapeHtml(content);
        return `<pre><code class="language-javascript">${escapedCode}</code></pre>`;
    }

    // Initialize Showdown converter
    const converter = new showdown.Converter({
        tables: true,
        tasklists: true,
        strikethrough: true,
        emoji: true,
        sanitize: true
    });

    try {
        return converter.makeHtml(content);
    } catch (e) {
        console.error('Markdown parsing failed:', e);
        return escapeHtml(content);
    }
}

function closeOffcanvas() {
    const toggleButton = document.querySelector('[data-bs-dismiss="offcanvas"]');
    if (toggleButton) {
        toggleButton.click();
        clearAllSearches();
    }
}

function clearSearch(container) {
    const input = container.find('input');
    input.val('');
    container.find('.search-results').hide();

    // Reload original data
    if (container.find('.assistant-search-input').length) {
        loadAssistants();
    } else {
        loadConversations();
    }

    container.removeClass('active').hide();
}

function clearAllSearches() {
    $('.search-container').each(function() {
        const container = $(this);
        const input = container.find('input');
        input.val('');
        container.find('.search-results').hide();

        // Determine which type of container and reload appropriate data
        if (container.find('.assistant-search-input').length) {
            loadAssistants();
        } else {
            loadConversations();
        }

        container.removeClass('active').hide();
    });
}


// document ready ops
$(document).ready(function() {

    debugLog('Document Ready');
    debugLog('=== PAGE LOAD ===');
    debugLog(`Window dimensions: ${window.innerWidth}x${window.innerHeight}`);
    debugLog(`User Agent: ${navigator.userAgent}`);
    debugLog(`URL: ${window.location.href}`);

    // Check if we're in a mobile browser
    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
    debugLog(`Is Mobile Browser: ${isMobile}`);

    // Check for critical resources
    debugLog(`jQuery loaded: ${typeof $ !== 'undefined'}`);
    debugLog(`Bootstrap loaded: ${typeof bootstrap !== 'undefined'}`);
    debugLog(`Showdown loaded: ${typeof showdown !== 'undefined'}`);
    debugLog(`Highlight.js loaded: ${typeof hljs !== 'undefined'}`);

    // Check for critical elements
    debugLog(`Chat messages div: ${$('#chat-messages').length > 0}`);
    debugLog(`Message input: ${$('#message-input').length > 0}`);
    debugLog(`Sidebar: ${$('.sidebar-column').length > 0}`);

    // Check CSRF token
    debugLog(`CSRF token: ${$('meta[name="csrf-token"]').length > 0}`);


    loadConversations();
    loadAssistants();
    initializeTheme();
    $('#message-input').focus();
    // Toggle search containers - delegated
    $(document).on('click', '.search-toggle', function() {
        const targetType = $(this).data('target');
        const containerClass = targetType.replace('search', 'search-container');
        const searchContainer = $(this).closest('.card').find(`.${containerClass}`);

        searchContainer.toggle();
        if (searchContainer.is(':visible')) {
            searchContainer.find('input').focus();
        } else {
            clearSearch(searchContainer);
        }
    });

    // Clear search - delegated
    $(document).on('click', '.search-clear', function() {
        const container = $(this).closest('.search-container');
        clearSearch(container);
    });

    // Handle assistant search - delegated
    $(document).on('keyup', '.assistant-search-input', function(e) {
        if (e.key === 'Enter') {
            const query = $(this).val().trim();
            if (query) {
                searchAssistants(query);
            }
        } else if (e.key === 'Escape') {
            clearSearch($(this).closest('.search-container'));
        }
    });

    // Handle conversation search - delegated
    $(document).on('keyup', '.conversation-search-input', function(e) {
        if (e.key === 'Enter') {
            const query = $(this).val().trim();
            if (query) {
                searchConversations(query);
            }
        } else if (e.key === 'Escape') {
            clearSearch($(this).closest('.search-container'));
        }
    });

    window.addEventListener('resize', function() {
        debugLog(`Window resized to: ${window.innerWidth}x${window.innerHeight}`);
    });
});

//event listeners
$('.model-selector').change(function() {
    const model = $(this).val();
    const allSelectors = $('.model-selector');

    // Keep all selectors in sync
    allSelectors.val(model);
    stopGeneration();

    $.post('/model/switch', { model }, function(response) {
        if (response.success) {
            showToast('Model switched successfully');
            closeOffcanvas();
            $('#message-input').focus();
        } else {
            showToast('Error switching model', 'error');
        }
    });
});

$('.assistant-selector').change(function() {
    const assistantId = $(this).val();
    const allSelectors = $('.assistant-selector');

    // Keep all selectors in sync
    allSelectors.val(assistantId);
    closeOffcanvas();
    stopGeneration();
    $('#message-input').focus();
});

$('#message-input').keypress(function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        $(this).blur();
        dismissMobileKeyboard();
        sendMessage();
        // Remove focus from any buttons
        $('.btn-brand').blur().removeClass('focus active');
    }
});

$('#assistantForm').on('keydown', function(e) {
    // Check if Enter was pressed and Shift wasn't held
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();  // Prevent form submission
        saveAssistant();    // Trigger save
    }
});

// Also prevent the textarea from submitting on Enter
$('#assistant-prompt').on('keydown', function(e) {
    // Only prevent if it's Enter without Shift
    // This allows Shift+Enter for new lines in the prompt
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
    }
});


$('.theme-toggle').on('click', toggleTheme);

//utilities

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

document.addEventListener('DOMContentLoaded', (event) => {

    hljs.configure({
        ignoreUnescaped: true,
        languages: ['javascript', 'php', 'python', 'html']
    });
});
