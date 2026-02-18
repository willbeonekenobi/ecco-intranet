ECCO Intranet

Ecco Intranet is a work-in-progress WordPress plugin that integrates Microsoft SharePoint as an intranet backend. It enables WordPress to connect to SharePoint lists, upload files, and will expand into a full intranet/enterprise integration solution.

🚀 The goal is to let WordPress sites leverage SharePoint (and eventually other platforms) for content, collaboration, notifications, project management, and more.

📦 Features
✅ Currently Available

Access SharePoint Lists
Retrieve and display data from SharePoint lists directly in WordPress.

Upload Files to SharePoint
Upload files from WordPress to SharePoint document libraries.

Modular integration structure
The codebase is structured so that additional providers/targets (e.g., SharePoint alternatives) can be added over time.

🛠 Installation
From GitHub

Download the latest release or clone the repo:

git clone https://github.com/willbeonekenobi/ecco-intranet.git


Move the ecco-intranet folder into your WordPress wp-content/plugins/ directory.

Activate the plugin from the WordPress admin dashboard:
Plugins → Installed Plugins → Activate “Ecco Intranet”

Via ZIP Upload

Compress the plugin folder into ecco-intranet.zip.

In the WordPress admin dashboard, go to:
Plugins → Add New → Upload Plugin

Choose the .zip file and click Install Now, then Activate.

⚠️ Ensure your WordPress installation meets the minimum requirements — PHP version compatible with REST and HTTP request support.

🧠 Configuration

After activation, go to Settings → Ecco Intranet.

Enter your Microsoft/SharePoint credentials and tenant configuration.

Save and test the connection.

(Optional) Use Azure App registrations and API scopes for more secure access & delegated permissions.

📌 How It Works

Ecco Intranet leverages SharePoint APIs to:

Query lists,

Upload documents,

Authenticate via OAuth (planned),

And surface SharePoint data within WordPress pages, Gutenberg blocks, or shortcodes.

This gives you a hybrid intranet where WordPress is the front-end and SharePoint is the backend content/collaboration store.

REQUIREMENTS:

Note these are subject to change and work on the version mentioned below:

Wordpress 6.9.1 or later (development started on 6.8)
PHP 8.3

For Microsoft Sharepoint integration you need the following:

A Microsoft 365 tenant

SharePoint Online enabled

Global Admin access (for initial setup only)

You do not need:

Entra ID Premium

Azure subscription

Enterprise licensing

📈 Roadmap
🔄 In Progress

🔹 Leave Management System
A dedicated UI for employees to submit and view leave requests, backed by SharePoint lists.

🔹 Notifications Engine
Real-time or scheduled notifications to Teams or WhatsApp when workflows or list items change.

🔹 Shared Calendar Sync
Integrate WordPress events with SharePoint/Outlook calendars.

🔹 Project Tracking Dashboard
A project management interface pulling data from SharePoint and displaying progress in WP.

📅 Planned Features

🚀 Multi-Platform Support
Add adapters for other intranet backends (e.g., Confluence, Google Workspace sites, Notion databases, etc).

🎯 SSO Integration
Seamless login with Microsoft/Azure AD, optionally scaling to other providers (OAuth / SAML).

🔔 Advanced Notifications
Push/Email/SMS delivery channels with templating.

📊 Role/Permission Sync
Mirror SharePoint roles and permissions to WordPress capabilities.

🧩 Custom Blocks & Widgets
Gutenberg blocks for dynamic intranet elements like lists, calendars, and dashboards.

📋 Admin UI Enhancements
Visual list configurators, schema mappers, and sync tools for managing SharePoint content inside WP.

📣 Contributing

Contributions, feedback, feature requests, and bug reports are welcome!
Please open an issue or submit a pull request.

Fork the repository.

Create your feature branch (git checkout -b feature/...).

Commit your changes.

Push to your branch and open a pull request.

📓 License

Distributed under the MIT License.
See LICENSE for details.

📫 Contact

If you have questions or want to collaborate:
Email: (your email)
Repo: https://github.com/willbeonekenobi/ecco-intranet
