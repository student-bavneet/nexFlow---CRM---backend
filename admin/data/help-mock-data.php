<?php
/**
 * NexFlow CRM Centralized Help & Knowledge Base Mock Data
 */

$help_categories = [
    [
        "id" => "cat-getting-started",
        "name" => "Getting Started",
        "desc" => "Set up your workspace, invite team members, and learn core CRM basics.",
        "icon" => "rocket",
        "count" => 8
    ],
    [
        "id" => "cat-dashboard-reports",
        "name" => "Dashboard & Reports",
        "desc" => "Analyze sales metrics, conversion rates, and revenue performance.",
        "icon" => "chart",
        "count" => 6
    ],
    [
        "id" => "cat-leads-contacts",
        "name" => "Leads & Contacts",
        "desc" => "Import, qualify, filter, and maintain clean lead directories.",
        "icon" => "users",
        "count" => 12
    ],
    [
        "id" => "cat-companies",
        "name" => "Companies",
        "desc" => "Manage organization records, parent accounts, and associated deals.",
        "icon" => "building",
        "count" => 5
    ],
    [
        "id" => "cat-pipeline",
        "name" => "Sales Pipeline",
        "desc" => "Track kanban deal stages, win probabilities, and pipeline values.",
        "icon" => "git-branch",
        "count" => 9
    ],
    [
        "id" => "cat-tasks-calendar",
        "name" => "Tasks & Calendar",
        "desc" => "Schedule follow-up activities, calls, meetings, and sales events.",
        "icon" => "calendar",
        "count" => 7
    ],
    [
        "id" => "cat-inbox",
        "name" => "Inbox & Communication",
        "desc" => "Unified customer conversations across email, simulated call, and WhatsApp.",
        "icon" => "mail",
        "count" => 10
    ],
    [
        "id" => "cat-softphone",
        "name" => "Softphone Simulation",
        "desc" => "Using the floating dialer, simulated call logs, and notes drawer.",
        "icon" => "phone-call",
        "count" => 6
    ],
    [
        "id" => "cat-team",
        "name" => "Team Management",
        "desc" => "Workload distribution, member roles, and permission previews.",
        "icon" => "user-check",
        "count" => 5
    ],
    [
        "id" => "cat-settings",
        "name" => "Settings & Preferences",
        "desc" => "Customizing sales pipeline stages, custom fields, and local prefs.",
        "icon" => "settings",
        "count" => 11
    ],
    [
        "id" => "cat-import-export",
        "name" => "Data Import & Export",
        "desc" => "CSV format guidelines, file upload previews, and client-side exports.",
        "icon" => "download",
        "count" => 4
    ],
    [
        "id" => "cat-troubleshooting",
        "name" => "Troubleshooting",
        "desc" => "Resolving display issues, local storage resets, and asset loading.",
        "icon" => "alert-circle",
        "count" => 8
    ]
];

