# LeadFlow CRM - PHP Frontend Application

A complete Sales and Lead Management CRM frontend application built with **HTML5, CSS3, Vanilla JavaScript, PHP 8+**, and **Chart.js**.

## Technology Stack
- **Frontend**: Semantic HTML5, Vanilla CSS3 (Custom Design Tokens), Vanilla JavaScript (ES6+)
- **Backend/Templating**: PHP 8+
- **Data Source**: Centralized PHP Arrays (`data/mock-data.php`)
- **Charts**: Chart.js 4.x (via CDN)
- **Drag & Drop**: Native HTML5 Drag and Drop API

---

## Folder Structure

```
leadflow-crm-php/
├── index.php                # Dashboard page (KPIs, Recent Leads, Analytics Chart, Pipeline Summary)
├── leads.php                # Leads Management page (Search, Status Tabs, Data Table, Add Modal, Detail Drawer)
├── pipeline.php             # Sales Pipeline Kanban page (Stage columns, HTML5 Drag & Drop, Live Stat Calculations)
├── contacts.php             # Secondary CRM navigation stub
├── companies.php            # Secondary CRM navigation stub
├── tasks.php                # Secondary CRM navigation stub
├── calendar.php             # Secondary CRM navigation stub
├── inbox.php                # Secondary CRM navigation stub
├── reports.php              # Secondary CRM navigation stub
├── team.php                 # Secondary CRM navigation stub
├── settings.php             # Secondary CRM navigation stub
├── help.php                 # Secondary CRM navigation stub
├── includes/
│   ├── header.php           # Shared HTML head, Google Fonts (Inter), Chart.js CDN, CSS links
│   ├── sidebar.php          # Reusable dark navy fixed navigation sidebar
│   ├── topbar.php           # Reusable top navigation bar with quick add, search, and notifications
│   └── footer.php           # Shared footer scripts & closing tags
├── data/
│   └── mock-data.php        # Centralized PHP data arrays ($leads, $kanban_stages, $activity_feed, $notifications)
├── assets/
│   ├── css/
│   │   ├── variables.css    # CSS variables for colors, typography, spacing, radii, shadows
│   │   ├── style.css        # CSS reset, layout grid, topbar, sidebar
│   │   ├── components.css   # Buttons, badges, tables, modals, drawers, cards, scorebar
│   │   └── responsive.css   # Breakpoint styles for Desktop, Tablet, and Mobile
│   ├── js/
│   │   ├── app.js           # Shared UI logic (sidebar drawer, quick add, notifications dropdown)
│   │   ├── dashboard.js     # Chart.js initialization & dashboard analytics
│   │   ├── leads.js         # Leads search, status tabs filter, checkbox select, drawer & modals
│   │   └── pipeline.js      # Native HTML5 Drag & Drop Kanban board with real-time total updates
│   └── images/
└── README.md                # Documentation & XAMPP setup instructions
```

---

## XAMPP Setup Instructions

1. **Locate XAMPP `htdocs` directory**:
   - On Windows: `C:\xampp\htdocs\`
   - Copy or verify the folder `LeadFlow CRM Design System/leadflow-crm-php` is placed inside `C:\xampp\htdocs\`.

2. **Start Apache Server**:
   - Open **XAMPP Control Panel**.
   - Click **Start** next to **Apache**.

3. **Access Application in Browser**:
   - Open your web browser and navigate to:
     ```
     http://localhost/LeadFlow%20CRM%20Design%20System/leadflow-crm-php/
     ```
   - Alternatively, if using PHP Built-in web server, run:
     ```powershell
     cd "c:\xampp\htdocs\LeadFlow CRM Design System\leadflow-crm-php"
     php -S localhost:8000
     ```
     and open `http://localhost:8000` in your browser.

---

## Verified Features

- **Fixed Dark Navy Sidebar (`#101828`)**: Includes active link highlights (`rgba(37,99,235,0.15)`), badge counts, and user profile (`Olivia Martin`).
- **White Top Navigation**: Search input, Quick Add menu, notifications dropdown with unread badge, and user dropdown.
- **Dashboard**: 4 KPI cards with trend indicators, recent leads table, stage progress bars, activity timeline, and interactive Chart.js revenue analytics line chart.
- **Leads Page**:
  - Live search input and status tabs filter (All, New, Contacted, Qualified, Proposal, Won, Lost).
  - Selectable row checkboxes with select-all and bulk delete action bar.
  - Score bars with dynamic color indicator (Green >= 80, Orange >= 55, Red < 55).
  - Row action menus (View, Edit, Delete).
  - Working "Add Lead" modal dynamically appending new leads.
  - Functional right-side Lead Detail drawer showing full lead contact info and action buttons.
- **Sales Pipeline**:
  - 5 Kanban columns (Prospect, Qualified, Proposal, Negotiation, Closed Won).
  - Drag-and-drop powered by native HTML5 Drag and Drop API (`draggable="true"`).
  - Real-time recalculation of deal count and total dollar sum per stage without page reloads.
- **Responsive Layout**:
  - Desktop: Fixed sidebar, full topbar, multi-column grid, horizontal Kanban board.
  - Tablet: Collapsible sidebar, 2-column KPI grid, scrollable tables.
  - Mobile: Overlay drawer sidebar, stacked cards, full-width modal forms, scrollable Kanban.


## Database-backed Onboarding (Phase 1)

The onboarding wizard now uses MySQL for its installation state and configuration catalog.

1. Import `../database.sql` in phpMyAdmin.
2. Update `../config/database.php` if your XAMPP MySQL credentials differ.
3. Open `admin/admin-login.php`. On a fresh installation it automatically redirects to `onboarding.php`.
4. Complete the 6-step setup and create the administrator credentials.
5. The installer creates the organization, administrator account, selected modules, pipeline stages and settings in MySQL, starts a PHP session, and opens the dashboard.
6. On later visits, the login page is shown and the saved credentials are required.

Business data is no longer stored in onboarding localStorage. Industry/module catalogs are loaded from MySQL and the final setup is committed in a database transaction.
