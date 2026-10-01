# SEO AGENCYOS

## ALL-IN-ONE SEO AUTOMATION + AGENCY MANAGEMENT + CLIENT SUBSCRIPTION SaaS

You are a senior SaaS architect, Laravel/PHP developer, MySQL database architect, SEO engineer, technical SEO specialist, web crawler engineer, AI engineer, UI/UX designer, security engineer, DevOps engineer, billing engineer, and testing engineer.

Build a complete production-ready commercial SaaS named:

# SEO AgencyOS

Tagline:

**One Platform. Complete SEO Operations.**

The platform is designed for digital marketing agencies that manage:

* SEO clients
* SEO employees
* Multiple websites
* SEO projects
* Technical SEO
* On-page SEO
* Keywords
* Rankings
* Content
* Backlinks
* Competitors
* SEO audits
* Tasks
* Automation
* Reports
* Client portals
* Client SEO-service subscriptions
* SaaS subscriptions
* AI-assisted SEO operations

The goal is to make SEO AgencyOS the agency's **central SEO operating system**, so employees do not need to switch between multiple SEO management tools for normal day-to-day work.

---

# 1. CRITICAL DEVELOPMENT RULES

Do NOT create a demo.

Do NOT create fake functionality.

Do NOT create buttons that do nothing.

Do NOT create fake SEO scores.

Do NOT generate fake ranking data.

Do NOT claim external API data exists when it does not.

Do NOT hard-code API keys.

Do NOT expose API keys in frontend code.

Do NOT make external APIs mandatory when the functionality can reasonably work internally.

Build self-contained functionality wherever possible.

Use external APIs only where they provide data/functionality that cannot reasonably be reproduced internally.

DeepSeek should be the primary optional AI provider.

If DeepSeek is not configured, the main SEO platform must continue functioning.

Every major feature must have:

* Database
* Backend
* UI
* Validation
* Permissions
* Error handling
* Logging where appropriate
* Tenant isolation
* Real working functionality

Build module-by-module and test each module before continuing.

---

# 2. TECHNOLOGY STACK

Backend:

* Laravel latest stable version compatible with PHP 8.3+
* PHP 8.3+
* MySQL 8+
* Laravel Queue
* Laravel Scheduler
* Laravel Events
* Laravel Listeners
* Laravel Notifications
* Laravel Policies
* Laravel Gates
* REST API architecture

Frontend:

* Blade
* Tailwind CSS
* Alpine.js
* AJAX / Fetch API
* Chart.js

Infrastructure compatibility:

* cPanel
* Apache
* Nginx
* VPS
* Shared hosting where technically possible

Optional:

* Redis
* Supervisor

Do not make Redis mandatory for basic installation.

---

# 3. DESIGN SYSTEM

Professional commercial SaaS design.

Primary style:

* White
* Black
* Orange
* Light gray
* Dark sidebar
* Clean cards
* Professional tables
* Modern charts
* Rounded components
* Minimal animations

The interface must look like a premium commercial SaaS product, not a basic admin panel.

Responsive:

* Desktop
* Laptop
* Tablet
* Mobile

Every page should have:

* Breadcrumb
* Page title
* Description
* Main action
* Search
* Filters
* Pagination
* Empty state
* Loading state
* Error state
* Success notification

---

# 4. TWO SUBSCRIPTION SYSTEMS

The platform must support TWO completely separate subscription concepts.

## A. SaaS Subscription

This controls the agency's subscription to SEO AgencyOS.

Example:

Agency
→ Starter
→ Professional
→ Agency
→ Enterprise

## B. Client SEO Subscription

This controls the agency's SEO service period for each client.

Example:

Client
→ SEO Monthly Plan
→ Start Date
→ Expiry Date
→ Renewal
→ Grace Period
→ Expired

These must never be mixed together.

---

# 5. MULTI-TENANT ARCHITECTURE

SEO AgencyOS is a multi-tenant SaaS.

Structure:

Platform
|
├── Agency 1
│   ├── Users
│   ├── Clients
│   ├── Projects
│   ├── Websites
│   ├── SEO data
│   └── Subscriptions
│
├── Agency 2
│   ├── Users
│   ├── Clients
│   ├── Projects
│   ├── Websites
│   ├── SEO data
│   └── Subscriptions

All tenant-owned tables must contain:

agency_id

Absolutely prevent cross-agency data access.

Use:

* Middleware
* Policies
* Gates
* Query scopes

Never rely only on frontend restrictions.

---

# 6. USER ROLES

Create complete RBAC.

## SUPER ADMIN

Complete platform control:

* Agencies
* Users
* Clients
* Plans
* Subscriptions
* Payments
* Features
* API settings
* AI settings
* SEO engine
* Crawler
* Billing
* Branding
* White-label
* Email
* Storage
* Queue
* Cron
* Security
* Backups
* Logs
* Maintenance
* License
* System settings

## AGENCY OWNER

Controls:

* Agency
* Employees
* Clients
* Projects
* SEO operations
* Reports
* Client subscriptions
* Agency billing

## SEO MANAGER

Controls:

* SEO projects
* Employees
* Tasks
* Audits
* Keywords
* Backlinks
* Reports

## SEO EXECUTIVE

Can:

* View assigned projects
* Run audits
* Manage keywords
* Manage backlinks
* Complete tasks
* Add notes
* Upload files

## CONTENT WRITER

Can:

* Content calendar
* Content briefs
* Content tasks
* AI assistance
* Content optimization