$help_articles = [
    [
        "id" => "art-001",
        "title" => "Getting started with NexFlow CRM",
        "category" => "Getting Started",
        "categoryId" => "cat-getting-started",
        "readTime" => "4 min read",
        "helpfulCount" => 142,
        "popular" => true,
        "updated" => "Aug 02, 2025",
        "intro" => "Welcome to NexFlow CRM! This guide walks you through navigating the dashboard, managing customer relationships, and tracking sales performance in your workspace.",
        "keywords" => ["getting started", "welcome", "basics", "overview", "onboarding"],
        "toc" => [
            "1. Navigating the Interface",
            "2. Managing Leads and Pipeline",
            "3. Using Communication Tools",
            "4. Personalizing Preferences"
        ],
        "sections" => [
            [
                "heading" => "1. Navigating the Interface",
                "content" => "NexFlow CRM features a fixed dark navigation sidebar on the left and a top application bar. Use the sidebar to jump between Dashboard, Leads, Sales Pipeline, Contacts, Companies, Tasks, Calendar, Inbox, Reports, Team, and Settings."
            ],
            [
                "heading" => "2. Managing Leads and Pipeline",
                "content" => "Access the <strong>Leads</strong> page to view, search, and filter incoming prospects. When a lead advances, move them into your drag-and-drop <strong>Sales Pipeline</strong> to manage deals through Prospect, Qualified, Proposal, Negotiation, and Closed stages."
            ],
            [
                "heading" => "3. Using Communication Tools",
                "content" => "Connect with leads directly from contact rows or the global <strong>Softphone</strong>. Launch simulated phone calls, compose WhatsApp messages via wa.me, or prepare mailto emails without leaving the CRM."
            ],
            [
                "heading" => "4. Personalizing Preferences",
                "content" => "Visit <strong>Settings</strong> to customize pipeline stages, create custom data fields, set default owners, and configure notification preferences saved directly to your browser's local storage."
            ]
        ],
        "tip" => "Pro Tip: Press <code>/</code> anywhere on the Help page to instantly focus the search bar and find guides.",
        "note" => "Note: All lead data and interactions in this demonstration run entirely within your local web browser.",
        "related" => ["art-002", "art-003", "art-006"]
    ],
    [
        "id" => "art-002",
        "title" => "How to create and qualify a lead",
        "category" => "Leads & Contacts",
        "categoryId" => "cat-leads-contacts",
        "readTime" => "3 min read",
        "helpfulCount" => 98,
        "popular" => true,
        "updated" => "Jul 29, 2025",
        "intro" => "Learn how to record prospective buyers, assign lead scores, update qualification status, and transition qualified leads into active pipeline deals.",
        "keywords" => ["leads", "create lead", "qualify", "score", "status"],
        "toc" => [
            "1. Adding a New Lead",
            "2. Lead Qualification Criteria",
            "3. Updating Lead Status"
        ],
        "sections" => [
            [
                "heading" => "1. Adding a New Lead",
                "content" => "Click the <strong>+ Add Lead</strong> button on the Leads page. Fill in the contact's name, company, email, phone number, estimated deal value, and lead source (e.g. Website, Referral, LinkedIn)."
            ],
            [
                "heading" => "2. Lead Qualification Criteria",
                "content" => "Review the lead's engagement score (0–100). Higher scores indicate strong purchasing intent based on budget alignment, decision-maker status, and activity frequency."
            ],
            [
                "heading" => "3. Updating Lead Status",
                "content" => "Use the lead drawer or table dropdown to transition status from <em>New</em> to <em>Contacted</em>, <em>Qualified</em>, or <em>Proposal</em>."
            ]
        ],
        "tip" => "You can search leads by company, owner name, or email directly from the toolbar search input.",
        "note" => "Required fields include Lead Name and Company Name.",
        "related" => ["art-001", "art-003", "art-010"]
    ],
    [
        "id" => "art-003",
        "title" => "Understanding sales pipeline stages",
        "category" => "Sales Pipeline",
        "categoryId" => "cat-pipeline",
        "readTime" => "5 min read",
        "helpfulCount" => 115,
        "popular" => true,
        "updated" => "Aug 01, 2025",
        "intro" => "The Sales Pipeline visualizes your revenue forecast across Kanban columns representing sales milestone stages.",
        "keywords" => ["pipeline", "kanban", "deal", "stages", "forecast", "win probability"],
        "toc" => [
            "1. Default Pipeline Stages",
            "2. Moving Deals (Drag & Drop)",
            "3. Customizing Stage Probabilities"
        ],
        "sections" => [
            [
                "heading" => "1. Default Pipeline Stages",
                "content" => "NexFlow CRM includes 6 default pipeline stages:<br>• <strong>Prospect (10%)</strong> — Initial interest<br>• <strong>Qualified (30%)</strong> — Needs & budget confirmed<br>• <strong>Proposal (60%)</strong> — Quote or proposal delivered<br>• <strong>Negotiation (80%)</strong> — Contract terms under review<br>• <strong>Closed Won (100%)</strong> — Deal successfully closed<br>• <strong>Closed Lost (0%)</strong> — Deal lost or canceled."
            ],
            [
                "heading" => "2. Moving Deals (Drag & Drop)",
                "content" => "Click and drag any deal card horizontally between column containers to update its sales stage. The stage deal count and total column revenue update immediately."
            ],
            [
                "heading" => "3. Customizing Stage Probabilities",
                "content" => "To adjust default percentages or add custom pipeline stages, navigate to <strong>Settings &gt; Sales Pipeline</strong>."
            ]
        ],
        "tip" => "Click any deal card to open the Deal Detail Drawer and view communication history or add notes.",
        "note" => "Total weighted pipeline value is calculated automatically by multiplying deal value by win probability.",
        "related" => ["art-001", "art-002", "art-008"]
    ],
    [
        "id" => "art-004",
        "title" => "How to use the Contacts directory",
        "category" => "Leads & Contacts",
        "categoryId" => "cat-leads-contacts",
        "readTime" => "3 min read",
        "helpfulCount" => 76,
        "popular" => false,
        "updated" => "Jul 25, 2025",
        "intro" => "Store and organize individual buyer profiles, decision-makers, and business contact cards.",
        "keywords" => ["contacts", "directory", "people", "email", "phone"],
        "toc" => [
            "1. Contact Cards & Details",
            "2. Linking Contacts to Companies"
        ],
        "sections" => [
            [
                "heading" => "1. Contact Cards & Details",
                "content" => "The Contacts page presents individual customer records equipped with direct action buttons for Call, Email, and WhatsApp messaging."
            ],
            [
                "heading" => "2. Linking Contacts to Companies",
                "content" => "Assign each contact to an existing Company record to automatically group stakeholders under client accounts."
            ]
        ],
        "tip" => "Clicking a contact's avatar opens the quick Contact Context drawer anywhere in the CRM.",
        "note" => "Contacts can be exported to CSV at any time from Settings > Import & Export.",
        "related" => ["art-002", "art-005", "art-007"]
    ],
    [
        "id" => "art-005",
        "title" => "Creating and managing follow-up tasks",
        "category" => "Tasks & Calendar",
        "categoryId" => "cat-tasks-calendar",
        "readTime" => "4 min read",
        "helpfulCount" => 88,
        "popular" => true,
        "updated" => "Jul 31, 2025",
        "intro" => "Never miss a customer follow-up. Set task due dates, priorities, and task types linked to specific leads or deals.",
        "keywords" => ["tasks", "todo", "followup", "calendar", "reminders", "priority"],
        "toc" => [
            "1. Creating a Task",
            "2. Task Types & Priorities",
            "3. List vs Board Views"
        ],
        "sections" => [
            [
                "heading" => "1. Creating a Task",
                "content" => "Click <strong>+ Add Task</strong> on the Tasks page. Enter title, due date, time, task type (Call, Email, WhatsApp, Meeting, Follow-up), priority (High, Medium, Low), and assignee."
            ],
            [
                "heading" => "2. Task Types & Priorities",
                "content" => "Categorize activities by icon to prioritize urgent follow-ups. High-priority tasks are highlighted with red urgency badges."
            ],
            [
                "heading" => "3. List vs Board Views",
                "content" => "Toggle between compact Table view and status-grouped Kanban Board view (Pending, In Progress, Completed)."
            ]
        ],
        "tip" => "Check off tasks directly from the list to mark them complete and trigger progress updates.",
        "note" => "Task reminders trigger in-app notifications based on your settings.",
        "related" => ["art-001", "art-006", "art-007"]
    ],
    [
        "id" => "art-006",
        "title" => "Using the frontend Softphone simulation",
        "category" => "Softphone Simulation",
        "categoryId" => "cat-softphone",
        "readTime" => "3 min read",
        "helpfulCount" => 124,
        "popular" => true,
        "updated" => "Aug 03, 2025",
        "intro" => "The global Softphone dialer widget simulates outbound and incoming phone calls, timer tracking, and quick note taking.",
        "keywords" => ["softphone", "phone", "dialer", "call", "simulate", "widget"],
        "toc" => [
            "1. Opening the Softphone",
            "2. Dialing & In-Call Timer",
            "3. Logging Call Notes"
        ],
        "sections" => [
            [
                "heading" => "1. Opening the Softphone",
                "content" => "Click the floating Phone icon at the bottom-right of any page or click any phone number link across the CRM."
            ],
            [
                "heading" => "2. Dialing & In-Call Timer",
                "content" => "Use the numeric keypad to enter a phone number or pick a recent contact. Press the green Call button to start a simulated call with active timer counter."
            ],
            [
                "heading" => "3. Logging Call Notes",
                "content" => "During or immediately after a call, use the attached Call Notes panel to record discussion summaries saved locally."
            ]
        ],
        "tip" => "You can minimize the active call into the floating On-Call widget while continuing to browse other CRM pages.",
        "note" => "Real telephone connections require integration with a telephony provider like Twilio.",
        "related" => ["art-001", "art-007", "art-008"]
    ],
    [
        "id" => "art-007",
        "title" => "Opening WhatsApp and Email from NexFlow",
        "category" => "Inbox & Communication",
        "categoryId" => "cat-inbox",
        "readTime" => "3 min read",
        "helpfulCount" => 92,
        "popular" => true,
        "updated" => "Jul 30, 2025",
        "intro" => "Connect with prospects using official web protocol links for WhatsApp Web and native email applications.",
        "keywords" => ["whatsapp", "email", "communication", "inbox", "mailto", "wa.me"],
        "toc" => [
            "1. Launching WhatsApp Messages",
            "2. Sending Emails via App",
            "3. Unified Inbox Workspace"
        ],
        "sections" => [
            [
                "heading" => "1. Launching WhatsApp Messages",
                "content" => "Clicking a WhatsApp action opens a clean <code>wa.me/&lt;number&gt;</code> link with pre-filled message text in a new browser tab. You press Send in WhatsApp Web."
            ],
            [
                "heading" => "2. Sending Emails via App",
                "content" => "Clicking an Email action triggers a standard <code>mailto:</code> handler, populating your desktop's default email client (e.g. Outlook, Apple Mail, Gmail web)."
            ],
            [
                "heading" => "3. Unified Inbox Workspace",
                "content" => "Review all customer message streams, simulated threads, and contact context panels inside the <strong>Inbox</strong> page."
            ]
        ],
        "tip" => "You can customize email signatures and reply-to defaults under Settings > Email Preferences.",
        "note" => "Direct automated email sending without opening your email app requires future SMTP or API integration.",
        "related" => ["art-006", "art-008", "art-010"]
    ],
    [
        "id" => "art-008",
        "title" => "Creating sales reports & revenue analytics",
        "category" => "Dashboard & Reports",
        "categoryId" => "cat-dashboard-reports",
        "readTime" => "4 min read",
        "helpfulCount" => 105,
        "popular" => true,
        "updated" => "Aug 04, 2025",
        "intro" => "Generate real-time Chart.js visualizations for revenue performance, pipeline velocity, team quotas, and lead conversions.",
        "keywords" => ["reports", "analytics", "charts", "revenue", "conversion", "kpi"],
        "toc" => [
            "1. KPI Performance Metrics",
            "2. Interactive Chart.js Graphs",
            "3. Filtering Date Ranges"
        ],
        "sections" => [
            [
                "heading" => "1. KPI Performance Metrics",
                "content" => "The Reports page displays top KPI summary cards: Total Revenue ($486.2K), Win Rate (68%), Average Deal Size ($24.5K), and Sales Cycle Length (18 days)."
            ],
            [
                "heading" => "2. Interactive Chart.js Graphs",
                "content" => "Explore monthly revenue trends, pipeline breakdown, lead acquisition channels, and individual team member performance."
            ],
            [
                "heading" => "3. Filtering Date Ranges",
                "content" => "Use the top toolbar filter to toggle analytics between <em>This Month</em>, <em>This Quarter</em>, <em>Year to Date</em>, and <em>Custom Range</em>."
            ]
        ],
        "tip" => "Hover over any chart data point to view detailed tooltips with exact figures and percentages.",
        "note" => "Report analytics reflect the centralized PHP mock dataset in real time.",
        "related" => ["art-001", "art-003", "art-009"]
    ],
    [
        "id" => "art-009",
        "title" => "Managing team members and role previews",
        "category" => "Team Management",
        "categoryId" => "cat-team",
        "readTime" => "3 min read",
        "helpfulCount" => 64,
        "popular" => false,
        "updated" => "Aug 05, 2025",
        "intro" => "Add team profiles, monitor individual sales quotas, reassign workloads, and inspect role permission matrices.",
        "keywords" => ["team", "members", "roles", "permissions", "workload", "quota"],
        "toc" => [
            "1. Team Roster & Availability",
            "2. Reassigning Member Workloads",
            "3. Roles & Permissions Matrix"
        ],
        "sections" => [
            [
                "heading" => "1. Team Roster & Availability",
                "content" => "The Team page presents member cards with real-time availability badges (Online, Busy, Away, Offline) and target progress bars."
            ],
            [
                "heading" => "2. Reassigning Member Workloads",
                "content" => "Use the <strong>Reassign Workload</strong> drawer to transfer open deals and active leads between sales representatives."
            ],
            [
                "heading" => "3. Roles & Permissions Matrix",
                "content" => "Review configured access privileges for Administrator, Sales Manager, Sales Rep, Account Exec, and Viewer roles."
            ]
        ],
        "tip" => "Toggle between List View and Grid View on the Team toolbar to suit your display preference.",
        "note" => "Role permissions in this phase serve as frontend demonstration previews.",
        "related" => ["art-001", "art-008", "art-010"]
    ],
    [
        "id" => "art-010",
        "title" => "Importing contacts from a CSV file",
        "category" => "Data Import & Export",
        "categoryId" => "cat-import-export",
        "readTime" => "4 min read",
        "helpfulCount" => 110,
        "popular" => true,
        "updated" => "Aug 02, 2025",
        "intro" => "Import existing spreadsheets of leads, contacts, or companies using client-side CSV file parsing and column header validation.",
        "keywords" => ["csv", "import", "export", "spreadsheet", "data", "file"],
        "toc" => [
            "1. Preparing Your CSV File",
            "2. Drag & Drop File Upload",
            "3. Exporting CRM Datasets"
        ],
        "sections" => [
            [
                "heading" => "1. Preparing Your CSV File",
                "content" => "Ensure your CSV file contains column headers in the first row: <code>Name</code>, <code>Email</code>, <code>Phone</code>, <code>Company</code>, <code>Value</code>, and <code>Source</code>."
            ],
            [
                "heading" => "2. Drag & Drop File Upload",
                "content" => "Navigate to <strong>Settings &gt; Import &amp; Export</strong>. Drag your CSV file onto the dropzone. The FileReader instantly parses headers and displays a local record summary."
            ],
            [
                "heading" => "3. Exporting CRM Datasets",
                "content" => "Click <strong>Export Leads (CSV)</strong> or <strong>Export Contacts (CSV)</strong> to generate client-side CSV file downloads directly in your browser."
            ]
        ],
        "tip" => "You can also export a complete JSON backup of all your saved settings from the Import & Export section.",
        "note" => "CSV imports in this phase perform local browser parsing without transferring files to a remote server.",
        "related" => ["art-002", "art-004", "art-007"]
    ]
];

