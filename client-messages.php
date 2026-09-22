<?php
require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-messages-data.php';

// Strict Client Authentication Guard
$client = require_client_auth();
$orgId     = (int)$client['organization_id'];
$companyId = (int)$client['company_id'];
$contactId = (int)$client['contact_id'];

$page_title = "Messages & Team Inbox — NexFlow Client Portal";
$active_nav = "messages";
$page_heading = "Direct Account Messages";

// Preload live conversations strictly scoped to authenticated client company
$convData = client_get_conversations_data(nexflow_db(), $orgId, $companyId);
$initialConversations = $convData['conversations'];

include __DIR__ . '/includes/client-header.php';
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">Communicate directly with your assigned NexFlow account managers, solutions engineers, and support team.</p>
        </div>

        <!-- Chat Container -->
        <div class="client-portal-chat-container">
            
            <!-- Left: Conversation List -->
            <div class="client-portal-chat-sidebar">
                <div class="client-portal-chat-sidebar-header" style="display:flex;gap:8px;align-items:center;">
                    <input type="text" class="input-control" placeholder="Search conversations..." style="font-size:12.5px;height:34px;flex:1;" id="chatSearchInput" oninput="filterConversationList()">
                    <button type="button" class="btn btn-primary btn-xs" style="height:34px;padding:0 10px;font-size:12px;display:flex;align-items:center;gap:4px;white-space:nowrap;" onclick="openNewConvModal()" id="btnOpenNewConvModal" title="Start a new conversation">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                        <span>New</span>
                    </button>
                </div>
                <div class="client-portal-chat-list" id="clientChatList">
                    <!-- Populated dynamically by safe DOM methods -->
                </div>
            </div>

            <!-- Right: Active Chat Area -->
            <div class="client-portal-chat-main" id="clientChatMain">
                <div class="client-portal-chat-header" id="activeChatHeader">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="client-portal-avatar" id="activeChatAvatar" style="background-color:#7C3AED;">--</div>
                        <div>
                            <div style="font-size:14px;font-weight:700;color:#0F172A;" id="activeChatName">Loading...</div>
                            <div style="font-size:11.5px;color:#059669;font-weight:500;" id="activeChatRole">Active</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-xs" id="btnRequestCall" onclick="handleReadOnlyAction('Request Call')">Request Call</button>
                </div>

                <div class="client-portal-chat-messages" id="activeChatMessages">
                    <!-- Messages populated dynamically by safe DOM methods -->
                </div>

                <form class="client-portal-chat-input-box" id="chatComposerForm" onsubmit="handleChatSubmit(event)">
                    <input type="file" id="chatAttachmentInput" style="display:none;" onchange="handleReadOnlyAction('Attachment')">
                    <button type="button" class="btn btn-ghost btn-xs" title="Attach file" onclick="handleReadOnlyAction('Attachment')">
                        <svg width="18" height="18" fill="none" stroke="#64748B" stroke-width="2" viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    </button>
                    <input type="text" class="input-control" id="chatInputMessage" placeholder="Type your reply here..." style="flex:1;" autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSendMessage">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        <span id="btnSendMessageText">Send</span>
                    </button>
                </form>
            </div>

        </div>

        <div style="margin-top:14px;font-size:12px;color:#94A3B8;text-align:center;">
            Direct account messages are strictly synchronized with your company's NexFlow account manager and team inbox.
        </div>

        <!-- New Conversation Modal -->
        <div id="newConvModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(3px);">
            <div style="background:#fff;border-radius:12px;width:100%;max-width:520px;padding:24px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);position:relative;margin:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <div>
                        <h3 style="margin:0;font-size:16px;font-weight:700;color:#0F172A;">Start New Conversation</h3>
                        <p style="margin:2px 0 0;font-size:12.5px;color:#64748B;">Send a direct message to your assigned NexFlow account team.</p>
                    </div>
                    <button type="button" onclick="closeNewConvModal()" style="background:none;border:none;cursor:pointer;color:#94A3B8;padding:4px;" aria-label="Close modal">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <div id="newConvError" style="display:none;padding:10px 14px;border-radius:6px;background:#FEE2E2;color:#991B1B;font-size:12.5px;margin-bottom:14px;"></div>
                
                <form id="newConvForm" onsubmit="handleNewConvSubmit(event)">
                    <div style="margin-bottom:14px;">
                        <label for="newConvSubject" style="display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:6px;">Subject <span style="color:#EF4444;">*</span></label>
                        <input type="text" id="newConvSubject" class="input-control" placeholder="e.g. Account setup, Project update, Billing question" maxlength="200" required style="width:100%;box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:18px;">
                        <label for="newConvBody" style="display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:6px;">Initial Message <span style="color:#EF4444;">*</span></label>
                        <textarea id="newConvBody" class="input-control" rows="5" placeholder="Type your message to the team here..." maxlength="10000" required style="width:100%;resize:vertical;font-size:13px;box-sizing:border-box;"></textarea>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="closeNewConvModal()" id="btnCancelNewConv">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitNewConv" style="display:flex;align-items:center;gap:6px;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            <span id="btnSubmitNewConvText">Start Conversation</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>