## DEVELOPER

Can:

* Technical SEO
* Technical tasks
* Website issues
* Fix tracking

## CLIENT

Can access only their own portal:

* Dashboard
* SEO reports
* Rankings
* Backlinks
* Tasks
* Project status
* Documents

---

# 7. SUPER ADMIN DASHBOARD

Create:

/super-admin

Dashboard widgets:

* Total Agencies
* Active Agencies
* Trial Agencies
* Suspended Agencies
* Total Users
* Total Clients
* Total Websites
* Total SEO Projects
* Active SaaS Subscriptions
* Expired SaaS Subscriptions
* Monthly Revenue
* AI Usage
* API Usage
* Crawl Jobs
* Failed Jobs
* System Health

Menu:

Dashboard
Agencies
Users
Plans
Subscriptions
Payments
Features
Clients
SEO Settings
AI Settings
API Settings
Crawler Settings
Email
Storage
Notifications
Cron
Queue
Security
Logs
Backups
Branding
White Label
License
Database
Maintenance
System Settings

---

# 8. AGENCY DASHBOARD

Show:

* Active Clients
* Active SEO Projects
* Employees
* Today's Tasks
* Completed Tasks
* Pending Tasks
* Overdue Tasks
* Average SEO Score
* Keyword Movement
* Backlinks
* Audit Issues
* Reports
* Client Expiries

Add charts:

* SEO score history
* Ranking movement
* Backlink growth
* Task completion
* Client subscription status

---

# 9. CLIENT MANAGEMENT

Client fields:

* Name
* Company
* Email
* Phone
* WhatsApp
* Website
* Industry
* Country
* State
* City
* Target locations
* Logo
* Notes
* Status

Client dashboard:

SEO Score
Keywords
Rankings
Backlinks
Audit Issues
Tasks
Content
Competitors
Reports
Subscription
Activity

---

# 10. CLIENT SEO SUBSCRIPTION SYSTEM

Every SEO client can have an independent SEO service subscription.

Fields:

* Client
* Agency
* Plan
* Start Date
* Expiry Date
* Billing Cycle
* Price
* Status
* Auto Renewal
* Grace Period
* Renewal Date
* Notes

Statuses:

* Trial
* Active
* Renewal Soon
* Expiring
* Grace Period
* Expired
* Suspended
* Cancelled

---

# 11. CLIENT EXPIRY COUNTER

Show subscription counter throughout the system.

Example:

ACTIVE

23 DAYS REMAINING

Started:
01 September 2026

Expires:
24 October 2026

Display a visual progress bar.

For short periods:

2 DAYS 14 HOURS REMAINING

For expired:

EXPIRED

Thresholds must be configurable from Super Admin.

Default:

30+ days:
Active

15–30:
Renewal Soon

7–14:
Expiring Soon

1–6:
Urgent

0:
Expired

---

# 12. CLIENT SUBSCRIPTION DASHBOARD

Show:

Plan
Price
Start date
Expiry date
Days remaining
Billing cycle
Auto renewal
Payment status
Renewal history
Invoices
Grace period

Actions:

Renew
Change Plan
Extend Expiry
Cancel
Suspend
Reactivate

Permission-controlled.

---

# 13. CLIENT EXPIRY NOTIFICATIONS

Automatically generate:

30-day reminder
15-day reminder
7-day reminder
3-day reminder
1-day reminder
Expiry-day notification

Channels:

* In-app
* Email
* Optional WhatsApp integration

Make reminder thresholds configurable.

---

# 14. EXPIRY AUTOMATION

When subscription expires:

Check configuration.

Possible modes:

1. Grace Period
2. Read Only
3. Suspend SEO Operations
4. Full Client Service Suspension

Never delete client data because of expiry.

Preserve:

* Reports
* Keywords
* Backlinks
* Tasks
* Audit history
* Content
* Documents
* Activity

---

# 15. CLIENT SUBSCRIPTION PLANS

Agency Owner can create:

Starter SEO
Basic SEO
Professional SEO
Advanced SEO
Custom

Plan limits can include:

* Websites
* Keywords
* Backlinks
* Audits
* Reports
* Employees
* Content tasks
* AI usage
* Crawl limits
* Support level

Do not hard-code plan limits.

---

# 16. SaaS PLANS

Super Admin can create:

Starter
Professional
Agency
Enterprise
Custom

Configurable limits:

* Agencies
* Users
* Clients
* Websites
* Projects
* Keywords
* Backlinks
* Crawls
* AI usage
* Storage
* Reports
* White-label
* Client portal
* API access

---

# 17. SaaS SUBSCRIPTION MANAGEMENT

Statuses:

Trial
Active
Past Due
Cancelled
Suspended
Expired

Store:

* Start date
* Expiry date
* Billing cycle
* Amount
* Payment status
* Renewal status

Show SaaS expiry counters too.

---

# 18. BILLING SYSTEM

Create:

Plans
Subscriptions
Payments
Invoices
Transactions
Renewals

Payment gateway architecture must be modular.

Do not make a specific payment gateway mandatory for local development.

Allow future integrations.

---

# 19. PROJECT MANAGEMENT

Each client can have multiple projects.

Fields:

* Client
* Project name
* Website
* Project type
* Start date
* Target locations
* Target keywords
* Competitors
* Manager
* Employees
* Status
* SEO strategy
* Goals
* Notes

---

# 20. WEBSITE MANAGEMENT

