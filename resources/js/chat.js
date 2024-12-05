// Global variables
let currentConversationId = null;

// Global function declarations
window.createAssistant = function() {
    $('#assistant-id').val('');
    $('#assistant-name').val('');
    $('#assistant-prompt').val('');
    $('#assistantModal').modal('show');
};

window.saveAssistant = function() {
    const id = $('#assistant-id').val();
    const name = $('#assistant-name').val();
    const prompt = $('#assistant-prompt').val();
    
    const url = id ? '/assistant/update' : '/assistant/create';
    const data = id ? { id, name, prompt } : { name, prompt };
    
    $.post(url, data, function(response) {
        $('#assistantModal').modal('hide');
        location.reload();
    });
};

window.startNewConversation = function() {
    currentConversationId = Date.now().toString();
    $('#chat-messages').empty();
    loadConversations();
};

window.sendMessage = function() {
    if (!currentConversationId) {
        startNewConversation();
    }
    
    const messageInput = $('#message-input');
    const message = messageInput.val().trim();
    const assistantId = $('#assistant-select').val();
    
    if (!message) return;
    
    appendMessage(message, true);
    messageInput.val('').focus();

    const loadingIndicator = showTypingIndicator();
    let responseDiv = null;
    let fullContent = '';
    
    $.ajax({
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
            if (loadingIndicator) {
                loadingIndicator.remove();
            }
            loadConversation(currentConversationId);
            loadConversations();
            
        },
        error: function(xhr, status, error) {
            console.error('Ajax error:', status, error);
            if (loadingIndicator) {
                loadingIndicator.remove();
            }
            appendMessage('An error occurred while processing your request.', false);
        }
    });
};

// window.deleteConversation = function(id) {
//     $.post('/conversation/delete', { id }, function(response) {
//         if (response.success) {
//             if (currentConversationId === id) {
//                 currentConversationId = null;
//                 $('#chat-messages').empty();
//             }
//             loadConversations();
//         }
//     });
// };

window.deleteConversation = function(id) {
    if (confirm('Are you sure you want to delete this conversation?')) {
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
    }
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

// Helper functions (can remain non-global as they're only called from within our code)
function showToast(message, type = 'success') {
    const toast = $(`
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
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

function loadConversations() {
    $.post('/conversations', function(data) {
        const container = $('#conversations');
        container.empty();
        
        if (data.conversations && Array.isArray(data.conversations)) {
            data.conversations.forEach(conv => {
                const div = $('<div>')
                    .addClass('conversation-item')
                    .attr('data-id', conv.id);
                
                const titleDiv = $('<div>')
                    .addClass('conversation-title d-flex align-items-center justify-content-between')
                    .append(
                        $('<span>').text(conv.title),
                        $('<div>').addClass('d-flex gap-2').append(
                            $('<button>')
                                .addClass('btn btn-secondary btn-sm')
                                .html('<i class="fas fa-pencil"></i>')
                                .on('click', function(e) {
                                    e.stopPropagation();
                                    const newTitle = prompt('Enter new title:', conv.title);
                                    if (newTitle) {
                                        updateConversationTitle(conv.id, newTitle);
                                    }
                                }),
                            $('<button>')
                                .addClass('btn btn-danger btn-sm')
                                .html('<i class="fas fa-trash"></i>')
                                .on('click', function(e) {
                                    e.stopPropagation();
                                    deleteConversation(conv.id);
                                    
                                })
                        )
                    );
                
                div.append(titleDiv);
                
                if (conv.id === currentConversationId) {
                    div.addClass('active');
                }
                
                div.click(() => loadConversation(conv.id));
                container.append(div);
            });
        }
    });
}

// function loadConversation(id) {
//         currentConversationId = id;
//         $.ajax({
//             url: '/conversation/load',
//             method: 'POST',
//             data: { id: id },
//             success: function(conversation) {
//                 $('#chat-messages').empty();
//                 if (conversation && conversation.messages) {
//                     conversation.messages.forEach(msg => {
//                         appendMessage(msg.prompt, true);
//                         if (msg.response) {
//                             appendMessage(msg.response, false);
//                         }
//                     });
//                 }
//                 loadConversations();
//             },
//             error: function(xhr, status, error) {
//                 console.error('Error loading conversation:', error);
//                 showToast('Error loading conversation', 'error');
//             }
//         });
//     }

    // function loadConversation(id) {
    //     currentConversationId = id;
    //     $.post('/conversation/load', { id }, function(conversation) {
    //         $('#chat-messages').empty();
    //         if (conversation && conversation.messages) {
    //             conversation.messages.forEach(msg => {
    //                 const messageDiv = $('<div>')
    //                     .addClass('message')
    //                     .addClass('user-message')
    //                     .text(msg.prompt);
    //                 $('#chat-messages').append(messageDiv);

    //                 if (msg.response) {
    //                     const formattedContent = parseAndFormatContent(msg.response);
    //                     const responseDiv = $('<div>')
    //                         .addClass('message')
    //                         .addClass('ai-message')
    //                         .html(formattedContent);

    //                     responseDiv.find('pre code').each((i, el) => {
    //                         hljs.highlightElement(el);
    //                     });
    //                     $('#chat-messages').append(responseDiv);
    //                 }
    //             });
    //         }
    //         loadConversations();
    //     });
    // }
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
        });
    }


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
                    .html('<i class="fas fa-copy"></i>')
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
                    .html('<i class="fas fa-copy"></i>')
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
    
    async function copyToClipboard(text, buttonElement) {
        try {
            await navigator.clipboard.writeText(text);
            
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

$('#model-select').change(function() {
    const model = $(this).val();
    $.post('/model/switch', { model }, function(response) {
        if (response.success) {
            showToast('Model switched successfully');
        } else {
            showToast('Error switching model', 'error');
        }
    });
});

$('#message-input').keypress(function(e) {
    if (e.which == 13 && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// Initialize CSRF token for AJAX requests
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

// Initialize on page load
$(document).ready(function() {
    loadConversations();
});

document.addEventListener('DOMContentLoaded', (event) => {
    hljs.configure({
        ignoreUnescaped: true,
        languages: ['javascript', 'php', 'python']
    });
});