$help_tutorials = [
    [
        "id" => "tut-001",
        "title" => "NexFlow CRM Overview",
        "desc" => "Complete tour of dashboard KPI cards, navigation sidebar, top search, and page layouts.",
        "duration" => "5:30",
        "category" => "Getting Started"
    ],
    [
        "id" => "tut-002",
        "title" => "Managing & Qualifying Leads",
        "desc" => "Step-by-step guide to capturing lead forms, scoring prospects, and updating statuses.",
        "duration" => "4:15",
        "category" => "Leads & Contacts"
    ],
    [
        "id" => "tut-003",
        "title" => "Mastering the Sales Pipeline",
        "desc" => "Drag-and-drop deal management, stage probability calculations, and deal creation.",
        "duration" => "6:45",
        "category" => "Sales Pipeline"
    ],
    [
        "id" => "tut-004",
        "title" => "Tasks, Calendar & Activity Reminders",
        "desc" => "Setting up follow-up schedules, sync views, and priority task lists.",
        "duration" => "3:50",
        "category" => "Tasks & Calendar"
    ],
    [
        "id" => "tut-005",
        "title" => "Inbox, WhatsApp & Softphone",
        "desc" => "Using simulated phone calls, wa.me messaging links, and unified inbox threads.",
        "duration" => "7:10",
        "category" => "Communication"
    ],
    [
        "id" => "tut-006",
        "title" => "Sales Reports & Analytics Deep Dive",
        "desc" => "Analyzing revenue growth charts, channel metrics, and exporting performance data.",
        "duration" => "5:00",
        "category" => "Reports"
    ]
];