Website fields:

* Domain
* Protocol
* Website name
* CMS
* Hosting
* Industry
* Country
* Target location
* Verification status
* Sitemap
* Robots.txt
* Last crawl
* SEO score

---

# 21. INTERNAL SEO CRAWLER

Build your own crawler.

Do not depend on an external crawler API.

Checks:

* HTTP status
* HTTPS
* Redirects
* Canonical
* Robots meta
* X-Robots-Tag
* Title
* Meta description
* H1
* H2
* H3
* Images
* ALT
* Image size
* Internal links
* External links
* Broken links
* Anchor text
* Word count
* Duplicate titles
* Duplicate descriptions
* Thin content indicators
* Noindex
* Nofollow
* Open Graph
* Twitter Cards
* JSON-LD
* Schema
* Sitemap
* Robots.txt
* Mixed content
* URL structure
* Parameter URLs
* Internal link depth
* Orphan-like pages based on crawl
* Response time
* HTML size

Respect robots.txt and reasonable crawling limits.

---

# 22. CRAWLER SETTINGS

Super Admin controls:

* Maximum URLs
* Crawl depth
* Delay
* User agent
* Timeout
* Retry
* Concurrent requests
* Include URLs
* Exclude URLs
* Sitemap crawling
* Robots compliance

Show:

Queued
Running
Completed
Failed
Cancelled

Heavy crawler work must use Laravel Queue.

---

# 23. SEO AUDIT ENGINE

Create rule-based audit engine.

Every check must produce:

* Rule
* Status
* Severity
* Score
* Explanation
* URL
* Recommendation
* Task option

Severity:

Critical
High
Medium
Low
Passed
Information

---

# 24. SEO SCORE

Create platform-generated scores:

Technical SEO
On-Page SEO
Content
Indexability
Internal Linking
Schema
Image Optimization
Overall

Clearly state:

"SEO AgencyOS Score is an internal diagnostic score and is not an official Google score."

Make scoring weights configurable.

---

# 25. AUTOMATIC TASK ENGINE

Audit issue:

↓
Rule Engine

↓
Task Generator

↓
Employee assignment

↓
Deadline

↓
Notification

↓
Employee completion

↓
Manager review

↓
Resolved

Example:

Missing meta title
→ SEO Executive

Broken links
→ Developer

Missing ALT
→ SEO Executive/Content

Technical issue
→ Developer

---

# 26. TASK MANAGEMENT

Fields:

Client
Project
Website
Category
Title
Description
Employee
Manager
Priority
Status
Due date
Estimated time
Actual time
Attachments
Comments
Checklist
Audit issue

Statuses:

Backlog
Pending
In Progress
Review
Approved
Rejected
Completed
Cancelled

---

# 27. SEO SOP ENGINE

Create configurable workflows.

Default:

Client Setup
↓
Website Verification
↓
Initial Audit
↓
Keyword Research
↓
Competitor Research
↓
Technical SEO
↓
On-Page SEO
↓
Content
↓
Internal Linking
↓
Backlinks
↓
Ranking Monitoring
↓
Monthly Audit
↓
Client Report

Agency Owner can modify SOP.

---

# 28. KEYWORD MANAGEMENT

Fields:

Keyword
Client
Project
Target URL
Intent
Location
Language
Device
Priority
Target Position
Current Position
Previous Position
Ranking URL
Search Volume
CPC
Competition
Tags

Support:

CSV
XLSX
Manual entry
Bulk editing
Groups
Clusters
Intent

---

# 29. RANK TRACKING

Store:

Keyword
Search engine
Country
City
Device
Date
Position
Ranking URL

Show:

Top 3
Top 10
Top 20
Top 50
Top 100
Improved
Dropped
Stable
New
Lost

Important:

Do not bypass search-engine anti-bot protections.

If reliable direct ranking collection is unavailable, support:

* Manual import
* CSV import
* Configured ranking providers
* Optional supported integrations

Clearly label the source of ranking data.

---

# 30. BACKLINK MANAGEMENT

Complete client-wise backlink system.

Every backlink belongs to:

Agency
Client
Project
Campaign

Fields:

Source URL
Source Domain
Target URL
Anchor
Link Type
DoFollow
NoFollow
Status
DA
DR
Spam Score
First Seen
Last Seen
Last Checked
Employee
Campaign
Notes

Statuses:

Pending
Submitted
Live
Lost
Not Found
Rejected
Under Review
DoFollow
NoFollow

---

# 31. BACKLINK IMPORT

Support:

CSV
XLSX

Import process:

Upload
↓
Column Mapping
↓
Preview
↓
Validation
↓
Duplicate Detection
↓
Import
↓
Result

Show:

Total
Imported
Duplicate
Invalid
Skipped

Do not create duplicate backlink records unnecessarily.

---

# 32. BACKLINK VERIFICATION

Where technically and legally permitted, check source pages.

Check:

HTTP status
Page existence
Target URL
Anchor
Link existence
rel
DoFollow/NoFollow
Last checked

Never mark a backlink Live unless the system actually detects it or it was explicitly verified/imported as such.

---

# 33. BACKLINK HISTORY

Never overwrite history.

Store:

Live
→ Lost
→ Recovered

Show timeline.

---

# 34. BACKLINK CAMPAIGNS

Fields:

Campaign
Client
Project
Target
Assigned employees
Start
End
Target backlinks
Achieved
Status

Dashboard:

Target
Submitted
Live
Lost