</div>

<script>
// Authoritative preloaded conversations and CSRF token from server
let liveConversations = <?php echo json_encode($initialConversations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?> || [];
let activeConvId = null;
let currentSearchQuery = "";
window.clientPortalCsrf = <?php echo json_encode(client_csrf_token()); ?>;

// Distinct avatar background palette matching CRM standards
const avatarColors = ["#7C3AED", "#0284C7", "#059669", "#D97706", "#4F46E5", "#DB2777"];
function getAvatarColor(id) {
    const num = parseInt(String(id).replace(/[^0-9]/g, ""), 10) || 0;
    return avatarColors[num % avatarColors.length];
}

/**
 * Filter and render conversation list using safe DOM manipulation (no innerHTML for user data)
 */
function filterConversationList() {
    const input = document.getElementById("chatSearchInput");
    currentSearchQuery = input ? input.value.trim().toLowerCase() : "";
    renderConversationList();
}

function renderConversationList() {
    const listContainer = document.getElementById("clientChatList");
    if (!listContainer) return;

    listContainer.innerHTML = "";

    const filtered = liveConversations.filter(c => {
        if (!currentSearchQuery) return true;
        const nameMatch = (c.name || "").toLowerCase().includes(currentSearchQuery);
        const subjMatch = (c.subject || "").toLowerCase().includes(currentSearchQuery);
        const roleMatch = (c.role || "").toLowerCase().includes(currentSearchQuery);
        return nameMatch || subjMatch || roleMatch;
    });

    if (filtered.length === 0) {
        const emptyDiv = document.createElement("div");
        emptyDiv.style.padding = "32px 16px";
        emptyDiv.style.textAlign = "center";
        emptyDiv.style.color = "#94A3B8";
        emptyDiv.style.fontSize = "12.5px";
        emptyDiv.textContent = currentSearchQuery 
            ? "No conversations matching \"" + currentSearchQuery + "\"."
            : "No conversations found for your company account.";
        listContainer.appendChild(emptyDiv);
        return;
    }

    filtered.forEach(c => {
        const itemDiv = document.createElement("div");
        itemDiv.className = "client-portal-chat-item" + (String(c.id) === String(activeConvId) ? " active" : "");
        itemDiv.onclick = () => selectConversation(c.id);

        const avatarDiv = document.createElement("div");
        avatarDiv.className = "client-portal-avatar";
        avatarDiv.style.backgroundColor = getAvatarColor(c.raw_id || c.id);
        avatarDiv.textContent = c.avatar || "??";

        const contentDiv = document.createElement("div");
        contentDiv.style.flex = "1";
        contentDiv.style.minWidth = "0";

        const headerRow = document.createElement("div");
        headerRow.style.display = "flex";
        headerRow.style.justifyContent = "space-between";
        headerRow.style.alignItems = "center";
        headerRow.style.marginBottom = "2px";

        const nameSpan = document.createElement("span");
        nameSpan.style.fontSize = "13px";
        nameSpan.style.fontWeight = "700";
        nameSpan.style.color = "#0F172A";
        nameSpan.textContent = c.name || "Account Team";

        const timeSpan = document.createElement("span");
        timeSpan.style.fontSize = "11px";
        timeSpan.style.color = "#94A3B8";
        timeSpan.textContent = c.time || "";

        headerRow.appendChild(nameSpan);
        headerRow.appendChild(timeSpan);

        const subjDiv = document.createElement("div");
        subjDiv.style.fontSize = "12px";
        subjDiv.style.fontWeight = "600";
        subjDiv.style.color = "#334155";
        subjDiv.style.whiteSpace = "nowrap";
        subjDiv.style.overflow = "hidden";
        subjDiv.style.textOverflow = "ellipsis";
        subjDiv.style.marginBottom = "2px";
        subjDiv.textContent = c.subject || "(No Subject)";

        const previewDiv = document.createElement("div");
        previewDiv.style.fontSize = "11.5px";
        previewDiv.style.color = "#64748B";
        previewDiv.style.whiteSpace = "nowrap";
        previewDiv.style.overflow = "hidden";
        previewDiv.style.textOverflow = "ellipsis";
        previewDiv.id = "convPreview-" + (c.raw_id || c.id);
        previewDiv.textContent = c.preview || "No messages yet";

        contentDiv.appendChild(headerRow);
        contentDiv.appendChild(subjDiv);
        contentDiv.appendChild(previewDiv);

        itemDiv.appendChild(avatarDiv);
        itemDiv.appendChild(contentDiv);

        listContainer.appendChild(itemDiv);
    });
}

function selectConversation(convId) {
    activeConvId = convId;
    renderConversationList();

    const conv = liveConversations.find(c => String(c.id) === String(convId) || String(c.raw_id) === String(convId));
    if (!conv) return;

    // Update active header via textContent
    const nameEl = document.getElementById("activeChatName");
    const roleEl = document.getElementById("activeChatRole");
    const avatarEl = document.getElementById("activeChatAvatar");

    if (nameEl) nameEl.textContent = conv.name || "Account Team";
    if (roleEl) roleEl.textContent = "Active • " + (conv.role || "Account Manager");
    if (avatarEl) {
        avatarEl.textContent = conv.avatar || "??";
        avatarEl.style.backgroundColor = getAvatarColor(conv.raw_id || conv.id);
    }

    // Load thread messages from server
    loadConversationThread(conv.raw_id || conv.id);
}

/**
 * Fetch thread messages strictly isolated to company
 */
function loadConversationThread(rawId) {
    const box = document.getElementById("activeChatMessages");
    if (!box) return;

    // Loading state
    box.innerHTML = "";
    const loadingDiv = document.createElement("div");
    loadingDiv.style.display = "flex";
    loadingDiv.style.alignItems = "center";
    loadingDiv.style.justifyContent = "center";
    loadingDiv.style.height = "100%";
    loadingDiv.style.color = "#94A3B8";
    loadingDiv.style.fontSize = "13px";
    loadingDiv.textContent = "Loading conversation messages...";
    box.appendChild(loadingDiv);

    fetch(`api/client-messages.php?action=thread&id=${encodeURIComponent(rawId)}`)
        .then(res => res.json())
        .then(json => {
            box.innerHTML = "";
            if (!json.success || !json.data || !json.data.thread) {
                const errorDiv = document.createElement("div");
                errorDiv.style.padding = "24px";
                errorDiv.style.textAlign = "center";
                errorDiv.style.color = "#EF4444";
                errorDiv.style.fontSize = "13px";
                errorDiv.textContent = json.message || "Failed to load conversation thread.";
                box.appendChild(errorDiv);
                return;
            }

            const thread = json.data.thread;
            const messages = thread.messages || [];

            if (messages.length === 0) {
                const emptyThreadDiv = document.createElement("div");
                emptyThreadDiv.style.display = "flex";
                emptyThreadDiv.style.flexDirection = "column";
                emptyThreadDiv.style.alignItems = "center";
                emptyThreadDiv.style.justifyContent = "center";
                emptyThreadDiv.style.height = "100%";
                emptyThreadDiv.style.color = "#94A3B8";
                emptyThreadDiv.style.fontSize = "13px";
                emptyThreadDiv.innerHTML = `
                    <svg width="36" height="36" fill="none" stroke="#CBD5E1" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:8px;">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span>No messages yet in this conversation.</span>
                `;
                box.appendChild(emptyThreadDiv);
                return;
            }

            // Render messages safely using DOM methods (XSS prevention)
            messages.forEach(m => {
                appendMessageToChat(m, box);
            });

            box.scrollTop = box.scrollHeight;
        })
        .catch(err => {
            console.error("Thread load failed:", err);
            box.innerHTML = "";
            const errDiv = document.createElement("div");
            errDiv.style.padding = "24px";
            errDiv.style.textAlign = "center";
            errDiv.style.color = "#EF4444";
            errDiv.style.fontSize = "13px";
            errDiv.textContent = "Network error loading conversation messages.";
            box.appendChild(errDiv);
        });
}

function appendMessageToChat(m, container) {
    const box = container || document.getElementById("activeChatMessages");
    if (!box) return;

    const bubble = document.createElement("div");
    bubble.className = "client-portal-chat-bubble " + (m.is_outgoing ? "outgoing" : "incoming");

    const metaDiv = document.createElement("div");
    metaDiv.style.fontSize = "11px";
    metaDiv.style.color = m.is_outgoing ? "rgba(255,255,255,0.85)" : "#64748B";
    metaDiv.style.marginBottom = "4px";
    metaDiv.style.fontWeight = "500";
    metaDiv.textContent = (m.sender || (m.is_outgoing ? "You" : "Staff")) + " • " + (m.time || "Just now");

    const bodyDiv = document.createElement("div");
    bodyDiv.style.whiteSpace = "pre-wrap";
    bodyDiv.style.wordBreak = "break-word";
    bodyDiv.style.lineHeight = "1.45";
    bodyDiv.textContent = m.body || ""; // Strict textContent

    bubble.appendChild(metaDiv);
    bubble.appendChild(bodyDiv);
    box.appendChild(bubble);
}

/**
 * Real client message sending handler (Phase 3)
 */
function handleChatSubmit(e) {
    e.preventDefault();
    if (!activeConvId) {
        notifyUser("Please select or start a conversation first.", "error");
        return;
    }

    const conv = liveConversations.find(c => String(c.id) === String(activeConvId) || String(c.raw_id) === String(activeConvId));
    if (!conv) {
        notifyUser("Invalid active conversation selected.", "error");
        return;
    }

    const input = document.getElementById("chatInputMessage");
    const sendBtn = document.getElementById("btnSendMessage");
    const sendBtnText = document.getElementById("btnSendMessageText");
    const messageText = input ? input.value.trim() : "";

    if (!messageText) {
        return;
    }

    // Set sending state
    if (input) input.disabled = true;
    if (sendBtn) sendBtn.disabled = true;
    if (sendBtnText) sendBtnText.textContent = "Sending...";

    const payload = {
        conversation_id: conv.raw_id || conv.id,
        body: messageText,
        csrf_token: window.clientPortalCsrf
    };

    fetch("api/client-messages.php?action=send", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": window.clientPortalCsrf || ""
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(json => {
        if (!json.success || !json.data || !json.data.message) {
            notifyUser(json.message || "Failed to send message.", "error");
            return;
        }

        // 1. Clear input
        if (input) input.value = "";

        // 2. Append new message to active stream
        const box = document.getElementById("activeChatMessages");
        if (box) {
            // Remove empty placeholder if present
            if (box.textContent.includes("No messages yet in this conversation")) {
                box.innerHTML = "";
            }
            appendMessageToChat(json.data.message, box);
            box.scrollTop = box.scrollHeight;
        }

        // 3. Update conversation in liveConversations and sidebar preview
        conv.preview = messageText.length > 95 ? (messageText.substring(0, 95) + "...") : messageText;
        conv.time = "Just now";
        conv.has_messages = true;

        const previewEl = document.getElementById("convPreview-" + (conv.raw_id || conv.id));
        if (previewEl) {
            previewEl.textContent = conv.preview;
        }

        notifyUser("Message sent.", "success");
    })
    .catch(err => {
        console.error("Failed to send message:", err);
        notifyUser("Network error while sending message. Please try again.", "error");
    })
    .finally(() => {
        if (input) {
            input.disabled = false;
            input.focus();
        }
        if (sendBtn) sendBtn.disabled = false;
        if (sendBtnText) sendBtnText.textContent = "Send";
    });
}

/**
 * Modal handlers for Starting a New Conversation
 */
function openNewConvModal() {
    const modal = document.getElementById("newConvModal");
    const errDiv = document.getElementById("newConvError");
    const subjInput = document.getElementById("newConvSubject");
    const bodyInput = document.getElementById("newConvBody");

    if (errDiv) errDiv.style.display = "none";
    if (subjInput) subjInput.value = "";
    if (bodyInput) bodyInput.value = "";
    if (modal) modal.style.display = "flex";
    if (subjInput) setTimeout(() => subjInput.focus(), 50);
}

function closeNewConvModal() {
    const modal = document.getElementById("newConvModal");
    if (modal) modal.style.display = "none";
}

function handleNewConvSubmit(e) {
    e.preventDefault();
    const subjInput = document.getElementById("newConvSubject");
    const bodyInput = document.getElementById("newConvBody");
    const errDiv = document.getElementById("newConvError");
    const submitBtn = document.getElementById("btnSubmitNewConv");
    const submitBtnText = document.getElementById("btnSubmitNewConvText");

    const subject = subjInput ? subjInput.value.trim() : "";
    const body = bodyInput ? bodyInput.value.trim() : "";

    if (subject.length < 3) {
        if (errDiv) {
            errDiv.textContent = "Subject must be at least 3 characters long.";
            errDiv.style.display = "block";
        }
        return;
    }

    if (!body) {
        if (errDiv) {
            errDiv.textContent = "Initial message cannot be empty.";
            errDiv.style.display = "block";
        }
        return;
    }

    if (errDiv) errDiv.style.display = "none";
    if (submitBtn) submitBtn.disabled = true;
    if (submitBtnText) submitBtnText.textContent = "Starting...";

    const payload = {
        subject: subject,
        body: body,
        csrf_token: window.clientPortalCsrf
    };

    fetch("api/client-messages.php?action=new_conversation", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": window.clientPortalCsrf || ""
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(json => {
        if (!json.success || !json.data || !json.data.conversation) {
            if (errDiv) {
                errDiv.textContent = json.message || "Failed to start conversation.";
                errDiv.style.display = "block";
            }
            return;
        }

        closeNewConvModal();

        // Add new conversation to liveConversations at the top
        const newThread = json.data.conversation;
        const newConvItem = {
            id: newThread.id,
            raw_id: newThread.raw_id,
            code: newThread.code,
            name: newThread.name,
            role: newThread.role,
            avatar: newThread.avatar,
            subject: newThread.subject,
            preview: body.length > 95 ? (body.substring(0, 95) + "...") : body,
            has_messages: true,
            time: "Just now",
            channel: newThread.channel,
            status: newThread.status,
            priority: "Normal",
            unread: false
        };

        liveConversations.unshift(newConvItem);
        renderConversationList();
        selectConversation(newConvItem.id);

        notifyUser("Conversation started successfully.", "success");
    })
    .catch(err => {
        console.error("Failed to start conversation:", err);
        if (errDiv) {
            errDiv.textContent = "Network error. Please try again.";
            errDiv.style.display = "block";
        }
    })
    .finally(() => {
        if (submitBtn) submitBtn.disabled = false;
        if (submitBtnText) submitBtnText.textContent = "Start Conversation";
    });
}

function handleReadOnlyAction(actionType) {
    const msg = actionType + " is currently in read-only mode in this phase.";
    notifyUser(msg, "info");
}

function notifyUser(msg, type) {
    if (window.clientPortal && typeof window.clientPortal.showToast === "function") {
        window.clientPortal.showToast(msg, type || "info");
    } else {
        alert(msg);
    }
}

// Initial hydration on DOMContentLoaded
document.addEventListener("DOMContentLoaded", function() {
    renderConversationList();

    if (liveConversations.length > 0) {
        // Check URL parameter for specific conversation
        const urlParams = new URLSearchParams(window.location.search);
        const targetId = urlParams.get("id") || urlParams.get("conv");
        
        let targetConv = null;
        if (targetId) {
            targetConv = liveConversations.find(c => String(c.id) === String(targetId) || String(c.raw_id) === String(targetId) || c.code === targetId);
        }
        if (!targetConv) {
            targetConv = liveConversations[0];
        }

        selectConversation(targetConv.id);
    } else {
        // Empty state for zero conversations
        const box = document.getElementById("activeChatMessages");
        if (box) {
            box.innerHTML = `
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;min-height:300px;color:#94A3B8;font-size:13px;text-align:center;">
                    <svg width="40" height="40" fill="none" stroke="#CBD5E1" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:10px;">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span style="font-weight:600;color:#64748B;">No Conversations</span>
                    <span style="margin-top:4px;font-size:12px;">Click "+ New" to start a direct message with your account manager.</span>
                </div>
            `;
        }
        const nameEl = document.getElementById("activeChatName");
        const roleEl = document.getElementById("activeChatRole");
        if (nameEl) nameEl.textContent = "No Conversation Selected";
        if (roleEl) roleEl.textContent = "Ready to connect";
    }
});
</script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>