$help_faqs = [
    [
        "q" => "Is this CRM connected to a database?",
        "a" => "No. This application is currently running in a frontend demonstration phase using static PHP mock-data arrays and client-side <code>localStorage</code>. No MySQL or server database connection is attached."
    ],
    [
        "q" => "Are changes saved permanently?",
        "a" => "Settings adjustments, custom fields, and demo support requests are stored in your web browser's <code>localStorage</code>. They persist across page reloads in this browser until local storage is cleared."
    ],
    [
        "q" => "Can the Softphone make real calls?",
        "a" => "The Softphone dialer simulates call initiation, timer counters, and call note logging. Making actual phone calls over telephone networks requires integration with a telephony backend provider like Twilio."
    ],
    [
        "q" => "Can NexFlow send WhatsApp messages automatically?",
        "a" => "Clicking WhatsApp actions opens an official <code>wa.me</code> link in a new browser tab with pre-filled message text. The user must press Send in WhatsApp Web. Automated sending requires the WhatsApp Business API."
    ],
    [
        "q" => "Can NexFlow send email directly?",
        "a" => "Email actions open your operating system's default email client (e.g. Outlook, Apple Mail, Gmail web) via standard <code>mailto:</code> links. Automated SMTP sending requires backend integration."
    ],
    [
        "q" => "Are Google and Outlook calendars connected?",
        "a" => "Not in this frontend phase. External calendar integration cards under Settings show 'Not Connected'. Real-time 2-way calendar sync requires OAuth API credentials."
    ],
    [
        "q" => "Do role settings provide real access control?",
        "a" => "Role definitions (Admin, Manager, Rep) serve as visual design previews. Enforcement of security rules requires server-side authentication and session checks."
    ],
    [
        "q" => "How can I import contacts?",
        "a" => "Go to <strong>Settings &gt; Import &amp; Export</strong>. You can drag and drop a CSV file to preview headers and record counts locally via JavaScript FileReader."
    ],
    [
        "q" => "Where are frontend preferences stored?",
        "a" => "All user preferences are stored under namespaced keys (e.g. <code>NexFlow_settings_*</code>) in your browser's local HTML5 storage."
    ],
    [
        "q" => "How can I reset demo data?",
        "a" => "You can clear custom preferences and restore default mock data by clicking <strong>Clear Storage</strong> under Settings &gt; Data &amp; Privacy or Danger Zone."
    ]
];