---

# 35. COMPETITOR ANALYSIS

Add competitors.

Analyze available/internal data:

Technical
Titles
Meta
Headings
Content
Keywords
Backlinks
Page count
Schema

Do not present unverified external information as factual.

---

# 36. CONTENT MANAGEMENT

Content calendar.

Fields:

Client
Project
Keyword
Topic
Intent
URL
Writer
Deadline
Status

Statuses:

Idea
Brief
Assigned
Writing
Review
Approved
Published
Optimizing

---

# 37. DEEPSEEK AI

DeepSeek is the primary optional AI provider.

Use AI for:

Keyword clustering
Intent classification
SEO title generation
Meta descriptions
Content briefs
H1/H2 suggestions
FAQ suggestions
SEO recommendations
Audit explanations
Report summaries
Content optimization
Internal link suggestions

AI must not invent SEO data.

AI must receive actual project findings.

---

# 38. AI PROVIDER ARCHITECTURE

Create:

AIProviderInterface

Implement:

DeepSeekProvider

Future providers can be added:

OpenAIProvider
GeminiProvider
LocalAIProvider

Do not couple the entire application directly to DeepSeek.

---

# 39. DEEPSEEK SETTINGS

Super Admin:

Enable/Disable
API Key
Base URL
Model
Temperature
Max Tokens
Timeout

Buttons:

Save
Test Connection
Clear Key

Encrypt API keys.

Never expose keys in frontend.

---

# 40. AI USAGE LOG

Store:

Provider
Model
Agency
User
Feature
Request time
Response time
Token usage if available
Status
Error

Avoid storing unnecessary sensitive content.

---

# 41. AI PROMPT MANAGEMENT

Create editable prompt templates:

SEO Audit
Keyword Clustering
Content Brief
Meta Generation
SEO Recommendation
Report Summary
Competitor Analysis

Super Admin can:

Edit
Enable
Disable
Version
Restore previous version

---

# 42. SITEMAP TOOL

Internal parser.

Support:

sitemap.xml
sitemap index

Extract:

URL
Last Modified
Change Frequency
Priority where available

Compare sitemap against crawl data.

---

# 43. ROBOTS.TXT ANALYZER

Analyze:

User agents
Allow
Disallow
Sitemap
Potential conflicts

Explain findings in simple language.

---

# 44. SCHEMA ANALYZER

Detect:

JSON-LD
Microdata
RDFa where feasible

Show:

Schema type
Detected properties
Missing/possible properties
Potential issues

Do not claim official Google validation without an appropriate Google-supported validation source.

---

# 45. INTERNAL LINK ANALYZER

Show:

Internal links
Pages with few links
Orphan-like pages
Most-linked pages
Pages with no internal links
Anchor text

---

# 46. IMAGE SEO ANALYZER

Check:

ALT
Missing ALT
Empty ALT
Image dimensions
Large files
Lazy loading
Format indicators
Image URLs

---

# 47. ON-PAGE SEO ANALYZER

Check:

Title
Meta description
H1
H2
Keyword relevance indicators
Content length
Internal links
External links
Canonical
Schema
Open Graph
Images
URL structure

Do not claim keyword density is an official ranking factor.

---

# 48. TECHNICAL SEO ANALYZER

Check:

HTTPS
Redirects
Canonical
Robots
Sitemap
Indexability
Broken links
Status codes
Duplicate URLs
URL structure
Mixed content
Mobile-related signals where detectable

---

# 49. AUTOMATION RULE BUILDER

Visual rule builder.

Example:

IF

Issue = Broken Link

AND

Severity = High

THEN

Create task
Assign Developer
Priority High
Deadline 48 hours
Notify SEO Manager

Other triggers:

Keyword drop
Backlink lost
Audit completed
Task overdue
Client expiry approaching
Subscription expired
Report due

---

# 50. EMPLOYEE DASHBOARD

Show:

Today's Tasks
Overdue
High Priority
Pending
Completed

Example:

CLIENT A
Fix Meta Titles
Check Backlinks
Optimize Internal Links

CLIENT B
Technical Audit
Keyword Update

Employees should see only permitted projects.

---

# 51. EMPLOYEE PRODUCTIVITY

Show operational metrics:

Tasks assigned
Tasks completed
Tasks pending
Tasks overdue
Average completion time
Backlinks handled
SEO issues resolved

Do not automatically label employees as "good" or "bad."

Show factual operational metrics.

---

# 52. CLIENT PORTAL

Client can login.

Dashboard:

SEO Score
Ranking
Backlinks
Tasks
Completed Work
Reports
Subscription
Expiry Counter
Invoices
Documents

Client cannot see internal notes unless explicitly permitted.

---

# 53. REPORT GENERATOR

Generate:

PDF
HTML
CSV
XLSX where appropriate

Monthly report:

Cover
Executive Summary
SEO Score
Keyword Movement
Technical SEO
On-Page SEO
Content Work
Backlinks
Tasks
Completed Work
Pending Work
Next Month Plan

---

# 54. REPORT BRANDING

Agency can configure:

Logo
Company name
Website
Email
Phone
Address
Colors
Footer

White-label report support.

---

# 55. AUTOMATIC REPORTS

Allow:

Monthly scheduled report

Workflow:

Generate
↓
Preview
↓
Approve
↓
Send

Do not automatically send unapproved reports unless explicitly configured.

---

# 56. EMAIL SYSTEM

SMTP settings:

