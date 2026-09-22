<?php
// includes/client-footer.php
?>
    <!-- Shared Right-Side Deal Details Drawer -->
    <div class="client-portal-drawer-overlay" id="clientDealDrawer" role="dialog" aria-modal="true" aria-labelledby="clientDealDrawerTitle" onclick="if(event.target===this) window.clientPortal.closeDealDrawer()">
        <div class="client-portal-drawer-panel" onclick="event.stopPropagation()">
            <div class="client-portal-drawer-header">
                <div>
                    <div class="client-portal-drawer-badge" id="cddStageBadge">In Progress</div>
                    <h3 class="client-portal-drawer-title" id="clientDealDrawerTitle">Deal Details</h3>
                </div>
                <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeDealDrawer()" aria-label="Close drawer">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            
            <!-- Drawer Tab Switcher -->
            <div class="client-portal-drawer-tabs" role="tablist" aria-label="Deal details navigation">
                <button type="button" class="client-portal-drawer-tab active" role="tab" aria-selected="true" data-tab="overview" onclick="window.clientPortal.switchDealDrawerTab('overview')">Overview</button>
                <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="activity" onclick="window.clientPortal.switchDealDrawerTab('activity')">Activity</button>
                <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="tasks" onclick="window.clientPortal.switchDealDrawerTab('tasks')">Tasks</button>
                <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="documents" onclick="window.clientPortal.switchDealDrawerTab('documents')">Documents</button>
                <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="comments" onclick="window.clientPortal.switchDealDrawerTab('comments')">Notes / Request</button>
            </div>

            <!-- Drawer Scrollable Body -->
            <div class="client-portal-drawer-body" id="clientDealDrawerBody" tabindex="0">
                <!-- Dynamically injected tab panels by client-portal.js -->
            </div>

            <div class="client-portal-drawer-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeDealDrawer()">Close</button>
                <a href="client-messages.php?contact=olivia-martin" class="btn btn-primary btn-sm" id="btnDrawerSendMessage">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Send Message to AE
                </a>
            </div>
        </div>
    </div>

    <!-- Shared Top-Level Modal Container -->
    <div class="client-portal-modal-overlay" id="clientModalOverlay" role="dialog" aria-modal="true" aria-labelledby="clientModalTitle" style="display:none;">
        <div class="client-portal-modal-card" id="clientModalCard">
            <!-- Dynamically populated modal content -->
        </div>
    </div>

    <!-- Toast Notifications Root -->
    <div class="client-portal-toast-container" id="clientToastContainer"></div>

</div> <!-- .client-portal-layout -->

<!-- Core Client Portal Controller Scripts -->
<script src="assets/js/modal-system.js"></script>
<script src="assets/js/client-portal.js"></script>
</body>
</html>
