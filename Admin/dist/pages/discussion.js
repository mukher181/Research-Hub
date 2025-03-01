document.addEventListener('DOMContentLoaded', function() {
    const messageContainer = document.getElementById('research-discussion-messages');
    const messageInput = document.getElementById('research-message-input');
    const sendButton = document.getElementById('research-send-message');
    let lastMessageId = 0;

    // Function to fetch messages
    function fetchMessages() {
        fetch(`discussion.php?last_id=${lastMessageId}`)
            .then(response => {
                console.log('Fetch response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(messages => {
                console.log('Received messages:', messages);
                if (Array.isArray(messages)) {
                    messages.forEach(msg => {
                        appendMessage(msg);
                        lastMessageId = Math.max(lastMessageId, msg.id);
                    });
                } else {
                    console.error('Received non-array messages:', messages);
                }
            })
            .catch(error => {
                console.error('Error fetching messages:', error);
                messageContainer.innerHTML = `
                    <div class="alert alert-danger">
                        Unable to load messages: ${error.message}
                        <br>Please check your network connection and server status.
                    </div>
                `;
            });
    }

    // Function to append a message to the container
    function appendMessage(message) {
        const messageElement = document.createElement('div');
        messageElement.classList.add('direct-chat-msg');
        
        // Check if the message is from admin and add special styling
        const senderName = message.sender === 'admin' ? 'By admin' : (message.sender || 'Unknown');
        const adminClass = message.sender === 'admin' ? 'admin-message' : '';
        
        // Optionally, you can add a tooltip or hidden attribute for the real username
        messageElement.innerHTML = `
            <div class="direct-chat-infos clearfix">
                <span class="direct-chat-name float-left ${adminClass}" 
                      title="Real username: ${message.real_username || 'N/A'}">${senderName}</span>
                <span class="direct-chat-timestamp float-right">${message.timestamp || 'Just now'}</span>
            </div>
            <div class="direct-chat-text ${adminClass}">${message.message || 'Empty message'}</div>
        `;
        messageContainer.appendChild(messageElement);
        messageContainer.scrollTop = messageContainer.scrollHeight;
    }

    // Send message function
    function sendMessage() {
        const message = messageInput.value.trim();
        if (!message) {
            console.warn('Attempted to send empty message');
            return;
        }

        const formData = new FormData();
        formData.append('message', message);

        console.log('Sending message:', message);

        fetch('discussion.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Send response status:', response.status);
            if (!response.ok) {
                return response.json().then(errorData => {
                    throw new Error(JSON.stringify(errorData));
                });
            }
            return response.json();
        })
        .then(result => {
            console.log('Send message result:', result);
            if (result.status === 'success') {
                messageInput.value = '';
                fetchMessages();
            } else {
                throw new Error(result.error || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error sending message:', error);
            messageContainer.innerHTML += `
                <div class="alert alert-danger">
                    Unable to send message: ${error.message}
                    <br>Please check your network connection and server configuration.
                </div>
            `;
        });
    }

    // Event listeners
    sendButton.addEventListener('click', sendMessage);
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendMessage();
    });

    // Fetch messages every 3 seconds
    fetchMessages();
    setInterval(fetchMessages, 3000);
});