Host
Port
Username
Password
Encryption
From Email
From Name

Test Email.

Encrypt credentials.

---

# 57. NOTIFICATION SYSTEM

Events:

Task assigned
Task overdue
Task completed
Task rejected
Audit completed
Keyword dropped
Backlink lost
Report generated
Client subscription expiring
Client subscription expired
SaaS subscription expiring
Payment received
Payment failed

Channels:

In-app
Email
Optional WhatsApp

---

# 58. API INTEGRATIONS

Create modular API manager.

Possible integrations:

Google Search Console
Google Analytics
Google Business Profile where supported
PageSpeed Insights
DeepSeek
Email
Storage
Payment providers

Each:

Enable/Disable
API key/OAuth
Endpoint
Configuration
Test Connection
Last Tested
Usage
Errors

All optional.

---

# 59. GOOGLE SEARCH CONSOLE

Optional OAuth integration.

Import:

Clicks
Impressions
CTR
Average position
Queries
Pages
Countries
Devices

Store imported historical data.

Do not make it mandatory.

---

# 60. GOOGLE ANALYTICS

Optional integration.

Import available metrics such as:

Users
Sessions
Landing pages
Traffic sources
Conversions where available

Clearly identify imported data.

---

# 61. PERFORMANCE ANALYSIS

Internal checks:

HTML size
Response time
Image size
Image dimensions
Lazy loading
Script count
Stylesheet count
Resource count

Optional PageSpeed integration.

Never fabricate Lighthouse/PageSpeed scores.

---

# 62. SEO DATA SOURCE LABELING

Every external/imported metric should identify its source.

Example:

Source:
Google Search Console

Source:
Manual Import

Source:
Internal Crawler

Source:
External Provider

Never mix these silently.

---

# 63. AUTOMATION ENGINE

Create central automation system.

Events:

Audit issue created
Task created
Task overdue
Keyword dropped
Backlink lost
Report due
Subscription nearing expiry
Subscription expired
Payment received
Payment failed

Actions:

Create task
Assign employee
Send notification
Generate report
Send email
Change status
Create reminder

---

# 64. CLIENT EXPIRY AUTOMATION

Example:

Client subscription:

24 Oct 2026

30 days before:
Renewal notification

15 days:
Renewal notification

7 days:
Urgent notification

3 days:
Urgent notification

1 day:
Final notification

Expiry:
Expired event

After grace period:
Apply configured access mode

---

# 65. SUBSCRIPTION COUNTERS

Show countdown in:

Super Admin
Agency Dashboard
Client List
Client Dashboard
Project Dashboard
Client Portal
Subscription Page

Use:

23 Days Remaining

or:

2 Days 14 Hours Remaining

or:

EXPIRED

---

# 66. EXPIRY COLOR/STATUS SYSTEM

Do not rely only on color.

Always show text.

Examples:

ACTIVE
RENEWAL SOON
URGENT
EXPIRED

Use configurable thresholds.

---

# 67. CLIENT SUBSCRIPTION HISTORY

Store:

Plan
Start
Expiry
Renewal
Price
Payment
Status
Changed By
Timestamp

Never delete history.

---

# 68. CLIENT INVOICES

Create invoice system.

Invoice:

* Invoice number
* Client
* Agency
* Plan
* Amount
* Tax
* Discount
* Total
* Due date
* Paid date
* Status

Statuses:

Draft
Pending
Paid
Partial
Overdue
Cancelled

PDF invoice.

---

# 69. TAX SETTINGS

Super Admin/Agency Owner can configure:

Tax name
Tax percentage
Currency
Invoice prefix

Do not hard-code one country's tax rules into the core billing engine.

---

# 70. SaaS BILLING

Super Admin dashboard:

Revenue
Payments
Active subscriptions
Expired subscriptions
Trial
Renewals
Failed payments

Plan-wise metrics.

---

# 71. WHITE LABEL

Agency can configure:

Logo
Name
Favicon
Primary color
Email
Footer
Report branding
Client portal branding

Super Admin can globally enable/disable white-label capability per plan.

---

# 72. FEATURE FLAGS

Create feature system.

Examples:

AI
Backlinks
Rank Tracking
Reports
Client Portal
White Label
API
Content
Competitor Analysis

Each feature can be enabled/disabled globally and per plan.

---

# 73. SECURITY

Implement:

CSRF
XSS protection
SQL injection protection
Mass assignment protection
Rate limiting
Secure authentication
Password hashing
Session security
Authorization
Tenant isolation
Encrypted API credentials
Secure file uploads
MIME validation
Upload limits
Audit logs

Never trust frontend authorization.

---

# 74. FILE SYSTEM

Support:

CSV
XLSX
PDF
Images

Use secure storage.

Prevent executable uploads.

Validate:

Extension
MIME
Size

Separate files by agency/client/project.

---

# 75. ACTIVITY LOGS

Track:

Login
Logout
Create
Update
Delete
Import
Export
Audit
Task assignment
Status changes
Subscription changes
Plan changes
API changes
AI usage
Report generation
Settings changes

Store:

User
Agency
Action
Object
Timestamp
IP where appropriate

---

# 76. DATABASE

Create normalized migrations for:

users
roles
permissions
agencies
agency_users
clients
projects
websites
competitors
keywords
keyword_groups
keyword_rankings
seo_audits
seo_audit_pages
seo_issues
seo_tasks
task_comments
task_attachments
sop_workflows
sop_steps
backlink_campaigns
backlinks
backlink_history
backlink_imports
content_projects
content_briefs
reports
report_metrics
notifications
automation_rules
automation_logs
api_integrations
ai_usage_logs
system_settings
agency_settings
plans
plan_features
subscriptions
subscription_items
subscription_history
payments
invoices
invoice_items
renewal_reminders
subscription_events
files
crawl_jobs
crawl_urls
crawl_results
sitemap_results
robots_results
activity_logs
feature_flags

Use proper:

Foreign keys
Indexes
Unique constraints
Soft deletes where appropriate

---

# 77. CRAWLER DATABASE

Use:

crawl_jobs
crawl_urls
crawl_results

Do not store huge crawl datasets inefficiently.

Use indexes.

Archive old crawls.

---

# 78. QUEUE SYSTEM

Queue:

Crawler
Backlink verification
Rank collection
Report generation
Excel import
AI requests
Email
Notifications
Large exports

Show:

Queued
Running
Completed
Failed

---

# 79. CRON

Scheduled tasks:

Daily:

Website monitoring
Backlink checks
Ranking collection where configured
Task reminders
Subscription reminders

Weekly:

SEO health checks
Project summaries

Monthly:

SEO reports
KPI snapshots
Subscription reports

---

# 80. SYSTEM HEALTH

Super Admin can see:

PHP
Laravel
MySQL
Queue
Cron
Storage
Cache
Mail
API
AI
Crawler

Status:

Healthy
Warning
Error

---

# 81. BACKUP

Create:

Manual Backup
Scheduled Backup
Backup History
Restore documentation

Never expose database credentials.

---

# 82. MAINTENANCE MODE

Super Admin can enable:

Maintenance mode

Configurable maintenance message.

Super Admin access remains available.

---

# 83. GLOBAL SEARCH

Search:

Clients
Projects
Websites
Keywords
Tasks
Backlinks
Reports
Invoices

Use pagination and indexed fields.

---

# 84. BULK ACTIONS

Support:

Bulk assignment
Bulk status update
Bulk delete
Bulk export
Bulk import
Bulk task creation

Require confirmation for destructive actions.

---

# 85. IMPORT SYSTEM

Generic import engine where appropriate.

Features:

File upload
Column mapping
Preview
Validation
Duplicate detection
Import
Error report

Never import invalid rows silently.

---

# 86. EXPORT SYSTEM

Support:

CSV
XLSX
PDF

All exports must respect permissions and agency isolation.

---

# 87. SEARCH AND FILTERS

All large tables need:

Search
Sort
Filter
Pagination
Date filter
Status filter
Bulk selection

---

# 88. ONBOARDING

Agency onboarding:

Agency information
↓
Create client
↓
Add website
↓
Verify website
↓
Run audit
↓
Add keywords
↓
Add competitors
↓
Upload backlinks
↓
Assign employees
↓
Create SEO subscription
↓
Generate first report

---

# 89. WEBSITE VERIFICATION

Support practical methods:

HTML meta tag
Verification file

Keep verification status in database.

---

# 90. AI DATA PRIVACY

Never send:

Passwords
API keys
Private credentials

Send only necessary project information.

AI processing must be configurable.

---

# 91. SEO REPORT LANGUAGE

Reports must explain technical problems in client-friendly language.

Example:

Instead of:

"Canonical mismatch detected."

Explain:

"Some pages indicate another URL as the preferred version. Review these pages to make sure search engines are directed to the intended URL."

---

# 92. INTERNAL SEO TOOLSET

The platform should contain:

SEO Audit
Technical Audit
On-Page Audit
Keyword Manager
Rank Tracker
Backlink Manager
Backlink Import
Backlink Verification
Competitor Analysis
Content Planner
Content Brief
Internal Link Analyzer
Sitemap Analyzer
Robots Analyzer
Schema Analyzer
Image SEO Analyzer
SEO Score
Task Manager
SOP
Automation
Reports
Client Portal

---

# 93. SELF-BUILT VS API RULE

Build internally wherever possible.

Internal:

Crawler
HTML analysis
SEO audit
Meta analysis
Heading analysis
Sitemap parser
Robots parser
Internal links
Broken links
Backlink list management
Backlink verification where permitted
Task management
Reports
SOP
Automation
Subscription counters
Billing records
Notifications
Employee management

Optional external integrations:

Search Console
Analytics
PageSpeed
AI
Payment gateway
WhatsApp

---

# 94. NO-API MODE

Create a clear:

**NO-API / SELF-HOSTED MODE**

In this mode:

* SEO crawler works
* Technical audit works
* On-page audit works
* Sitemap works
* Robots works
* Backlink management works
* CSV/XLSX imports work
* Task automation works
* Reports work
* Client portal works
* Subscription system works
* Employee management works

AI and external data features that require APIs are clearly marked as unavailable until configured.

---

# 95. API FAILURE FALLBACK

If an API fails:

Do not crash the application.

Show:

"External integration unavailable."

Continue using internal functionality.

Store failure in logs.

---

# 96. PLAN FEATURE CONTROL

Example:

STARTER

5 Clients
2 Employees
10 Websites
1,000 Keywords
5,000 Backlinks
5 Crawls/month
Basic Reports

PROFESSIONAL

25 Clients
10 Employees
50 Websites
10,000 Keywords
50,000 Backlinks
Advanced Reports
AI

AGENCY

Unlimited/large configurable limits
White Label
Client Portal
Advanced Automation
AI
API

