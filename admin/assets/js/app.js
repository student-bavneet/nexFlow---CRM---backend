/**
 * NexFlow CRM Core Application JavaScript
 */
document.addEventListener("DOMContentLoaded", function() {
    // Mobile Sidebar Drawer Toggle
    const mobileToggle = document.getElementById("mobileMenuToggle");
    const sidebar = document.getElementById("sidebar");
    const backdrop = document.getElementById("sidebarBackdrop");

    if (mobileToggle && sidebar && backdrop) {
        mobileToggle.addEventListener("click", function() {
            sidebar.classList.toggle("mobile-open");
            backdrop.classList.toggle("mobile-open");
        });

        backdrop.addEventListener("click", function() {
            sidebar.classList.remove("mobile-open");
            backdrop.classList.remove("mobile-open");
        });
    }

    // Generic Dropdown Toggle Logic
    setupDropdown("quickAddBtn", "quickAddMenu");
    setupDropdown("notifBtn", "notifMenu");
    setupDropdown("userMenuBtn", "userMenu");

    function setupDropdown(btnId, menuId) {
        const btn = document.getElementById(btnId);
        const menu = document.getElementById(menuId);

        if (btn && menu) {
            btn.addEventListener("click", function(e) {
                e.stopPropagation();
                // Close other open dropdowns
                document.querySelectorAll(".dropdown-menu.show").forEach(m => {
                    if (m.id !== menuId) m.classList.remove("show");
                });
                menu.classList.toggle("show");
            });

            menu.addEventListener("click", function(e) {
                if (e.target.closest(".dropdown-item")) {
                    menu.classList.remove("show");
                    return;
                }
                e.stopPropagation();
            });
        }
    }

    // Close dropdowns on outside click
    document.addEventListener("click", function() {
        document.querySelectorAll(".dropdown-menu.show").forEach(m => m.classList.remove("show"));
    });
});

// Notifications Mark All Read
function markAllNotificationsRead() {
    const badge = document.getElementById("notifBadge");
    const tag = document.getElementById("notifCountTag");
    if (badge) badge.style.display = "none";
    if (tag) tag.textContent = "0";
    document.querySelectorAll(".notif-item").forEach(item => {
        item.style.backgroundColor = "#ffffff";
    });
}

// Open Global Add Lead Modal helper
function openAddLeadModal() {
    const modal = document.getElementById("addLeadModal");
    if (modal) {
        modal.classList.add("show");
    } else {
        window.location.href = "leads.php?action=new";
    }
}

function closeAddLeadModal() {
    const modal = document.getElementById("addLeadModal");
    if (modal) {
        modal.classList.remove("show");
    }
}

// Centralized Sidebar Dynamic Badge Refresh
window.refreshSidebarBadges = function () {
    fetch('api/sidebar.php?action=counts', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    })
    .then(data => {
        if (data && data.success && data.counts) {
            window.updateSidebarBadges(data.counts);
        }
    })
    .catch(err => {
        // Silently skip if user navigated or request cancelled
    });
};

window.updateSidebarBadges = function (counts) {
    if (!counts || typeof counts !== 'object') return;
    for (const [key, val] of Object.entries(counts)) {
        const countNum = parseInt(val, 10) || 0;
        const badge = document.querySelector(`.sidebar-badge[data-sidebar-badge="${key}"]`)
            || document.querySelector(`.sidebar-nav-item[href*="${key}.php"] .sidebar-badge`);
        if (badge) {
            if (countNum > 0) {
                badge.textContent = countNum;
                badge.style.display = "";
            } else {
                badge.textContent = "0";
                badge.style.display = "none";
            }
        }
    }
};

// Initial sidebar counts refresh on DOM ready
document.addEventListener("DOMContentLoaded", function () {
    if (typeof window.refreshSidebarBadges === "function") {
        window.refreshSidebarBadges();
    }
});