$help_troubleshooting = [
    [
        "id" => "tb-001",
        "title" => "Page displays a white screen",
        "problem" => "The page fails to render and shows a blank white screen.",
        "steps" => [
            "Open your browser Developer Tools (F12 or Ctrl+Shift+I) and check the <strong>Console</strong> tab for JavaScript errors.",
            "Verify that your local PHP server (e.g. XAMPP Apache or PHP CLI server) is actively running.",
            "Ensure the URL path matches your local project directory (e.g. <code>http://localhost/NexFlow%20CRM%20Design%20System/NexFlow-crm-php/help.php</code>)."
        ]
    ],
    [
        "id" => "tb-002",
        "title" => "JavaScript controls do not respond",
        "problem" => "Buttons, tabs, or drawers do not open when clicked.",
        "steps" => [
            "Check if script execution is blocked by browser extensions or ad blockers.",
            "Confirm that <code>assets/js/help.js</code> and shared scripts loaded successfully (HTTP 200) in the Network tab.",
            "Try performing a hard refresh (Ctrl+F5 or Cmd+Shift+R) to bypass cached JavaScript files."
        ]
    ],
    [
        "id" => "tb-003",
        "title" => "CSS changes do not appear",
        "problem" => "Styling updates or layout fixes are not taking effect.",
        "steps" => [
            "Check the DevTools Sources tab to confirm the server is serving the updated <code>assets/css/help.css</code>.",
            "Ensure style rules are properly namespaced beneath <code>.help-page</code>.",
            "Clear browser cache or append a version query parameter (e.g. <code>?v=1.1</code>) to the stylesheet link."
        ]
    ],
    [
        "id" => "tb-004",
        "title" => "Page has horizontal scrolling",
        "problem" => "A horizontal scrollbar appears at the bottom of the viewport.",
        "steps" => [
            "Inspect container elements in DevTools for fixed pixel widths exceeding the available viewport width.",
            "Ensure flexible containers use <code>min-width: 0</code> and <code>box-sizing: border-box</code>.",
            "Verify that grid template columns use responsive fractions (e.g. <code>minmax(0, 1fr)</code>)."
        ]
    ],
    [
        "id" => "tb-005",
        "title" => "LocalStorage demo data needs resetting",
        "problem" => "Saved settings or custom demo fields need to be reset to factory defaults.",
        "steps" => [
            "Go to <strong>Settings &gt; Data &amp; Privacy</strong> and click <strong>Clear Storage</strong>.",
            "Alternatively, open DevTools > Application > Local Storage and remove keys starting with <code>NexFlow_</code>.",
            "Refresh the page to reload default PHP mock data state."
        ]
    ]
];

$help_checklist_defaults = [
    ["id" => "chk-1", "title" => "Review the Dashboard", "desc" => "Explore KPI cards, recent activity streams, and sales metrics.", "url" => "index.php", "done" => false],
    ["id" => "chk-2", "title" => "Add or import leads", "desc" => "Create a new prospect or test local CSV file import.", "url" => "leads.php", "done" => false],
    ["id" => "chk-3", "title" => "Create a deal", "desc" => "Add a deal card to the Kanban Sales Pipeline.", "url" => "pipeline.php", "done" => false],
    ["id" => "chk-4", "title" => "Add contacts & companies", "desc" => "Organize decision-makers and client accounts.", "url" => "contacts.php", "done" => false],
    ["id" => "chk-5", "title" => "Create a follow-up task", "desc" => "Schedule a call, email, or meeting task.", "url" => "tasks.php", "done" => false],
    ["id" => "chk-6", "title" => "Explore Reports & Analytics", "desc" => "View Chart.js revenue graphs and channel breakdowns.", "url" => "reports.php", "done" => false],
    ["id" => "chk-7", "title" => "Review Team & Settings", "desc" => "Customize pipeline stages, custom fields, and team roles.", "url" => "settings.php", "done" => false]
];