All values configurable by Super Admin.

Do not hard-code these exact values.

---

# 97. CLIENT PLAN FEATURE CONTROL

Client SEO plans can separately control:

Keywords
Backlinks
Reports
Audits
Content
Crawls
AI
Employees
Websites

---

# 98. ACCESS CONTROL MATRIX

Implement permissions such as:

clients.view
clients.create
clients.edit
clients.delete

projects.view
projects.create
projects.edit
projects.delete

seo.audit
seo.keywords
seo.rankings
seo.backlinks
seo.content
seo.reports

tasks.view
tasks.create
tasks.assign
tasks.complete

subscriptions.view
subscriptions.create
subscriptions.renew
subscriptions.cancel

billing.view
billing.manage

settings.manage
api.manage
ai.manage

superadmin.*

Do not use only role-name checks when granular permissions are required.

---

# 99. CLIENT EXPIRY ACCESS MATRIX

Example:

Active:

Full access

Grace:

Configured access

Read Only:

Reports and historical data

Expired:

Restricted

Suspended:

No operational access

All rules configurable.

---

# 100. AUDIT TRAIL FOR SUBSCRIPTIONS

Whenever subscription changes:

Store:

Old plan
New plan
Old expiry
New expiry
Changed by
Reason
Timestamp

---

# 101. RENEWAL

Renewal should:

* Extend expiry
* Create payment/invoice
* Store history
* Trigger notification
* Update status
* Reset applicable counters
* Preserve all historical data

Do not overwrite old subscription history.

---

# 102. PLAN CHANGE

Support:

Upgrade
Downgrade
Custom plan

Handle feature-limit changes safely.

Do not delete data when a plan limit decreases.

Instead show:

"Current usage exceeds new plan limit."

---

# 103. CLIENT DASHBOARD EXPIRY WIDGET

Create a premium card:

SEO PLAN
Professional

ACTIVE

23 DAYS REMAINING

01 Sep 2026
↓
24 Oct 2026

[Renew Subscription]

Show progress percentage.

---

# 104. ADMIN EXPIRY WIDGET

Agency dashboard:

Active Clients: 42

Expiring:

7 clients

Expired:

3 clients

Renewal Due:

₹XX,XXX

Show clickable lists.

---

# 105. SUPER ADMIN SUBSCRIPTION ANALYTICS

Show:

MRR
ARR where applicable
Active subscriptions
Trials
Renewals
Churn counts
Expired accounts
Plan distribution
Payment success/failure
Upcoming renewals

Do not fabricate financial data.

---

# 106. LOCALIZATION

Prepare system for:

* English
* Hindi

Use translation files rather than hard-coded text where practical.

Currency configurable.

Timezone configurable.

Default timezone can be Asia/Kolkata but must be configurable.

---

# 107. DOCUMENTATION

Create:

README.md
INSTALLATION.md
DATABASE.md
ARCHITECTURE.md
API.md
SEO_ENGINE.md
CRAWLER.md
AI.md
DEEPSEEK.md
SUBSCRIPTIONS.md
BILLING.md
CLIENT_PORTAL.md
CRON.md
QUEUE.md
SECURITY.md
ROLES_PERMISSIONS.md
DEPLOYMENT.md
TROUBLESHOOTING.md
ADMIN_GUIDE.md

---

# 108. INSTALLER

Create production installation flow.

Check:

PHP version
Extensions
MySQL
Writable directories
Storage
Environment

Then:

Database configuration
Migration
Seeder
Admin creation
Application settings

Never expose .env.

Create .env.example.

---

# 109. ENVIRONMENT VARIABLES

Include:

APP_NAME
APP_ENV
APP_KEY
APP_URL

DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD

QUEUE_CONNECTION
CACHE_STORE

MAIL_MAILER
MAIL_HOST
MAIL_PORT
MAIL_USERNAME
MAIL_PASSWORD
MAIL_ENCRYPTION
MAIL_FROM_ADDRESS
MAIL_FROM_NAME

DEEPSEEK_API_KEY
DEEPSEEK_BASE_URL
DEEPSEEK_MODEL

All optional external credentials must remain configurable.

---

# 110. DEPLOYMENT

Support:

cPanel
Apache
Nginx
VPS

Provide:

PHP requirements
Database setup
Storage setup
Cron setup
Queue setup
Supervisor setup if needed
Permissions
SSL
Environment configuration

---

# 111. CRON CONFIGURATION

Document required Laravel scheduler configuration.

Example concept:

Laravel scheduler
↓
Every minute
↓
Due jobs

Do not assume cron automatically exists.

Provide installation instructions.

---

# 112. TESTING

Create tests for:

Authentication
Authorization
Tenant isolation
Client CRUD
Project CRUD
Website CRUD
SEO audit
Crawler
Keyword management
Backlink import
Backlink duplicate detection
Backlink verification
Tasks
Automation
Reports
AI integration
API settings
Subscription creation
Subscription renewal
Subscription expiry
Grace period
Invoice
Payment records
Notifications
Permissions

---

# 113. SECURITY TESTING

Verify:

No cross-tenant access
No unauthorized API access
No unauthorized file access
No secret exposure
No SQL injection
No XSS
No CSRF issues
No insecure upload
No privilege escalation

---

# 114. PERFORMANCE TESTING

Test:

Large client lists
Large keyword lists
Large backlink lists
Large crawl results
Large imports
Large exports

Use:

Pagination
Indexes
Queues
Caching
Batch processing

Do not load huge datasets into memory.

---

# 115. ERROR HANDLING

Handle:

Timeout
DNS error
SSL error
HTTP errors
API failures
Database failures
Queue failures
Crawler failures
Invalid uploads
Invalid API keys

Show user-friendly messages.

Store technical details in logs.

Never show secrets.

---

# 116. DEMO DATA

Create optional demo seeder:

Demo Agency
Demo Employees
Demo Clients
Demo Websites
Demo Projects
Demo Keywords
Demo Rankings
Demo Backlinks
Demo Tasks
Demo Reports
Demo Subscription

Clearly label demo data.

---

# 117. FINAL UI PAGES

Create at minimum:

LOGIN
REGISTER
FORGOT PASSWORD
SUPER ADMIN DASHBOARD
AGENCY DASHBOARD
CLIENT DASHBOARD
EMPLOYEE DASHBOARD
CLIENT PORTAL

CLIENTS
CLIENT DETAILS
CLIENT SUBSCRIPTION
PROJECTS
PROJECT DETAILS
WEBSITES

SEO AUDIT
CRAWLER
KEYWORDS
RANKINGS
BACKLINKS
BACKLINK CAMPAIGNS
COMPETITORS
CONTENT
TASKS
SOP
AUTOMATIONS
REPORTS

PLANS
SUBSCRIPTIONS
PAYMENTS
INVOICES

AI SETTINGS
API SETTINGS
EMAIL SETTINGS
NOTIFICATIONS
SYSTEM SETTINGS
BRANDING
WHITE LABEL
SECURITY
LOGS
BACKUPS

---

# 118. FINAL ARCHITECTURE

The complete platform should conceptually operate as:

```
                SEO AGENCYOS
                     |
   ┌─────────────────┼──────────────────┐
   |                 |                  |
SUPER ADMIN       AGENCY             CLIENT
   |                 |                  |
   |            ┌────┴────┐             |
   |            |         |             |
 SaaS         USERS    CLIENTS       PORTAL
```

Billing          |         |
|      PROJECTS
|         |
|      WEBSITE
|         |
└────┬────┘
|
SEO ENGINE
|
┌──────────┬───────┼───────┬──────────┐
|          |       |       |          |
AUDIT     KEYWORDS  RANK   BACKLINKS CONTENT
|          |       |       |          |
└──────────┴───────┼───────┴──────────┘
|
TASK ENGINE
|
AUTOMATION
|
EMPLOYEES
|
REPORTS
|
CLIENT

Subscription system operates across:

SaaS
+
Agency
+
Client SEO Service

with independent expiry and access rules.

---

# 119. DEVELOPMENT METHOD

Do NOT generate the entire application in one untested response.

Implement sequentially:

PHASE 1
Foundation
Laravel
Authentication
Database
RBAC
Multi-tenancy
Super Admin

PHASE 2
Agency
Employees
Clients
Projects
Websites

PHASE 3
Client Subscription
Plans
Expiry Counter
Renewals
Invoices
Notifications

PHASE 4
Task System
SOP
Automation

PHASE 5
Crawler
SEO Audit
Technical SEO

PHASE 6
Keywords
Rank Tracking

PHASE 7
Backlinks
Import
Verification
Campaigns
History

PHASE 8
Content
Competitors
Internal Linking

PHASE 9
Reports
PDF
Client Portal
White Label

PHASE 10
DeepSeek AI
AI Prompt System
AI Usage

PHASE 11
Optional APIs
Search Console
Analytics
Performance

PHASE 12
SaaS Billing
Plans
Feature Limits
Subscription Management

PHASE 13
Security
Performance
Backups
Logs

PHASE 14
Testing
Documentation
Deployment

---

# 120. DEFINITION OF DONE

A module is complete only when:

Database exists
AND
Models exist
AND
Relationships exist
AND
Backend exists
AND
UI exists
AND
Validation exists
AND
Authorization exists
AND
Tenant isolation exists
AND
Error handling exists
AND
Real data works
AND
Mobile UI works
AND
Tests exist where appropriate.

---

# 121. FINAL QUALITY REQUIREMENT

The final application must be suitable for:

1. Internal agency SEO operations
2. Managing all agency SEO employees
3. Managing many SEO clients
4. Managing client-wise backlink campaigns
5. Managing client SEO subscriptions
6. Managing subscription expiry
7. Generating client reports
8. AI-assisted SEO work
9. Optional external integrations
10. Selling SEO AgencyOS as a SaaS product

The system must prioritize:

Security
Reliability
Tenant isolation
Maintainability
Scalability
Real functionality
Professional UI
SEO accuracy
Transparent data sources
Configurable settings

---

# 122. MOST IMPORTANT FINAL INSTRUCTION

Build **SEO AgencyOS as a real commercial product, not a prototype**.

The platform should work in **self-hosted/no-API mode** for all functionality that can reasonably be implemented internally.

DeepSeek is optional and configurable.

External APIs are optional and modular.

Never fabricate data.

Never create fake functionality.

Never expose secrets.

Never delete client SEO history because of subscription expiry.

Never allow one agency to access another agency's data.

Never bypass search-engine security or anti-bot protections.

Make every important setting configurable from Super Admin.

Make the system modular so future features can be added without rewriting the core.

Start implementation from **PHASE 1**, create the foundation properly, test it, and then continue phase-by-phase until the entire production-ready SEO AgencyOS is complete.
