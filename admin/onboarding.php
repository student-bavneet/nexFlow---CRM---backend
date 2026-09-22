<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $setupComplete = (bool)nexflow_db()->query("SELECT id FROM organizations WHERE setup_completed = 1 LIMIT 1")->fetchColumn();
} catch (Throwable $e) {
    $setupComplete = false;
}

if ($setupComplete) {
    if (!empty($_SESSION['user_id'])) {
        header('Location: index.php');
    } else {
        header('Location: admin-login.php');
    }
    exit;
}

$page_title = "Build Your CRM — Welcome Onboarding";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Build Your CRM — NexFlow CRM</title>
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/onboarding.css">
    <script src="assets/js/universal-crm.js"></script>
</head>
<body class="onboarding-body">

    <!-- Header Navigation -->
    <header class="ob-header-nav">
        <a href="admin-login.php" class="ob-brand">
            <div class="ob-brand-icon">L</div>
            <span>NexFlow CRM</span>
        </a>
        <div style="display:flex; align-items:center; gap:12px;">
            <span style="font-size:12px; color:var(--text-muted);">Configured for your business</span>
            <a href="admin-login.php" class="btn btn-ghost btn-sm" style="color:var(--text-muted);">Exit Setup</a>
        </div>
    </header>

    <!-- Progress Stepper (6 Clean Steps) -->
    <nav class="ob-stepper">
        <div class="ob-step-item active" data-step="1">
            <div class="ob-step-num">1</div>
            <span class="ob-step-name">01 Business</span>
        </div>
        <div class="ob-step-divider"></div>
        <div class="ob-step-item" data-step="2">
            <div class="ob-step-num">2</div>
            <span class="ob-step-name">02 Industry</span>
        </div>
        <div class="ob-step-divider"></div>
        <div class="ob-step-item" data-step="3">
            <div class="ob-step-num">3</div>
            <span class="ob-step-name">03 Modules</span>
        </div>
        <div class="ob-step-divider"></div>
        <div class="ob-step-item" data-step="4">
            <div class="ob-step-num">4</div>
            <span class="ob-step-name">04 Setup</span>
        </div>
        <div class="ob-step-divider"></div>
        <div class="ob-step-item" data-step="5">
            <div class="ob-step-num">5</div>
            <span class="ob-step-name">05 Preview</span>
        </div>
        <div class="ob-step-divider"></div>
        <div class="ob-step-item" data-step="6">
            <div class="ob-step-num">6</div>
            <span class="ob-step-name">06 Ready</span>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="ob-main-container">
        <div class="ob-card">

            <!-- STEP 1: BUSINESS INFORMATION -->
            <div class="ob-step-pane active" id="obStep1">
                <div class="ob-pane-header">
                    <h1 class="ob-title">WELCOME TO NexFlow CRM</h1>
                    <p class="ob-subtitle">Build a CRM that works the way your business works. Let's start with your company details.</p>
                </div>

                <div class="ob-form-grid">
                    <div class="ob-field-group">
                        <label class="ob-label" for="obBizName">Business Name *</label>
                        <input type="text" class="input-control" id="obBizName" value="" placeholder="e.g. Apex Solutions Inc.">
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obBizEmail">Business Email *</label>
                        <input type="email" class="input-control" id="obBizEmail" value="" placeholder="e.g. hello@company.com">
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obBizPhone">Business Phone</label>
                        <input type="text" class="input-control" id="obBizPhone" value="" placeholder="e.g. +1 555 019 2831">
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obBizWebsite">Website</label>
                        <input type="url" class="input-control" id="obBizWebsite" value="" placeholder="https://example.com">
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obBizCountry">Country</label>
                        <select class="input-control" id="obBizCountry">
                            <option value="">Select country</option>
                            <option value="United States">United States</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="Canada">Canada</option>
                            <option value="Australia">Australia</option>
                            <option value="Germany">Germany</option>
                            <option value="India">India</option>
                        </select>
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obBizState">State / Region</label>
                        <input type="text" class="input-control" id="obBizState" value="" placeholder="State / Region">
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obBizCity">City</label>
                        <input type="text" class="input-control" id="obBizCity" value="" placeholder="City">
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obBizZip">ZIP / Postal Code</label>
                        <input type="text" class="input-control" id="obBizZip" value="" placeholder="ZIP / Postal Code">
                    </div>
                    <div class="ob-field-group span-2">
                        <label class="ob-label" for="obBizAddress">Street Address</label>
                        <input type="text" class="input-control" id="obBizAddress" value="" placeholder="Street address">
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obBizCurrency">Currency</label>
                        <select class="input-control" id="obBizCurrency">
                            <option value="USD ($)">USD ($)</option>
                            <option value="EUR (€)">EUR (€)</option>
                            <option value="GBP (£)">GBP (£)</option>
                            <option value="CAD ($)">CAD ($)</option>
                            <option value="AUD ($)">AUD ($)</option>
                            <option value="INR (₹)">INR (₹)</option>
                        </select>
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obBizTimezone">Time Zone</label>
                        <select class="input-control" id="obBizTimezone">
                            <option value="">Select time zone</option>
                            <option value="America/Los_Angeles">Pacific Time (UTC-8)</option>
                            <option value="America/New_York">Eastern Time (UTC-5)</option>
                            <option value="Europe/London">London (UTC+0)</option>
                            <option value="Asia/Kolkata">India (UTC+5:30)</option>
                        </select>
                    </div>
                    <div class="ob-field-group span-2">
                        <label class="ob-label">Business Logo</label>
                        <label class="ob-logo-dropzone" for="obBizLogo" style="cursor:pointer;">
                            <div class="ob-logo-preview" id="obLogoPreviewBox">LOGO</div>
                            <div style="text-align:left;">
                                <div style="font-size:13px; font-weight:600; color:var(--primary);">Upload Logo Image</div>
                                <div style="font-size:11px; color:var(--text-muted);">PNG, JPG or SVG (Max 2MB)</div>
                            </div>
                            <input type="file" id="obBizLogo" accept="image/png,image/jpeg,image/svg+xml" hidden>
                        </label>
                    </div>

                    <div class="ob-field-group span-2" style="margin-top:8px; padding-top:20px; border-top:1px solid #E2E8F0;">
                        <div style="font-size:15px; font-weight:700; color:var(--text-heading); margin-bottom:4px;">Administrator Account</div>
                        <div style="font-size:12px; color:var(--text-muted); margin-bottom:16px;">These credentials will be used to sign in after setup.</div>
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obAdminName">Admin Name *</label>
                        <input type="text" class="input-control" id="obAdminName" placeholder="e.g. John Doe" autocomplete="name">
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obAdminEmail">Admin Login Email *</label>
                        <input type="email" class="input-control" id="obAdminEmail" placeholder="e.g. admin@company.com" autocomplete="email">
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obAdminPassword">Password *</label>
                        <input type="password" class="input-control" id="obAdminPassword" placeholder="Minimum 8 characters" autocomplete="new-password">
                    </div>
                    <div class="ob-field-group">
                        <label class="ob-label" for="obAdminPasswordConfirm">Confirm Password *</label>
                        <input type="password" class="input-control" id="obAdminPasswordConfirm" placeholder="Re-enter password" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <!-- STEP 2: SELECT INDUSTRY -->
            <div class="ob-step-pane" id="obStep2">
                <div class="ob-pane-header">
                    <h2 class="ob-title">What type of business do you run?</h2>
                    <p class="ob-subtitle">Select your industry. We will automatically prepare optimal default workflows and tools.</p>
                </div>

                <div class="ob-industry-grid" id="obIndustryGrid">
                    <!-- Populated dynamically via JS -->
                </div>

                <div id="obOtherIndustryWrap" style="display:none; margin-top:20px;">
                    <label class="ob-label" for="obOtherIndustryInput">Enter Your Custom Industry Name</label>
                    <input type="text" class="input-control" id="obOtherIndustryInput" placeholder="e.g. Solar Energy Installation, Yacht Brokerage, Event Planning">
                </div>
            </div>

            <!-- STEP 3: CHOOSE WHAT TO MANAGE -->
            <div class="ob-step-pane" id="obStep3">
                <div class="ob-pane-header">
                    <h2 class="ob-title">What would you like to manage?</h2>
                    <p class="ob-subtitle">Select the tools your business needs. You can enable or disable these anytime later.</p>
                </div>

                <div class="ob-module-grid" id="obModuleGrid">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- STEP 4: RECOMMENDED SETUP SUMMARY -->
            <div class="ob-step-pane" id="obStep4">
                <div class="ob-pane-header">
                    <h2 class="ob-title">Your CRM Setup Is Ready</h2>
                    <p class="ob-subtitle">We've prepared your CRM based on your business and the tools you selected.</p>
                </div>

                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:24px; max-width:640px; margin:0 auto;">
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
                            <span style="font-size:13px; color:var(--text-muted);">Business Name</span>
                            <strong id="obSetupBizName" style="font-size:14px; color:var(--text-heading);">Apex Solutions Inc.</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
                            <span style="font-size:13px; color:var(--text-muted);">Selected Industry</span>
                            <strong id="obSetupIndustry" style="font-size:14px; color:var(--text-heading);">Real Estate &amp; Property</strong>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:700; color:var(--text-heading); margin-bottom:10px;">Your CRM Includes:</div>
                            <div id="obSetupModulesList" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:13px; color:var(--text-body);">
                                <!-- Dynamically populated via JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 5: CRM PREVIEW -->
            <div class="ob-step-pane" id="obStep5">
                <div class="ob-pane-header">
                    <h2 class="ob-title">Preview Your CRM</h2>
                    <p class="ob-subtitle">Here is what your personalized workspace navigation and dashboard will look like.</p>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:20px;">
                        <h4 style="font-weight:700; font-size:15px; margin-bottom:12px; color:var(--text-heading);">🏢 Business Profile</h4>
                        <div style="font-size:13px; display:flex; flex-direction:column; gap:6px;">
                            <div>Business: <strong id="obPrevBizName">Apex Solutions Inc.</strong></div>
                            <div>Industry: <strong id="obPrevIndustry">Real Estate</strong></div>
                            <div>Location: <strong id="obPrevLocation">San Francisco, CA</strong></div>
                            <div>Currency: <strong id="obPrevCurrency">USD ($)</strong></div>
                        </div>
                    </div>

                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:20px;">
                        <h4 style="font-weight:700; font-size:15px; margin-bottom:12px; color:var(--text-heading);">⚡ Navigation &amp; Workspace</h4>
                        <div style="font-size:13px; display:flex; flex-direction:column; gap:6px;">
                            <div>Enabled Tools: <strong id="obPrevModCount">10 Active Modules</strong></div>
                            <div>Default Status: <strong style="color:#10B981;">Ready to Launch</strong></div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:20px; background:#F1F5F9; border-radius:10px; padding:16px; text-align:center;">
                    <span style="font-weight:600; font-size:13px; color:var(--text-secondary);">Everything looks ready! Click "Continue" to launch your workspace.</span>
                </div>
            </div>

            <!-- STEP 6: YOUR CRM IS READY -->
            <div class="ob-step-pane" id="obStep6">
                <div style="text-align:center; padding:20px 0;">
                    <div style="width:72px; height:72px; border-radius:50%; background:#10B981; color:#FFF; font-size:36px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px auto; box-shadow:0 10px 25px rgba(16,185,129,0.3);">
                        ✓
                    </div>
                    <h2 class="ob-title" style="font-size:26px;">Your CRM Is Ready</h2>
                    <p class="ob-subtitle" style="font-size:15px; max-width:500px; margin:0 auto 24px auto;">Your workspace has been configured based on your business and selected tools.</p>

                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:24px; max-width:560px; margin:0 auto 30px auto; text-align:left;">
                        <div style="font-weight:700; font-size:15px; color:var(--text-heading); margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                            <span>🚀 Setup Summary</span>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:10px; font-size:13.5px;">
                            <div style="display:flex; justify-content:space-between;">
                                <span style="color:var(--text-muted);">Organization:</span>
                                <strong id="obFinalBizName">Apex Solutions Inc.</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between;">
                                <span style="color:var(--text-muted);">Industry:</span>
                                <strong id="obFinalIndustry">Real Estate</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between;">
                                <span style="color:var(--text-muted);">Enabled Modules:</span>
                                <strong id="obFinalModules">10 Active Modules</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="ob-footer-bar">
                <button type="button" class="btn-ob-back" id="btnObBack" disabled>&larr; Back</button>
                <button type="button" class="btn-ob-next" id="btnObNext">Continue &rarr;</button>
            </div>

        </div>
    </main>

    <!-- Create Custom Module Modal -->
    <div class="ob-modal-backdrop" id="obCustomModuleModal" style="display:none;" onclick="if(event.target===this) window.obApp.closeCustomModuleModal();">
        <div class="ob-modal-dialog">
            <div class="ob-modal-header">
                <div>
                    <h3 class="ob-modal-title">Create Custom Module</h3>
                    <p class="ob-modal-subtitle">Create a module that matches your business needs.</p>
                </div>
                <button type="button" class="ob-modal-close" onclick="window.obApp.closeCustomModuleModal()">&times;</button>
            </div>

            <div class="ob-modal-body">
                <div id="obCustomModuleError" style="display:none; background:#FEE2E2; border:1px solid #FCA5A5; color:#DC2626; padding:10px 14px; border-radius:6px; font-size:12.5px; margin-bottom:14px; font-weight:600;">
                    Please enter a module name.
                </div>

                <div class="ob-field-group" style="margin-bottom:16px;">
                    <label class="ob-label" for="obCustomModuleName">Module Name *</label>
                    <input type="text" class="input-control" id="obCustomModuleName" placeholder="e.g. Properties, Candidates, Services">
                </div>

                <div class="ob-field-group" style="margin-bottom:16px;">
                    <label class="ob-label" for="obCustomModuleDesc">Description</label>
                    <textarea class="input-control" id="obCustomModuleDesc" rows="3" placeholder="Briefly describe what this module is used for..." style="height:auto; padding:8px 12px;"></textarea>
                </div>

                <div class="ob-field-group">
                    <label class="ob-label">Choose Icon</label>
                    <div class="ob-icon-picker" id="obIconPicker">
                        <button type="button" class="ob-icon-option active" data-icon="✨" onclick="window.obApp.selectCustomModuleIcon(this, '✨')">✨</button>
                        <button type="button" class="ob-icon-option" data-icon="🏢" onclick="window.obApp.selectCustomModuleIcon(this, '🏢')">🏢</button>
                        <button type="button" class="ob-icon-option" data-icon="💼" onclick="window.obApp.selectCustomModuleIcon(this, '💼')">💼</button>
                        <button type="button" class="ob-icon-option" data-icon="👤" onclick="window.obApp.selectCustomModuleIcon(this, '👤')">👤</button>
                        <button type="button" class="ob-icon-option" data-icon="📦" onclick="window.obApp.selectCustomModuleIcon(this, '📦')">📦</button>
                        <button type="button" class="ob-icon-option" data-icon="🏡" onclick="window.obApp.selectCustomModuleIcon(this, '🏡')">🏡</button>
                        <button type="button" class="ob-icon-option" data-icon="🚗" onclick="window.obApp.selectCustomModuleIcon(this, '🚗')">🚗</button>
                        <button type="button" class="ob-icon-option" data-icon="🎓" onclick="window.obApp.selectCustomModuleIcon(this, '🎓')">🎓</button>
                        <button type="button" class="ob-icon-option" data-icon="📅" onclick="window.obApp.selectCustomModuleIcon(this, '📅')">📅</button>
                        <button type="button" class="ob-icon-option" data-icon="📄" onclick="window.obApp.selectCustomModuleIcon(this, '📄')">📄</button>
                        <button type="button" class="ob-icon-option" data-icon="🛍️" onclick="window.obApp.selectCustomModuleIcon(this, '🛍️')">🛍️</button>
                        <button type="button" class="ob-icon-option" data-icon="⭐" onclick="window.obApp.selectCustomModuleIcon(this, '⭐')">⭐</button>
                    </div>
                </div>
            </div>

            <div class="ob-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.obApp.closeCustomModuleModal()">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.obApp.submitCustomModule()">Create Module</button>
            </div>
        </div>
    </div>

    <script src="assets/js/modal-system.js"></script>
    <script src="assets/js/onboarding.js"></script>
</body>
</html>



