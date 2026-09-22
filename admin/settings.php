<?php
$page_title = "Settings";
$current_page = "settings";
$page_script = "settings.js";

// Include mock data for CSV export
require_once __DIR__ . '/data/mock-data.php';

include __DIR__ . '/includes/header.php';
requirePermission('settings');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<main class="main-content">
    <div class="page-container settings-page">

        <!-- Page Header -->
        <div class="settings-header">
            <div class="settings-header-left">
                <div class="settings-title-row">
                    <h1 class="settings-header-title">Settings</h1>
                    <span class="settings-status-badge saved" id="settingsStatusBadge">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        All changes saved
                    </span>
                </div>
                <p class="settings-header-subtitle">Manage your profile, workspace and CRM preferences.</p>
            </div>
            <div class="settings-header-right">
                <button type="button" class="btn btn-primary btn-md btn-save-settings" onclick="window.settingsApp.saveAllSettings()">Save Changes</button>
            </div>
        </div>

        <!-- Two-Column Settings Layout -->
        <div class="settings-layout">

            <!-- Mobile Navigation Dropdown -->
            <div class="settings-nav-mobile-select">
                <select class="input-control" id="settingsMobileNavSelect">
                    <optgroup label="Personal">
                        <option value="profile">My Profile</option>
                        <option value="preferences">Preferences</option>
                        <option value="notifications">Notifications</option>
                    </optgroup>
                    <optgroup label="Workspace">
                        <option value="company">Company Profile</option>
                        <option value="team-defaults">Team Defaults</option>
                        <option value="roles">Roles Preview</option>
                    </optgroup>
                    <optgroup label="CRM Configuration">
                        <option value="pipeline">Sales Pipeline</option>
                        <option value="leads">Lead Settings</option>
                        <option value="custom-fields">Custom Fields</option>
                        <option value="tasks">Task Settings</option>
                    </optgroup>
                    <optgroup label="Communication">
                        <option value="email">Email Preferences</option>
                        <option value="calling">Calling Preferences</option>
                        <option value="inbox">Inbox Preferences</option>
                    </optgroup>
                    <optgroup label="Data &amp; Integrations">
                        <option value="integrations">Integrations</option>
                        <option value="import-export">Import &amp; Export</option>
                    </optgroup>
                    <optgroup label="Security">
                        <option value="security">Security Preview</option>
                        <option value="privacy">Data &amp; Privacy</option>
                    </optgroup>
                    <optgroup label="Other">
                        <option value="billing">Billing Preview</option>
                        <option value="danger">Danger Zone</option>
                    </optgroup>
                </select>
            </div>

            <!-- Left Settings Navigation Rail -->
            <aside class="settings-navigation">
                <div class="settings-nav-search">
                    <input type="text" class="input-control" id="settingsSearchInput" placeholder="Search settings...">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="settings-nav-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>

                <div class="settings-nav-group">
                    <div class="settings-nav-group-title">Personal</div>
                    <button type="button" class="settings-nav-item active" data-section="profile">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        My Profile
                    </button>
                    <button type="button" class="settings-nav-item" data-section="preferences">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                        Preferences
                    </button>
                    <button type="button" class="settings-nav-item" data-section="notifications">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                        Notifications
                    </button>
                </div>

                <div class="settings-nav-group">
                    <div class="settings-nav-group-title">Workspace</div>
                    <button type="button" class="settings-nav-item" data-section="company">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/></svg>
                        Company Profile
                    </button>
                    <button type="button" class="settings-nav-item" data-section="team-defaults">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        Team Defaults
                    </button>
                    <button type="button" class="settings-nav-item" data-section="roles">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Roles Preview
                    </button>
                </div>

                <div class="settings-nav-group">
                    <div class="settings-nav-group-title">CRM Configuration</div>
                    <button type="button" class="settings-nav-item" data-section="pipeline">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 3v12"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 01-9 9"/></svg>
                        Sales Pipeline
                    </button>
                    <button type="button" class="settings-nav-item" data-section="leads">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        Lead Settings
                    </button>
                    <button type="button" class="settings-nav-item" data-section="custom-fields">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>
                        Custom Fields
                    </button>
                    <button type="button" class="settings-nav-item" data-section="tasks">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                        Task Settings
                    </button>
                </div>

                <div class="settings-nav-group">
                    <div class="settings-nav-group-title">Communication</div>
                    <button type="button" class="settings-nav-item" data-section="email">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Email Preferences
                    </button>
                    <button type="button" class="settings-nav-item" data-section="calling">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                        Calling Preferences
                    </button>
                    <button type="button" class="settings-nav-item" data-section="inbox">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
                        Inbox Preferences
                    </button>
                </div>

                <div class="settings-nav-group">
                    <div class="settings-nav-group-title">Data &amp; Integrations</div>
                    <button type="button" class="settings-nav-item" data-section="integrations">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        Integrations
                    </button>
                    <button type="button" class="settings-nav-item" data-section="import-export">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Import &amp; Export
                    </button>
                </div>

                <div class="settings-nav-group">
                    <div class="settings-nav-group-title">Security</div>
                    <button type="button" class="settings-nav-item" data-section="security">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        Security Preview
                    </button>
                    <button type="button" class="settings-nav-item" data-section="privacy">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Data &amp; Privacy
                    </button>
                </div>

                <div class="settings-nav-group">
                    <div class="settings-nav-group-title">Other</div>
                    <button type="button" class="settings-nav-item" data-section="billing">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Billing Preview
                    </button>
                    <button type="button" class="settings-nav-item" data-section="danger" style="color:#DC2626;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Danger Zone
                    </button>
                </div>
            </aside>

            <!-- Right Content Container -->
            <div class="settings-content">

                <!-- 1. MY PROFILE -->
                <section class="settings-section active" id="section-profile">
                    <div class="settings-card">
                        <h2 class="settings-card-title">My Profile</h2>
                        <p class="settings-card-subtitle">Manage your personal information and contact details.</p>

                        <div class="settings-profile-photo-card">
                            <div id="avatarPreviewContainer">
                                <div class="settings-avatar-preview">OM</div>
                            </div>
                            <div>
                                <h3 style="font-size:15px;font-weight:600;color:var(--text-heading);margin-bottom:2px;">Profile Photo</h3>
                                <p style="font-size:12px;color:var(--text-secondary);margin-bottom:10px;">JPG, PNG or GIF up to 2MB. FileReader preview only.</p>
                                <div class="settings-avatar-actions">
                                    <input type="file" id="avatarFileInput" accept="image/*" style="display:none;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('avatarFileInput').click()">Change Photo</button>
                                    <button type="button" class="btn btn-ghost btn-sm" id="btnRemoveAvatar" style="color:#DC2626;">Remove</button>
                                </div>
                            </div>
                        </div>

                        <div class="settings-form-grid">
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileFirstName">First Name *</label>
                                <input type="text" class="input-control" id="profileFirstName" value="Olivia">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileLastName">Last Name *</label>
                                <input type="text" class="input-control" id="profileLastName" value="Martin">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileDisplayName">Display Name</label>
                                <input type="text" class="input-control" id="profileDisplayName" value="Olivia Martin">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileEmail">Work Email *</label>
                                <input type="email" class="input-control" id="profileEmail" value="olivia.martin@NexFlow.io">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="profilePhone">Phone Number</label>
                                <input type="text" class="input-control" id="profilePhone" value="+1 (555) 234-5678">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileJobTitle">Job Title</label>
                                <input type="text" class="input-control" id="profileJobTitle" value="Sales Manager">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileDepartment">Department</label>
                                <select class="input-control" id="profileDepartment">
                                    <option value="Sales">Sales</option>
                                    <option value="Account Management">Account Management</option>
                                    <option value="Business Development">Business Development</option>
                                    <option value="Operations">Operations</option>
                                    <option value="Customer Success">Customer Success</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileLocation">Location</label>
                                <input type="text" class="input-control" id="profileLocation" value="San Francisco, CA">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileTimeZone">Time Zone</label>
                                <select class="input-control" id="profileTimeZone">
                                    <option value="America/Los_Angeles">Pacific Time (PST, UTC-8)</option>
                                    <option value="America/New_York">Eastern Time (EST, UTC-5)</option>
                                    <option value="Europe/London">Greenwich Mean Time (GMT, UTC+0)</option>
                                    <option value="Asia/Tokyo">Japan Standard Time (JST, UTC+9)</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileLanguage">Language</label>
                                <select class="input-control" id="profileLanguage">
                                    <option value="en-US">English (United States)</option>
                                    <option value="en-GB">English (United Kingdom)</option>
                                    <option value="es-ES">Spanish</option>
                                    <option value="fr-FR">French</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top:16px;">
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileBio">Short Bio</label>
                                <textarea class="input-control" id="profileBio" rows="3" style="height:auto;padding:8px 12px;">Leading SMB & Enterprise sales teams at NexFlow CRM.</textarea>
                            </div>
                        </div>

                        <div style="margin-top:16px;">
                            <div class="settings-field-group">
                                <label class="settings-label" for="profileLinkedin">LinkedIn URL</label>
                                <input type="url" class="input-control" id="profileLinkedin" value="https://linkedin.com/in/oliviamartin-NexFlow">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 2. PREFERENCES -->
                <section class="settings-section" id="section-preferences">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Localization &amp; Display Preferences</h2>
                        <p class="settings-card-subtitle">Configure date, time, currency and app view settings.</p>

                        <div class="settings-form-grid">
                            <div class="settings-field-group">
                                <label class="settings-label" for="prefLanguage">System Language</label>
                                <select class="input-control" id="prefLanguage">
                                    <option value="en-US">English (US)</option>
                                    <option value="en-GB">English (UK)</option>
                                    <option value="es">Spanish</option>
                                    <option value="de">German</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="prefTimeZone">Time Zone</label>
                                <select class="input-control" id="prefTimeZone">
                                    <option value="America/Los_Angeles">Pacific Time (PST)</option>
                                    <option value="America/New_York">Eastern Time (EST)</option>
                                    <option value="Europe/London">London (GMT)</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="prefDateFormat">Date Format</label>
                                <select class="input-control" id="prefDateFormat">
                                    <option value="MMM DD, YYYY">Jul 28, 2025</option>
                                    <option value="YYYY-MM-DD">2025-07-28</option>
                                    <option value="DD/MM/YYYY">28/07/2025</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="prefTimeFormat">Time Format</label>
                                <select class="input-control" id="prefTimeFormat">
                                    <option value="12h">12-hour (02:30 PM)</option>
                                    <option value="24h">24-hour (14:30)</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="prefWeekStartsOn">Week Starts On</label>
                                <select class="input-control" id="prefWeekStartsOn">
                                    <option value="Sunday">Sunday</option>
                                    <option value="Monday">Monday</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="prefCurrency">Default Currency</label>
                                <select class="input-control" id="prefCurrency">
                                    <option value="USD ($)">USD ($)</option>
                                    <option value="EUR (€)">EUR (€)</option>
                                    <option value="GBP (£)">GBP (£)</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="prefNumberFormat">Number Format</label>
                                <select class="input-control" id="prefNumberFormat">
                                    <option value="1,234.56">1,234.56</option>
                                    <option value="1.234,56">1.234,56</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="prefDefaultLanding">Default Landing Page</label>
                                <select class="input-control" id="prefDefaultLanding">
                                    <option value="dashboard">Dashboard</option>
                                    <option value="leads">Leads</option>
                                    <option value="pipeline">Sales Pipeline</option>
                                    <option value="tasks">Tasks</option>
                                    <option value="inbox">Inbox</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="prefPageSize">Default Table Rows</label>
                                <select class="input-control" id="prefPageSize">
                                    <option value="10">10 per page</option>
                                    <option value="25">25 per page</option>
                                    <option value="50">50 per page</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="prefDensity">Interface Density</label>
                                <select class="input-control" id="prefDensity">
                                    <option value="comfortable">Comfortable</option>
                                    <option value="compact">Compact</option>
                                </select>
                            </div>
                        </div>

                        </div>
                    </div>
                </section>

                <!-- 3. NOTIFICATIONS -->
                <section class="settings-section" id="section-notifications">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Notification Preferences</h2>
                        <p class="settings-card-subtitle">Choose when and how you receive CRM updates.</p>

                        <div class="settings-banner settings-banner-info">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <div>Email and desktop delivery require future permission/API/backend support. In-app toggles act as local frontend preferences.</div>
                        </div>

                        <div style="margin-top:16px;">
                            <h3 style="font-size:14px;font-weight:600;color:var(--text-heading);margin-bottom:12px;">Lead Notifications</h3>
                            
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">New Lead Assigned</div>
                                    <div class="settings-toggle-desc">Notify when a new lead is assigned to you.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="notif_leadAssignedInApp" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>

                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Lead Status Changed</div>
                                    <div class="settings-toggle-desc">Notify when one of your leads updates status.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="notif_leadStatusInApp" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>

                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Follow-up Due</div>
                                    <div class="settings-toggle-desc">Alert when a lead follow-up date arrives.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="notif_leadFollowupInApp" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border-divider);">
                            <h3 style="font-size:14px;font-weight:600;color:var(--text-heading);margin-bottom:12px;">Deal &amp; Task Notifications</h3>

                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Deal Assigned</div>
                                    <div class="settings-toggle-desc">Notify when a deal is assigned to your ownership.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="notif_dealAssignedInApp" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>

                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Task Due Soon</div>
                                    <div class="settings-toggle-desc">Send reminders 15 minutes before tasks.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="notif_taskDueInApp" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>

                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Missed Simulated Call</div>
                                    <div class="settings-toggle-desc">Notify when a Softphone call is missed.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="notif_callMissedInApp" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 4. COMPANY PROFILE -->
                <section class="settings-section" id="section-company">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Company Profile</h2>
                        <p class="settings-card-subtitle">Manage organization details and business information.</p>

                        <div class="settings-form-grid">
                            <div class="settings-field-group">
                                <label class="settings-label" for="compName">Company Name</label>
                                <input type="text" class="input-control" id="compName" value="NexFlow CRM">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compWebsite">Website</label>
                                <input type="url" class="input-control" id="compWebsite" value="https://NexFlowcrm.com">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compIndustry">Industry</label>
                                <input type="text" class="input-control" id="compIndustry" value="Software & Technology">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compSize">Company Size</label>
                                <select class="input-control" id="compSize">
                                    <option value="1-10">1-10 employees</option>
                                    <option value="11-50">11-50 employees</option>
                                    <option value="51-200">51-200 employees</option>
                                    <option value="50-200">50-200 employees</option>
                                    <option value="201-500">201-500 employees</option>
                                    <option value="501-1000">501-1000 employees</option>
                                    <option value="1001-5000">1001-5000 employees</option>
                                    <option value="5000+">5000+ employees</option>
                                    <option value="200+">200+ employees</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compPhone">Phone</label>
                                <input type="text" class="input-control" id="compPhone" value="+1 (800) 555-0199">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compEmail">Business Email</label>
                                <input type="email" class="input-control" id="compEmail" value="contact@NexFlowcrm.com">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compCountry">Country</label>
                                <input type="text" class="input-control" id="compCountry" value="United States">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compCity">City</label>
                                <input type="text" class="input-control" id="compCity" value="San Francisco">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compState">State / Province</label>
                                <input type="text" class="input-control" id="compState" value="CA">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compZip">Postal Code</label>
                                <input type="text" class="input-control" id="compZip" value="94105">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compFiscalStart">Fiscal Year Start</label>
                                <select class="input-control" id="compFiscalStart">
                                    <option value="January">January</option>
                                    <option value="April">April</option>
                                    <option value="July">July</option>
                                    <option value="October">October</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="compHours">Business Hours</label>
                                <input type="text" class="input-control" id="compHours" value="09:00 - 17:00 PST">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 5. TEAM DEFAULTS -->
                <section class="settings-section" id="section-team-defaults">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Team Defaults</h2>
                        <p class="settings-card-subtitle">Set default assignees, quota targets and working hours.</p>

                        <div class="settings-form-grid">
                            <div class="settings-field-group">
                                <label class="settings-label" for="tdOwner">Default Lead Owner</label>
                                <select class="input-control" id="tdOwner">
                                    <option value="Olivia Martin">Olivia Martin</option>
                                    <option value="Sarah Chen">Sarah Chen</option>
                                    <option value="James Wu">James Wu</option>
                                    <option value="Alex Rivera">Alex Rivera</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="tdDealOwner">Default Deal Owner</label>
                                <select class="input-control" id="tdDealOwner">
                                    <option value="Olivia Martin">Olivia Martin</option>
                                    <option value="Sarah Chen">Sarah Chen</option>
                                    <option value="James Wu">James Wu</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="tdTaskAssignee">Default Task Assignee</label>
                                <select class="input-control" id="tdTaskAssignee">
                                    <option value="Olivia Martin">Olivia Martin</option>
                                    <option value="Sarah Chen">Sarah Chen</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="tdTeam">Default Team</label>
                                <select class="input-control" id="tdTeam">
                                    <option value="SMB Sales">SMB Sales</option>
                                    <option value="Enterprise Sales">Enterprise Sales</option>
                                    <option value="Account Management">Account Management</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="tdStatus">Default Availability</label>
                                <select class="input-control" id="tdStatus">
                                    <option value="Available">Available</option>
                                    <option value="Busy">Busy</option>
                                    <option value="Away">Away</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="tdHours">Working Hours</label>
                                <input type="text" class="input-control" id="tdHours" value="09:00 - 17:00">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="tdQuotaPeriod">Sales Target Period</label>
                                <select class="input-control" id="tdQuotaPeriod">
                                    <option value="Monthly">Monthly</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Annual">Annual</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="tdQuota">Default Monthly Quota</label>
                                <input type="text" class="input-control" id="tdQuota" value="$50,000">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 6. ROLES PREVIEW --> 
                <section class="settings-section" id="section-roles">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Roles &amp; Permissions Preview</h2>
                        <p class="settings-card-subtitle">Review access levels and role definitions across NexFlow CRM.</p>

                        <div class="settings-banner settings-banner-info">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <div><strong>Database Authorized:</strong> Role access definitions and permissions are stored in MySQL and enforced by the backend permissions engine.</div>
                        </div>

                        <div class="settings-table-wrapper">
                            <table class="crm-table" id="settingsRolesTable">
                                <thead>
                                    <tr>
                                        <th data-protected="true" data-column-id="role-name">Role Name</th>
                                        <th data-column-id="leads-contacts">Leads &amp; Contacts</th>
                                        <th data-column-id="pipeline-deals">Pipeline &amp; Deals</th>
                                        <th data-column-id="reports">Reports</th>
                                        <th data-column-id="team-management">Team Management</th>
                                        <th data-column-id="settings">Settings</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="font-weight:600;color:var(--text-heading);">Administrator</td>
                                        <td>Full Access</td>
                                        <td>Full Access</td>
                                        <td>Full Access</td>
                                        <td>Full Access</td>
                                        <td>Full Access</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight:600;color:var(--text-heading);">Sales Manager</td>
                                        <td>Full Access</td>
                                        <td>Full Access</td>
                                        <td>Full Access</td>
                                        <td>Edit Assigned</td>
                                        <td>View Only</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight:600;color:var(--text-heading);">Sales Representative</td>
                                        <td>Assigned Only</td>
                                        <td>Assigned Only</td>
                                        <td>View Own</td>
                                        <td>No Access</td>
                                        <td>No Access</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight:600;color:var(--text-heading);">Account Executive</td>
                                        <td>Full Access</td>
                                        <td>Full Access</td>
                                        <td>View Team</td>
                                        <td>No Access</td>
                                        <td>No Access</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight:600;color:var(--text-heading);">Sales Operations</td>
                                        <td>Full Access</td>
                                        <td>Full Access</td>
                                        <td>Full Access</td>
                                        <td>View Only</td>
                                        <td>Edit Config</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight:600;color:var(--text-heading);">Viewer</td>
                                        <td>View Only</td>
                                        <td>View Only</td>
                                        <td>View Only</td>
                                        <td>No Access</td>
                                        <td>No Access</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- 7. SALES PIPELINE -->
                <section class="settings-section" id="section-pipeline">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h2 class="settings-card-title">Sales Pipeline Stages</h2>
                                <p class="settings-card-subtitle" style="margin-bottom:0;">Reorder, customize probabilities, or add stage rows.</p>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="btnResetPipeline">Reset to Default</button>
                        </div>

                        <div class="settings-pipeline-list" id="settingsPipelineList">
                            <!-- Populated dynamically via JS -->
                        </div>

                        <button type="button" class="btn btn-secondary btn-sm" id="btnAddPipelineStage">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Stage
                        </button>
                    </div>
                </section>

                <!-- 8. LEAD SETTINGS -->
                <section class="settings-section" id="section-leads">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Lead Management Settings</h2>
                        <p class="settings-card-subtitle">Configure lead scoring, default statuses and sources.</p>

                        <div class="settings-form-grid">
                            <div class="settings-field-group">
                                <label class="settings-label" for="lsDefaultStatus">Default Lead Status</label>
                                <select class="input-control" id="lsDefaultStatus">
                                    <option value="New">New</option>
                                    <option value="Contacted">Contacted</option>
                                    <option value="Qualified">Qualified</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="lsDefaultSource">Default Lead Source</label>
                                <select class="input-control" id="lsDefaultSource">
                                    <option value="Website">Website</option>
                                    <option value="Referral">Referral</option>
                                    <option value="LinkedIn">LinkedIn</option>
                                    <option value="Cold Outreach">Cold Outreach</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="lsDefaultOwner">Default Lead Owner</label>
                                <select class="input-control" id="lsDefaultOwner">
                                    <option value="Olivia Martin">Olivia Martin</option>
                                    <option value="Sarah Chen">Sarah Chen</option>
                                    <option value="James Wu">James Wu</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="lsScoreThreshold">Lead Score Threshold</label>
                                <input type="number" class="input-control" id="lsScoreThreshold" value="70" min="0" max="100">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="lsInactivityDays">Inactivity Threshold (Days)</label>
                                <input type="number" class="input-control" id="lsInactivityDays" value="14" min="1" max="90">
                            </div>
                        </div>

                        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border-divider);">
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Enable Lead Scoring Engine</div>
                                    <div class="settings-toggle-desc">Calculate engagement scores based on lead interactions.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="lsScoreEnabled" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Duplicate Detection Preview</div>
                                    <div class="settings-toggle-desc">Flag existing emails or clean phones when adding leads.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="lsDuplicateDetection" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Automatic Follow-up Reminders</div>
                                    <div class="settings-toggle-desc">Schedule follow-up tasks when lead status changes.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="lsAutoFollowup" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>



                <!-- 9. CUSTOM FIELDS -->
                <section class="settings-section" id="section-custom-fields">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h2 class="settings-card-title">Custom Fields Manager</h2>
                                <p class="settings-card-subtitle" style="margin-bottom:0;">Add custom data fields to CRM entities.</p>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" id="btnOpenCustomFieldDrawer">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Create Custom Field
                            </button>
                        </div>

                        <div class="filter-chips-wrapper" style="margin-bottom:16px;">
                            <button type="button" class="filter-chip settings-cf-tab active" data-object="Leads">Leads</button>
                            <button type="button" class="filter-chip settings-cf-tab" data-object="Contacts">Contacts</button>
                            <button type="button" class="filter-chip settings-cf-tab" data-object="Companies">Companies</button>
                            <button type="button" class="filter-chip settings-cf-tab" data-object="Deals">Deals</button>
                            <button type="button" class="filter-chip settings-cf-tab" data-object="Tasks">Tasks</button>
                        </div>

                        <div class="settings-table-wrapper">
                            <table class="crm-table" id="settingsCustomFieldsTable">
                                <thead>
                                    <tr>
                                        <th data-protected="true" data-column-id="field-label">Field Label</th>
                                        <th data-column-id="target-object">Target Object</th>
                                        <th data-column-id="field-type">Field Type</th>
                                        <th data-column-id="required">Required</th>
                                        <th data-column-id="status">Status</th>
                                        <th data-protected="true" data-column-id="actions">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="settingsCustomFieldsTbody">
                                    <!-- Populated dynamically via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- 10. TASK SETTINGS -->
                <section class="settings-section" id="section-tasks">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Task Management Settings</h2>
                        <p class="settings-card-subtitle">Configure default task priorities, views and reminders.</p>

                        <div class="settings-form-grid">
                            <div class="settings-field-group">
                                <label class="settings-label" for="tsDefaultStatus">Default Task Status</label>
                                <select class="input-control" id="tsDefaultStatus">
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="tsDefaultPriority">Default Priority</label>
                                <select class="input-control" id="tsDefaultPriority">
                                    <option value="High">High</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Low">Low</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="tsDefaultReminder">Default Reminder Time</label>
                                <select class="input-control" id="tsDefaultReminder">
                                    <option value="15 minutes before">15 minutes before</option>
                                    <option value="30 minutes before">30 minutes before</option>
                                    <option value="1 hour before">1 hour before</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="tsDefaultView">Default Tasks View</label>
                                <select class="input-control" id="tsDefaultView">
                                    <option value="list">List View</option>
                                    <option value="board">Kanban Board</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border-divider);">
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Show Completed Tasks</div>
                                    <div class="settings-toggle-desc">Keep completed tasks visible in lists and board.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="tsShowCompleted" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 11. EMAIL PREFERENCES -->
                <section class="settings-section" id="section-email">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Email Preferences</h2>
                        <p class="settings-card-subtitle">Set up default email senders and signatures.</p>

                        <div class="settings-banner settings-banner-info">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <div>CRM email sending, tracking and SMTP require future backend/API integration. Current emails open in the user's default email client.</div>
                        </div>

                        <div class="settings-form-grid">
                            <div class="settings-field-group">
                                <label class="settings-label" for="emailSenderName">Sender Display Name</label>
                                <input type="text" class="input-control" id="emailSenderName" value="Olivia Martin">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="emailReplyTo">Reply-to Email Address</label>
                                <input type="email" class="input-control" id="emailReplyTo" value="olivia.martin@NexFlow.io">
                            </div>
                        </div>

                        <div style="margin-top:16px;">
                            <div class="settings-field-group">
                                <label class="settings-label" for="emailSignature">Email Signature</label>
                                <textarea class="input-control" id="emailSignature" rows="4" style="height:auto;padding:8px 12px;">Best regards,
