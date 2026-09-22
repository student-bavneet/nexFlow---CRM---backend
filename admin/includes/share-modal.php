<?php
// Shared Reusable Share Modal Partial for NexFlow CRM
?>

<div class="share-modal-overlay" id="NexFlowShareModal" role="dialog" aria-modal="true" aria-labelledby="shareModalRecordTitle" onclick="if(event.target===this) window.NexFlowShare.closeModal()">
    <div class="share-modal-card" onclick="event.stopPropagation()">
        
        <!-- Modal Header -->
        <div class="share-modal-header">
            <div class="share-modal-header-left">
                <div style="display: flex; flex-direction: column;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="share-type-badge" id="shareModalRecordTypeBadge">
                            <span id="shareModalRecordTypeIcon">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                            </span>
                            <span id="shareModalRecordType">Record</span>
                        </span>
                        <h3 class="share-modal-title" id="shareModalRecordTitle">Share Record</h3>
                    </div>
                    <p class="share-modal-subtitle" id="shareModalRecordSub">Acme Corp • Lead</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" id="shareModalCloseBtn" onclick="window.NexFlowShare.closeModal()" aria-label="Close share dialog">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="share-modal-body">
            
            <!-- 1. Share Link Group -->
            <div>
                <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Shareable Demo Link</label>
                <div class="share-link-group">
                    <input type="text" class="input-control input-sm share-link-input" id="shareModalLinkInput" readonly>
                    <button type="button" class="btn btn-secondary btn-sm" id="btnShareCopyLink" onclick="window.NexFlowShare.copyLink()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span>Copy</span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="btnShareNative" style="display: none;" onclick="window.NexFlowShare.nativeShare()" title="System Share">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    </button>
                </div>
            </div>

            <!-- 2. Access Settings Grid -->
            <div class="share-settings-grid">
                <div>
                    <label class="form-label" style="font-size: 12px;">Audience Access</label>
                    <select class="input-control input-sm" id="shareAudienceSelect" onchange="window.NexFlowShare.toggleAudienceSection()">
                        <option value="anyone" selected>Anyone with demo link</option>
                        <option value="team">Selected team members only</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size: 12px;">Permissions</label>
                    <select class="input-control input-sm" id="sharePermissionSelect">
                        <option value="view" selected>Can view only</option>
                        <option value="edit">Can edit record</option>
                    </select>
                </div>
            </div>

            <!-- 3. Expiry -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label class="form-label" style="font-size: 12px;">Link Expiration</label>
                    <select class="input-control input-sm" id="shareExpirySelect">
                        <option value="never" selected>Never expires</option>
                        <option value="7days">Expires in 7 days</option>
                        <option value="30days">Expires in 30 days</option>
                    </select>
                </div>
            </div>

            <!-- 4. Team Members Checklist (Shown when audience is 'team') -->
            <div class="share-team-section" id="shareTeamSection" style="display: none;">
                <label class="form-label" style="font-size: 12px; margin-bottom: 6px;">Select Team Members</label>
                <div class="share-team-search">
                    <input type="text" class="input-control input-xs" id="shareTeamSearchInput" placeholder="Search team members..." oninput="window.NexFlowShare.filterTeamList(this.value)">
                </div>
                <div class="share-team-list" id="shareTeamList">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- 5. Optional Note / Message -->
            <div>
                <label class="form-label" style="font-size: 12px;">Message / Note (Optional)</label>
                <textarea class="input-control input-sm" id="shareMessageTextarea" rows="2" style="height: 55px; resize: none;" placeholder="Add context or notes for the recipient..."></textarea>
            </div>

            <!-- 6. Existing Shared Access List -->
            <div>
                <label class="form-label" style="font-size: 12px; margin-bottom: 6px;">Active Access Grants</label>
                <div class="share-access-list" id="shareAccessList">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- 7. Demo Info Banner -->
            <div class="share-info-banner">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>Frontend demo: sharing settings are stored locally in this browser. Secure links, invitations and access enforcement require backend integration.</span>
            </div>

        </div>

        <!-- Modal Footer -->
        <div class="share-modal-footer">
            <div class="share-footer-left">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.NexFlowShare.closeModal()">Cancel</button>
            </div>
            <div class="share-footer-right">
                <button type="button" class="btn btn-ghost btn-sm" onclick="window.NexFlowShare.copyLink()">Copy Link</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveShareSettings" onclick="window.NexFlowShare.saveSettings()">Save Sharing Settings</button>
            </div>
        </div>

    </div>
</div>

<!-- Dedicated Toast Container for Share Actions -->
<div class="share-toast-container" id="shareToastContainer"></div>

