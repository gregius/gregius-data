# Gregius Data - Dashboard Overview

[Editorial: Feature: Gregius Data Dashboard | Pages: 1 | Total words: ~720 | Sections: 8 (H2: 7, H3: 3, H4: 0) | Screenshots needed: 2 | Doc category: /docs/gregius-data/ | Audience: Administrator]

## Overview

This feature enables **site administrators** to manage Gregius Data plugin features from a single dashboard in the WordPress admin by providing tabbed access to Models, Connections, Sync, Vectors, Prompts, Search, and Logs.

[SRS: IF-FR-01, IF-FR-03]

### Prerequisites

- Administrator access to the WordPress admin panel
- Gregius Data plugin installed and activated
- Modern browser with JavaScript enabled

---

## In this page

- [Overview](#overview)
  - [Prerequisites](#prerequisites)
- [Access the Dashboard](#access-the-dashboard)
- [Dashboard Tabs Overview](#dashboard-tabs-overview)
- [How the Dashboard Works](#how-the-dashboard-works)
  - [Startup and Connectivity Check](#startup-and-connectivity-check)
  - [Navigation Between Tabs](#navigation-between-tabs)
  - [State Management](#state-management)
- [Error Handling and Troubleshooting](#error-handling-and-troubleshooting)
- [Known Limitations](#known-limitations)
- [Next Steps](#next-steps)

---

## Access the Dashboard

Navigate to the **Gregius Data** menu in the WordPress admin sidebar. The dashboard appears as the main admin page for the plugin.

<!-- IMAGE: WordPress admin sidebar showing the Gregius Data menu item -->

[SRS: IF-FR-01]

---

## Dashboard Tabs Overview

The dashboard organizes features into tabs. Each tab corresponds to a feature area:

- **Models** — Manage AI model configurations and settings.
- **Connections** — View and manage data source connections with active or inactive status.
- **Sync** — Configure and monitor data synchronization operations.
- **Vectors** — Manage vector embeddings and vector store settings.
- **Prompts** — Configure prompt templates and settings.
- **Search** — Manage AI site search settings and indexing.
- **Logs** — View plugin activity and operation logs.

Each tab loads its content from the Gregius Data REST API and displays status indicators for connectivity and data availability.

<!-- IMAGE: dashboard showing the tabs bar with the Connections tab active -->

[SRS: IF-FR-03, IF-FR-06]

---

## How the Dashboard Works

### Startup and Connectivity Check

When the dashboard loads, it performs an automatic connectivity check against the WordPress REST API. During this check the dashboard shows a loading state. If the connection succeeds, the tabs become available for use. If it fails, an error notice appears on screen.

[SRS: IF-FR-04, IF-FR-05]

### Navigation Between Tabs

Click any tab to switch between feature pages. Some areas of the plugin can trigger tab navigation programmatically, ensuring consistent cross-feature workflows without leaving the dashboard.

[SRS: IF-FR-07]

### State Management

The dashboard uses shared state stores to keep data consistent across tabs. Settings, connection records, logs, and selection state remain in sync as you navigate between feature areas.

[SRS: IF-FR-06, IF-DR-02, IF-DR-03]

---

## Error Handling and Troubleshooting

The dashboard handles errors at multiple levels:

- **Startup failures** — If the dashboard cannot initialize, diagnostic information is logged and a failure signal is emitted for support debugging.
- **API errors** — REST API errors returned by the server are displayed as visible notices in the dashboard interface.
- **Missing container** — If the dashboard container element is missing from the page, the plugin attempts to create a fallback container and render the dashboard.

[SRS: IF-OR-01, IF-OR-02, IF-OR-03, IF-QR-04]

### Quick Troubleshooting

| Issue | What to check |
|---|---|
| Dashboard does not appear | Confirm the Gregius Data menu is visible in the admin sidebar |
| Tabs show loading state without resolving | Check that the WordPress REST API is accessible and you are logged in |
| Tab content does not switch | Refresh the page and try navigating again |
| Error notices on load | Check browser console for details and confirm API routes are registered |

---

## Known Limitations

- Not all backend features have dedicated dashboard pages yet. Some capabilities are available through the REST API or WP-CLI but lack a visual interface surface in the current dashboard.
- The dashboard requires JavaScript. It does not function in environments with scripting disabled.

[SRS: IF-OR-05]

---

## Next Steps

**Local:**
- [Dashboard Tabs Overview](#dashboard-tabs-overview)
- [Error Handling and Troubleshooting](#error-handling-and-troubleshooting)

**Global:**
- [REST API Reference](/docs/rest-api/)
- [WP-CLI Commands](/docs/wpcli/)

[SRS: IF-FR-08, IF-QR-03]