Olivia Martin
Sales Manager | NexFlow CRM</textarea>
                            </div>
                        </div>

                        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border-divider);">
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Include Signature by Default</div>
                                    <div class="settings-toggle-desc">Append email signature to all new messages.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="emailIncludeSig" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 12. CALLING PREFERENCES -->
                <section class="settings-section" id="section-calling">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Calling &amp; Softphone Preferences</h2>
                        <p class="settings-card-subtitle">Configure settings for the simulated NexFlow Softphone widget.</p>

                        <div class="settings-form-grid">
                            <div class="settings-field-group">
                                <label class="settings-label" for="callCountryCode">Default Country Code</label>
                                <select class="input-control" id="callCountryCode">
                                    <option value="+1">+1 (US / Canada)</option>
                                    <option value="+44">+44 (UK)</option>
                                    <option value="+33">+33 (France)</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="callAvailability">Default Status</label>
                                <select class="input-control" id="callAvailability">
                                    <option value="Available">Available</option>
                                    <option value="Busy">Busy</option>
                                    <option value="Away">Away</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border-divider);">
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Auto-open Call Notes Drawer</div>
                                    <div class="settings-toggle-desc">Automatically open note-taking panel when a call starts.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="callAutoNotes" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Show Floating On Call Widget</div>
                                    <div class="settings-toggle-desc">Keep floating widget visible during active simulated calls.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="callFloatingWidget" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 13. INBOX PREFERENCES -->
                <section class="settings-section" id="section-inbox">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Inbox Preferences</h2>
                        <p class="settings-card-subtitle">Customize conversation views and thread loading behavior.</p>

                        <div class="settings-form-grid">
                            <div class="settings-field-group">
                                <label class="settings-label" for="inboxDefaultFolder">Default Folder</label>
                                <select class="input-control" id="inboxDefaultFolder">
                                    <option value="inbox">Inbox</option>
                                    <option value="assigned">Assigned to Me</option>
                                    <option value="unread">Unread</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="inboxSortOrder">Sort Order</label>
                                <select class="input-control" id="inboxSortOrder">
                                    <option value="newest">Newest First</option>
                                    <option value="oldest">Oldest First</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border-divider);">
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Mark Read on Open</div>
                                    <div class="settings-toggle-desc">Automatically mark unread messages as read when selected.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="inboxMarkReadOnOpen" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">Show Contact Context on Click</div>
                                    <div class="settings-toggle-desc">Open Contact Context panel when clicking user avatars or names.</div>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" id="inboxShowContextOnClick" checked>
                                    <span class="settings-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 14. INTEGRATIONS -->
                <section class="settings-section" id="section-integrations">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Third-Party Integrations</h2>
                        <p class="settings-card-subtitle">Connect external calendar, communication and webhook services.</p>

                        <div class="settings-banner settings-banner-info">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <div>External integrations require credentials, APIs and backend services and are not available in this frontend phase.</div>
                        </div>

                        <div class="settings-integrations-grid">
                            <div class="settings-integration-card" data-integration-key="google_calendar">
                                <div class="settings-integration-top">
                                    <div class="settings-integration-icon" style="color:#4285F4;">G</div>
                                    <span class="status-badge status-proposal" style="font-size:11px;">Not Connected</span>
                                </div>
                                <div class="settings-integration-name">Google Calendar</div>
                                <div class="settings-integration-desc">Sync meetings and sales schedule automatically.</div>
                                <div class="settings-integration-footer">
                                    <span style="font-size:12px;color:var(--text-secondary);">Calendar</span>
                                    <button type="button" class="btn btn-secondary btn-xs" data-integration-btn data-action="connect">Connect</button>
                                </div>
                            </div>

                            <div class="settings-integration-card" data-integration-key="outlook_calendar">
                                <div class="settings-integration-top">
                                    <div class="settings-integration-icon" style="color:#0078D4;">O</div>
                                    <span class="status-badge status-proposal" style="font-size:11px;">Not Connected</span>
                                </div>
                                <div class="settings-integration-name">Outlook Calendar</div>
                                <div class="settings-integration-desc">Integrate Microsoft 365 calendar events.</div>
                                <div class="settings-integration-footer">
                                    <span style="font-size:12px;color:var(--text-secondary);">Calendar</span>
                                    <button type="button" class="btn btn-secondary btn-xs" data-integration-btn data-action="connect">Connect</button>
                                </div>
                            </div>

                            <div class="settings-integration-card" data-integration-key="gmail">
                                <div class="settings-integration-top">
                                    <div class="settings-integration-icon" style="color:#EA4335;">M</div>
                                    <span class="status-badge status-proposal" style="font-size:11px;">Not Connected</span>
                                </div>
                                <div class="settings-integration-name">Gmail Integration</div>
                                <div class="settings-integration-desc">Send and receive customer emails in Inbox.</div>
                                <div class="settings-integration-footer">
                                    <span style="font-size:12px;color:var(--text-secondary);">Email</span>
                                    <button type="button" class="btn btn-secondary btn-xs" data-integration-btn data-action="connect">Connect</button>
                                </div>
                            </div>

                            <div class="settings-integration-card" data-integration-key="whatsapp_business">
                                <div class="settings-integration-top">
                                    <div class="settings-integration-icon" style="color:#25D366;">WA</div>
                                    <span class="status-badge status-proposal" style="font-size:11px;">Not Connected</span>
                                </div>
                                <div class="settings-integration-name">WhatsApp Business API</div>
                                <div class="settings-integration-desc">Connect official WhatsApp Business account.</div>
                                <div class="settings-integration-footer">
                                    <span style="font-size:12px;color:var(--text-secondary);">Messaging</span>
                                    <button type="button" class="btn btn-secondary btn-xs" data-integration-btn data-action="connect">Connect</button>
                                </div>
                            </div>

                            <div class="settings-integration-card" data-integration-key="twilio">
                                <div class="settings-integration-top">
                                    <div class="settings-integration-icon" style="color:#F22F46;">TW</div>
                                    <span class="status-badge status-proposal" style="font-size:11px;">Not Connected</span>
                                </div>
                                <div class="settings-integration-name">Twilio Softphone</div>
                                <div class="settings-integration-desc">Power real telephony calls and SMS messaging.</div>
                                <div class="settings-integration-footer">
                                    <span style="font-size:12px;color:var(--text-secondary);">Calling</span>
                                    <button type="button" class="btn btn-secondary btn-xs" data-integration-btn data-action="connect">Connect</button>
                                </div>
                            </div>

                            <div class="settings-integration-card" data-integration-key="zapier">
                                <div class="settings-integration-top">
                                    <div class="settings-integration-icon" style="color:#FF4A00;">ZP</div>
                                    <span class="status-badge status-proposal" style="font-size:11px;">Not Connected</span>
                                </div>
                                <div class="settings-integration-name">Zapier Webhooks</div>
                                <div class="settings-integration-desc">Automate workflows with over 5,000+ apps.</div>
                                <div class="settings-integration-footer">
                                    <span style="font-size:12px;color:var(--text-secondary);">Automation</span>
                                    <button type="button" class="btn btn-secondary btn-xs" data-integration-btn data-action="connect">Connect</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 15. IMPORT & EXPORT -->
                <section class="settings-section" id="section-import-export">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Data Import (CSV)</h2>
                        <p class="settings-card-subtitle">Preview local CSV file columns and headers. Frontend-only (no server upload).</p>

                        <div class="settings-dropzone" id="csvDropzone">
                            <div class="settings-dropzone-icon">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            </div>
                            <h3 style="font-size:15px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Click or Drag CSV File Here</h3>
                            <p style="font-size:12px;color:var(--text-secondary);">Supports Leads, Contacts and Companies CSV formats up to 5MB.</p>
                            <input type="file" id="csvFileInput" accept=".csv" style="display:none;">
                        </div>

                        <div id="csvImportSummary" style="display:none;margin-top:16px;">
                            <!-- Populated on file select via JS FileReader -->
                        </div>
                    </div>

                    <div class="settings-card">
                        <h2 class="settings-card-title">Data &amp; Backup Export</h2>
                        <p class="settings-card-subtitle">Export current mock datasets to CSV or backup your settings JSON.</p>

                        <div style="display:flex;gap:12px;flex-wrap:wrap;">
                            <button type="button" class="btn btn-secondary btn-md" onclick="window.settingsApp.exportCSV('leads')">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Export Leads (CSV)
                            </button>
                            <button type="button" class="btn btn-secondary btn-md" onclick="window.settingsApp.exportCSV('contacts')">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Export Contacts (CSV)
                            </button>
                            <button type="button" class="btn btn-secondary btn-md" onclick="window.settingsApp.exportCSV('companies')">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Export Companies (CSV)
                            </button>
                            <button type="button" class="btn btn-primary btn-md" onclick="window.settingsApp.exportJSONBackup()">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                Backup Settings (JSON)
                            </button>
                        </div>
                    </div>
                </section>

                <!-- 16. SECURITY PREVIEW -->
                <section class="settings-section" id="section-security">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Security &amp; Authentication Preview</h2>
                        <p class="settings-card-subtitle">Password policy, 2FA and active session management.</p>

                        <div class="settings-banner settings-banner-warning">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <div>Backend authentication required. Credentials and 2FA changes are disabled in this demo phase.</div>
                        </div>

                        <div class="settings-form-grid">
                            <div class="settings-field-group">
                                <label class="settings-label" for="secCurrentPass">Current Password</label>
                                <input type="password" class="input-control" id="secCurrentPass" value="••••••••••••" disabled>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="secNewPass">New Password</label>
                                <input type="password" class="input-control" id="secNewPass" placeholder="Enter new password" disabled>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="secTimeout">Session Timeout</label>
                                <select class="input-control" id="secTimeout" disabled>
                                    <option value="30">30 minutes</option>
                                    <option value="60">1 hour</option>
                                </select>
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-label" for="secPolicy">Password Strength Policy</label>
                                <input type="text" class="input-control" id="secPolicy" value="Strong (8+ chars, numbers, symbols)" disabled>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 17. DATA & PRIVACY -->
                <section class="settings-section" id="section-privacy">
                    <div class="settings-card">
                        <h2 class="settings-card-title">Data &amp; Privacy Control</h2>
                        <p class="settings-card-subtitle">Manage browser demo storage and privacy settings.</p>

                        <div class="settings-toggle-row">
                            <div class="settings-toggle-info">
                                <div class="settings-toggle-title">Clear Local Demo Storage</div>
                                <div class="settings-toggle-desc">Removes all custom local settings and restore defaults.</div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" style="color:#DC2626;" onclick="window.settingsApp.clearLocalDemoData()">Clear Storage</button>
                        </div>
                    </div>
                </section>

                <!-- 18. BILLING PREVIEW -->
                <section class="settings-section" id="section-billing">
                    <!-- Section Header -->
                    <div style="margin-bottom:16px;">
                        <h2 class="settings-card-title" style="margin:0 0 4px 0;">Billing &amp; Plan Preview</h2>
                        <p class="settings-card-subtitle" style="margin:0;">Overview of your subscription plan and active seat usage.</p>
                    </div>

                    <!-- 1. Current Plan Card -->
                    <div class="settings-card" style="margin-bottom:20px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                            <div>
                                <h3 class="settings-card-title" style="margin:0;font-size:16px;">Current Plan</h3>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openManagePlanModal()">Manage Plan</button>
                        </div>

                        <div style="background:var(--bg-body, #F8FAFC);border:1px solid var(--border-card, #E2E8F0);border-radius:var(--radius-lg, 8px);padding:20px;">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                                <div>
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                        <h3 style="font-size:18px;font-weight:700;color:var(--text-heading);margin:0;">NexFlow CRM Pro</h3>
                                        <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:12px;background:#E2E8F0;color:#475569;">Frontend Demo</span>
                                    </div>
                                    <div style="font-size:22px;font-weight:800;color:var(--primary, #2563EB);margin-top:4px;">₹9,999 <span style="font-size:13px;font-weight:500;color:var(--text-secondary);">/ month</span></div>
                                </div>
                                <span class="status-badge status-won" style="font-size:12px;padding:4px 12px;font-weight:600;">Active</span>
                            </div>

                            <!-- Team Seat Progress Bar -->
                            <div style="margin-bottom:16px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;font-size:13px;color:var(--text-heading);">
                                    <span style="font-weight:600;">Team Seats</span>
                                    <span style="font-weight:700;color:var(--primary);">8 of 10 Team Seats Occupied (80%)</span>
                                </div>
                                <div style="width:100%;height:10px;background:#E2E8F0;border-radius:5px;overflow:hidden;">
                                    <div style="width:80%;height:100%;background:var(--primary, #2563EB);border-radius:5px;transition:width 0.3s ease;"></div>
                                </div>
                                <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:12px;color:var(--text-secondary);">
                                    <span>Used Seats: <strong>8</strong></span>
                                    <span>Available Seats: <strong>2</strong></span>
                                </div>
                            </div>

                            <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border-card);padding-top:14px;font-size:13px;">
                                <span style="color:var(--text-secondary);">Next Billing Date:</span>
                                <span style="font-weight:700;color:var(--text-heading);">September 15, 2026</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Billing Summary & Payment Method Grid -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:20px;margin-bottom:20px;">
                        
                        <!-- Billing Summary Card -->
                        <div class="settings-card" style="margin:0;">
                            <h2 class="settings-card-title">Billing Summary</h2>
                            <p class="settings-card-subtitle">Current plan specs and billing interval.</p>
                            
                            <div style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
                                <div style="display:flex;justify-content:space-between;padding-bottom:8px;border-bottom:1px solid var(--border-divider, #F1F5F9);font-size:13px;">
                                    <span style="color:var(--text-secondary);">Plan</span>
                                    <span style="font-weight:700;color:var(--text-heading);">Pro</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding-bottom:8px;border-bottom:1px solid var(--border-divider, #F1F5F9);font-size:13px;">
                                    <span style="color:var(--text-secondary);">Billing Cycle</span>
                                    <span style="font-weight:700;color:var(--text-heading);">Monthly</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding-bottom:8px;border-bottom:1px solid var(--border-divider, #F1F5F9);font-size:13px;">
                                    <span style="color:var(--text-secondary);">Price</span>
                                    <span style="font-weight:700;color:var(--primary, #2563EB);">₹9,999 / month</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:13px;">
                                    <span style="color:var(--text-secondary);">Next Billing</span>
                                    <span style="font-weight:700;color:var(--text-heading);">Sep 15, 2026</span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method Card -->
                        <div class="settings-card" style="margin:0;display:flex;flex-direction:column;justify-content:space-between;">
                            <div>
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                                    <h2 class="settings-card-title" style="margin:0;">Payment Method</h2>
                                    <button type="button" class="btn btn-secondary btn-xs" onclick="openUpdatePaymentModal()">Update</button>
                                </div>
                                <p class="settings-card-subtitle">Active payment card for automated renewal.</p>

                                <div style="display:flex;align-items:center;gap:14px;background:var(--bg-body, #F8FAFC);border:1px solid var(--border-card, #E2E8F0);border-radius:var(--radius-md, 6px);padding:14px;margin-top:16px;">
                                    <div style="width:42px;height:28px;border-radius:4px;background:#1A1F71;color:#FFFFFF;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:11px;letter-spacing:0.05em;">VISA</div>
                                    <div>
                                        <div style="font-weight:700;color:var(--text-heading);font-size:13.5px;" id="pmCardDisplay">Visa •••• 4242</div>
                                        <div style="font-size:11.5px;color:var(--text-secondary);" id="pmExpiryDisplay">Expires 08/28</div>
                                    </div>
                                </div>
                            </div>
                            <div style="font-size:11.5px;color:var(--text-muted);margin-top:14px;">
                                Frontend demonstration only.
                            </div>
                        </div>

                    </div>

                    <!-- 3. Your Plan Includes Card -->
                    <div class="settings-card" style="margin-bottom:20px;">
                        <h2 class="settings-card-title">Your Plan Includes</h2>
                        <p class="settings-card-subtitle">Key features and tools included in the NexFlow CRM Pro tier.</p>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px 24px;margin-top:16px;">
                            <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-heading);font-weight:500;">
                                <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>CRM</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-heading);font-weight:500;">
                                <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Projects</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-heading);font-weight:500;">
                                <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Client Portal</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-heading);font-weight:500;">
                                <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Contracts</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-heading);font-weight:500;">
                                <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Proposals</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-heading);font-weight:500;">
                                <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Estimates</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-heading);font-weight:500;">
                                <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Invoices</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-heading);font-weight:500;">
                                <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Tasks &amp; Calendar</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-heading);font-weight:500;">
                                <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Documents</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-heading);font-weight:500;">
                                <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Support</span>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Billing History Card -->
                    <div class="settings-card" style="margin-bottom:0;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                            <div>
                                <h2 class="settings-card-title" style="margin:0;">Billing History</h2>
                                <p class="settings-card-subtitle" style="margin:4px 0 0 0;">Recent subscription invoice statements and receipts.</p>
                            </div>
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.settingsApp.showToast('Showing complete billing history archive.', 'info')">View All</button>
                        </div>

                        <div style="overflow-x:auto;">
                            <table style="width:100%;border-collapse:collapse;font-size:13px;text-align:left;">
                                <thead>
                                    <tr style="border-bottom:2px solid var(--border-card, #E2E8F0);color:var(--text-secondary);">
                                        <th style="padding:10px 12px;font-weight:600;">Invoice</th>
                                        <th style="padding:10px 12px;font-weight:600;">Date</th>
                                        <th style="padding:10px 12px;font-weight:600;">Amount</th>
                                        <th style="padding:10px 12px;font-weight:600;">Status</th>
                                        <th style="padding:10px 12px;font-weight:600;text-align:right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="border-bottom:1px solid var(--border-divider, #F1F5F9);">
                                        <td style="padding:12px;font-weight:600;color:var(--text-heading);">INV-2026-08</td>
                                        <td style="padding:12px;color:var(--text-body);">Aug 15, 2026</td>
                                        <td style="padding:12px;font-weight:700;color:var(--text-heading);">₹9,999</td>
                                        <td style="padding:12px;"><span class="status-badge status-won" style="font-size:11px;">Paid</span></td>
                                        <td style="padding:12px;text-align:right;">
                                            <button type="button" class="btn btn-ghost btn-xs" onclick="window.settingsApp.showToast('Downloading INV-2026-08.pdf...', 'info')">View / Download</button>
                                        </td>
                                    </tr>
                                    <tr style="border-bottom:1px solid var(--border-divider, #F1F5F9);">
                                        <td style="padding:12px;font-weight:600;color:var(--text-heading);">INV-2026-07</td>
                                        <td style="padding:12px;color:var(--text-body);">Jul 15, 2026</td>
                                        <td style="padding:12px;font-weight:700;color:var(--text-heading);">₹9,999</td>
                                        <td style="padding:12px;"><span class="status-badge status-won" style="font-size:11px;">Paid</span></td>
                                        <td style="padding:12px;text-align:right;">
                                            <button type="button" class="btn btn-ghost btn-xs" onclick="window.settingsApp.showToast('Downloading INV-2026-07.pdf...', 'info')">View / Download</button>
                                        </td>
                                    </tr>
                                    <tr style="border-bottom:1px solid var(--border-divider, #F1F5F9);">
                                        <td style="padding:12px;font-weight:600;color:var(--text-heading);">INV-2026-06</td>
                                        <td style="padding:12px;color:var(--text-body);">Jun 15, 2026</td>
                                        <td style="padding:12px;font-weight:700;color:var(--text-heading);">₹9,999</td>
                                        <td style="padding:12px;"><span class="status-badge status-won" style="font-size:11px;">Paid</span></td>
                                        <td style="padding:12px;text-align:right;">
                                            <button type="button" class="btn btn-ghost btn-xs" onclick="window.settingsApp.showToast('Downloading INV-2026-06.pdf...', 'info')">View / Download</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </section>

                <!-- 19. DANGER ZONE -->
                <section class="settings-section" id="section-danger">
                    <div class="settings-card settings-danger-card">
                        <h2 class="settings-card-title">Danger Zone</h2>
                        <p class="settings-card-subtitle" style="color:#B91C1C;">Irreversible and destructive workspace actions.</p>

                        <div class="settings-danger-row">
                            <div>
                                <div style="font-weight:600;color:#991B1B;">Reset All Settings to Factory Default</div>
                                <div style="font-size:12px;color:#B91C1C;">Restores original factory settings without affecting your business records.</div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" style="color:#DC2626;" onclick="window.settingsApp.resetAllSettings()">Reset All</button>
                        </div>

                        <div class="settings-danger-row">
                            <div>
                                <div style="font-weight:600;color:#991B1B;">Delete Workspace</div>
                                <div style="font-size:12px;color:#B91C1C;">Permanently remove this workspace and all associated data.</div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" style="color:#DC2626;" onclick="window.settingsApp.deleteWorkspace()">Delete Workspace</button>
                        </div>
                    </div>
                </section>

            </div>
        </div>

    </div>
</main>

<!-- Custom Field Right-Side Drawer -->
<div class="settings-drawer" id="customFieldDrawer">
    <div class="settings-drawer-panel">
        <div class="settings-drawer-header">
            <h3 class="settings-drawer-title">Create Custom Field</h3>
            <button type="button" class="modal-close-btn" id="btnCloseCustomFieldDrawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="customFieldForm" style="display:flex;flex-direction:column;flex:1;">
            <div class="settings-drawer-body">
                <div class="settings-field-group" style="margin-bottom:16px;">
                    <label class="settings-label" for="cfLabelInput">Field Label *</label>
                    <input type="text" class="input-control" id="cfLabelInput" placeholder="e.g. Budget Range" required>
                </div>
                <div class="settings-field-group" style="margin-bottom:16px;">
                    <label class="settings-label" for="cfObjectSelect">Target Object *</label>
                    <select class="input-control" id="cfObjectSelect">
                        <option value="Leads">Leads</option>
                        <option value="Contacts">Contacts</option>
                        <option value="Companies">Companies</option>
                        <option value="Deals">Deals</option>
                        <option value="Tasks">Tasks</option>
                    </select>
                </div>
                <div class="settings-field-group" style="margin-bottom:16px;">
                    <label class="settings-label" for="cfTypeSelect">Field Type *</label>
                    <select class="input-control" id="cfTypeSelect">
                        <option value="Text">Text</option>
                        <option value="Text Area">Text Area</option>
                        <option value="Number">Number</option>
                        <option value="Email">Email</option>
                        <option value="Phone">Phone</option>
                        <option value="Date">Date</option>
                        <option value="Date & Time">Date & Time</option>
                        <option value="Dropdown">Dropdown</option>
                        <option value="Multi Select">Multi Select</option>
                        <option value="Checkbox">Checkbox</option>
                        <option value="Radio">Radio</option>
                        <option value="URL">URL</option>
                        <option value="Currency">Currency</option>
                    </select>
                </div>
                <div class="settings-field-group" id="cfOptionsGroup" style="display:none;margin-bottom:16px;">
                    <label class="settings-label" for="cfOptionsInput">Field Options (Comma separated)</label>
                    <input type="text" class="input-control" id="cfOptionsInput" placeholder="Option 1, Option 2, Option 3">
                </div>
                <div class="settings-toggle-row" style="padding:8px 0;">
                    <div class="settings-toggle-info">
                        <div class="settings-toggle-title">Required Field</div>
                        <div class="settings-toggle-desc">Mandatory field when saving records.</div>
                    </div>
                    <label class="settings-switch">
                        <input type="checkbox" id="cfRequiredToggle">
                        <span class="settings-switch-slider"></span>
                    </label>
                </div>
                <div class="settings-toggle-row" style="padding:8px 0;">
                    <div class="settings-toggle-info">
                        <div class="settings-toggle-title">Active Status</div>
                        <div class="settings-toggle-desc">Enable or disable this custom field.</div>
                    </div>
                    <label class="settings-switch">
                        <input type="checkbox" id="cfActiveToggle" checked>
                        <span class="settings-switch-slider"></span>
                    </label>
                </div>
            </div>
            <div class="settings-drawer-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="document.getElementById('customFieldDrawer').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md">Create Field</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Plan Modal -->
<div class="modal-overlay" id="managePlanModal" style="display:none;" onclick="if(event.target===this) closeManagePlanModal()">
    <div class="modal-content" style="max-width:760px;width:90%;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 class="modal-title">Choose Your Plan</h3>
            <button type="button" class="modal-close-btn" onclick="closeManagePlanModal()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" style="padding:20px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:16px;">
                
                <!-- Starter Plan -->
                <div style="border:1px solid var(--border-card, #E2E8F0);border-radius:var(--radius-lg, 8px);padding:18px;display:flex;flex-direction:column;justify-content:space-between;background:#FFFFFF;">
                    <div>
                        <h4 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px 0;">Starter</h4>
                        <div style="font-size:20px;font-weight:800;color:var(--text-heading);margin-bottom:12px;">₹4,999 <span style="font-size:12px;font-weight:400;color:var(--text-secondary);">/ mo</span></div>
                        <ul style="list-style:none;padding:0;margin:0 0 16px 0;font-size:12.5px;color:var(--text-body);display:flex;flex-direction:column;gap:8px;">
                            <li>✓ CRM</li>
                            <li>✓ Projects</li>
                            <li>✓ Invoices</li>
                            <li>✓ Up to 5 Team Members</li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" style="width:100%;" onclick="selectPlanDemo('Starter')">Choose Starter</button>
                </div>

                <!-- Professional Plan (Current Active) -->
                <div style="border:2px solid var(--primary, #2563EB);border-radius:var(--radius-lg, 8px);padding:18px;display:flex;flex-direction:column;justify-content:space-between;background:#F8FAFC;position:relative;">
                    <span class="status-badge status-won" style="position:absolute;top:-10px;right:12px;font-size:10px;padding:2px 8px;">Current Plan</span>
                    <div>
                        <h4 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px 0;">Professional</h4>
                        <div style="font-size:20px;font-weight:800;color:var(--primary, #2563EB);margin-bottom:12px;">₹9,999 <span style="font-size:12px;font-weight:400;color:var(--text-secondary);">/ mo</span></div>
                        <ul style="list-style:none;padding:0;margin:0 0 16px 0;font-size:12.5px;color:var(--text-body);display:flex;flex-direction:column;gap:8px;">
                            <li>✓ CRM</li>
                            <li>✓ Projects</li>
                            <li>✓ Client Portal</li>
                            <li>✓ Contracts</li>
                            <li>✓ Proposals</li>
                            <li>✓ Estimates</li>
                            <li>✓ Invoices</li>
                            <li>✓ Up to 10 Team Members</li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" style="width:100%;" disabled>Current Plan</button>
                </div>

                <!-- Enterprise Plan -->
                <div style="border:1px solid var(--border-card, #E2E8F0);border-radius:var(--radius-lg, 8px);padding:18px;display:flex;flex-direction:column;justify-content:space-between;background:#FFFFFF;">
                    <div>
                        <h4 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px 0;">Enterprise</h4>
                        <div style="font-size:18px;font-weight:800;color:var(--text-heading);margin-bottom:12px;">Custom Pricing</div>
                        <ul style="list-style:none;padding:0;margin:0 0 16px 0;font-size:12.5px;color:var(--text-body);display:flex;flex-direction:column;gap:8px;">
                            <li>✓ Advanced Features</li>
                            <li>✓ Unlimited Team Members</li>
                            <li>✓ Priority Support</li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" style="width:100%;" onclick="selectPlanDemo('Enterprise')">Contact Sales</button>
                </div>

            </div>
        </div>
        <div class="modal-footer" style="padding:14px 20px;border-top:1px solid var(--border-card, #E2E8F0);display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:11.5px;color:var(--text-muted);">Frontend demonstration only.</span>
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeManagePlanModal()">Close</button>
        </div>
    </div>
</div>

<!-- Update Payment Method Modal -->
<div class="modal-overlay" id="updatePaymentModal" style="display:none;" onclick="if(event.target===this) closeUpdatePaymentModal()">
    <div class="modal-content" style="max-width:440px;width:90%;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 class="modal-title">Update Payment Method</h3>
            <button type="button" class="modal-close-btn" onclick="closeUpdatePaymentModal()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="updatePaymentForm" onsubmit="savePaymentMethodDemo(event)">
            <div class="modal-body" style="padding:20px;">
                <div style="margin-bottom:14px;">
                    <label class="settings-label" for="pmCardNumber">Card Number</label>
                    <input type="text" class="input-control" id="pmCardNumber" value="•••• •••• •••• 4242" required>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div>
                        <label class="settings-label" for="pmExpiry">Expiration Date</label>
                        <input type="text" class="input-control" id="pmExpiry" value="08/28" placeholder="MM/YY" required>
                    </div>
                    <div>
                        <label class="settings-label" for="pmCvc">CVC / CVV</label>
                        <input type="password" class="input-control" id="pmCvc" value="•••" placeholder="123" required>
                    </div>
                </div>
                <div style="margin-bottom:6px;">
                    <label class="settings-label" for="pmHolder">Cardholder Name</label>
                    <input type="text" class="input-control" id="pmHolder" value="Demo Account" required>
                </div>
                <div style="font-size:11.5px;color:var(--text-muted);margin-top:12px;">
                    Frontend demonstration only.
                </div>
            </div>
            <div class="modal-footer" style="padding:14px 20px;border-top:1px solid var(--border-card, #E2E8F0);display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeUpdatePaymentModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Payment Method</button>
            </div>
        </form>
    </div>
</div>

<script>
function openManagePlanModal() {
    const modal = document.getElementById("managePlanModal");
    if (modal) {
        modal.style.display = "flex";
        modal.classList.add("show");
    }
}
function closeManagePlanModal() {
    const modal = document.getElementById("managePlanModal");
    if (modal) {
        modal.classList.remove("show");
        modal.style.display = "none";
    }
}

function openUpdatePaymentModal() {
    const modal = document.getElementById("updatePaymentModal");
    if (modal) {
        modal.style.display = "flex";
        modal.classList.add("show");
    }
}
function closeUpdatePaymentModal() {
    const modal = document.getElementById("updatePaymentModal");
    if (modal) {
        modal.classList.remove("show");
        modal.style.display = "none";
    }
}

function selectPlanDemo(planName) {
    if (window.settingsApp && typeof window.settingsApp.showToast === "function") {
        window.settingsApp.showToast("Frontend demo: plan changes are not connected to billing.", "info");
    }
    closeManagePlanModal();
}

function savePaymentMethodDemo(e) {
    e.preventDefault();
    const cardNum = document.getElementById("pmCardNumber").value.trim();
    const expiry = document.getElementById("pmExpiry").value.trim();
    
    const last4 = cardNum.slice(-4) || "4242";
    const cardDisplay = document.getElementById("pmCardDisplay");
    const expiryDisplay = document.getElementById("pmExpiryDisplay");

    if (cardDisplay) cardDisplay.textContent = `Visa •••• ${last4}`;
    if (expiryDisplay) expiryDisplay.textContent = `Expires ${expiry}`;

    if (window.settingsApp && typeof window.settingsApp.showToast === "function") {
        window.settingsApp.showToast("Payment method updated for demonstration session.", "success");
    }
    closeUpdatePaymentModal();
}

document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        closeManagePlanModal();
        closeUpdatePaymentModal();
    }
});
</script>

<!-- Mobile Sticky Save Bar -->
<div class="settings-mobile-save-bar">
    <button type="button" class="btn btn-secondary btn-sm" onclick="window.settingsApp.resetDemoChanges()">Reset</button>
    <button type="button" class="btn btn-primary btn-sm btn-save-settings" onclick="window.settingsApp.saveAllSettings()">Save Changes</button>
</div>

<!-- Embed Mock Data for Client-Side CSV Export -->
<script>
window.SETTINGS_MOCK_DATA = {
    leads: <?php echo json_encode($leads ?? []); ?>,
    contacts: <?php echo json_encode($contacts ?? []); ?>,
    companies: <?php echo json_encode($companies ?? []); ?>
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

