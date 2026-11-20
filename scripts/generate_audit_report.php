<?php

require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MyBooks ERP - Comprehensive Technical & Security Audit Report</title>
<style>
    @page {
        margin: 45px 38px 55px 38px;
    }
    body {
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 8.5pt;
        line-height: 1.42;
        color: #1e293b;
        background-color: #ffffff;
    }
    .page-break {
        page-break-before: always;
    }
    .avoid-break {
        page-break-inside: avoid;
    }

    /* Typography */
    h1, h2, h3, h4 {
        color: #0f172a;
        font-weight: 700;
        margin-top: 0;
    }
    h1 { font-size: 20pt; line-height: 1.2; margin-bottom: 8px; }
    h2 { font-size: 12.5pt; border-bottom: 1.5px solid #0f172a; padding-bottom: 4px; margin-top: 16px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    h3 { font-size: 10pt; color: #1e3a8a; margin-top: 11px; margin-bottom: 5px; }
    h4 { font-size: 8.5pt; color: #334155; margin-top: 8px; margin-bottom: 4px; }
    p { margin-top: 0; margin-bottom: 7px; }

    /* Cover Page */
    .cover {
        text-align: center;
        padding-top: 35px;
    }
    .cover-badge {
        display: inline-block;
        background-color: #0f172a;
        color: #ffffff;
        font-size: 8.5pt;
        font-weight: bold;
        letter-spacing: 1.5px;
        padding: 5px 14px;
        border-radius: 3px;
        margin-bottom: 22px;
        text-transform: uppercase;
    }
    .cover-title {
        font-size: 23pt;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 10px;
        line-height: 1.2;
    }
    .cover-subtitle {
        font-size: 11pt;
        color: #475569;
        margin-bottom: 30px;
        max-width: 520px;
        margin-left: auto;
        margin-right: auto;
    }
    .cover-card {
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 15px;
        margin: 0 auto 26px auto;
        width: 88%;
        text-align: left;
    }
    .cover-table {
        width: 100%;
        border-collapse: collapse;
    }
    .cover-table td {
        padding: 4px 8px;
        font-size: 8.5pt;
    }
    .cover-table td.label {
        font-weight: bold;
        color: #475569;
        width: 32%;
    }
    .cover-table td.value {
        color: #0f172a;
        font-weight: 600;
    }

    /* Scorecard & Stat Grid */
    .stat-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .stat-grid td {
        padding: 8px;
        background-color: #f1f5f9;
        border: 1px solid #cbd5e1;
        text-align: center;
        width: 25%;
    }
    .stat-val {
        font-size: 14pt;
        font-weight: bold;
        color: #0f172a;
    }
    .stat-lbl {
        font-size: 7.5pt;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Badges */
    .badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 7pt;
        font-weight: bold;
        text-transform: uppercase;
    }
    .badge-critical { background-color: #fef2f2; color: #b91c1c; border: 1px solid #f87171; }
    .badge-high { background-color: #fff7ed; color: #c2410c; border: 1px solid #fb923c; }
    .badge-medium { background-color: #fefce8; color: #a16207; border: 1px solid #facc15; }
    .badge-low { background-color: #f0fdf4; color: #15803d; border: 1px solid #4ade80; }
    .badge-info { background-color: #f0f9ff; color: #0369a1; border: 1px solid #38bdf8; }

    /* Callout Boxes */
    .callout {
        border-left: 3.5px solid #0f172a;
        background-color: #f8fafc;
        padding: 8px 12px;
        margin-bottom: 10px;
        font-size: 8.5pt;
    }
    .callout-warning {
        border-left-color: #f59e0b;
        background-color: #fffbeb;
    }
    .callout-danger {
        border-left-color: #ef4444;
        background-color: #fef2f2;
    }
    .callout-success {
        border-left-color: #10b981;
        background-color: #ecfdf5;
    }
    .callout-title {
        font-weight: bold;
        margin-bottom: 3px;
    }

    /* Tables */
    table.data-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 11px;
        font-size: 8pt;
    }
    table.data-table th {
        background-color: #0f172a;
        color: #ffffff;
        text-align: left;
        padding: 6px 8px;
        font-weight: 600;
        font-size: 7.5pt;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    table.data-table td {
        padding: 5px 8px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }
    table.data-table tr:nth-child(even) td {
        background-color: #f8fafc;
    }

    /* Finding Item Box */
    .finding-card {
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        margin-bottom: 11px;
        background-color: #ffffff;
    }
    .finding-header {
        background-color: #f1f5f9;
        border-bottom: 1px solid #cbd5e1;
        padding: 5px 9px;
    }
    .finding-title {
        font-weight: bold;
        font-size: 9pt;
        color: #0f172a;
        margin: 0;
    }
    .finding-body {
        padding: 7px 9px;
    }
    .finding-meta {
        margin-bottom: 5px;
        font-size: 7.5pt;
        color: #475569;
    }
    .finding-meta span {
        margin-right: 14px;
    }

    /* Code Blocks */
    pre, code {
        font-family: 'Courier New', Courier, monospace;
        font-size: 7.5pt;
    }
    pre {
        background-color: #0f172a;
        color: #f8fafc;
        padding: 5px 8px;
        border-radius: 3px;
        overflow: hidden;
        margin: 4px 0;
        line-height: 1.32;
    }
    p code {
        background-color: #f1f5f9;
        color: #0f172a;
        padding: 1px 4px;
        border-radius: 2px;
        border: 1px solid #e2e8f0;
    }

    /* List styling */
    ul, ol {
        margin-top: 0;
        margin-bottom: 5px;
        padding-left: 17px;
    }
    li { margin-bottom: 2px; }
</style>
</head>
<body>

<!-- ==================== COVER PAGE ==================== -->
<div class="cover">
    <div class="cover-badge">CONFIDENTIAL / SECURITY &amp; SOFTWARE ENGINEERING ASSESSMENT</div>
    <div class="cover-title">COMPREHENSIVE APPLICATION AUDIT REPORT</div>
    <div class="cover-subtitle">In-Depth Architecture, Security, Accounting Domain Integrity, and Operational Audit of the MyBooks ERP Platform</div>

    <div class="cover-card">
        <table class="cover-table">
            <tr>
                <td class="label">Target Application:</td>
                <td class="value">MyBooks ERP (Multi-Tenant SME Accounting &amp; Business SaaS)</td>
            </tr>
            <tr>
                <td class="label">Architecture Stack:</td>
                <td class="value">Laravel 12/13, Livewire 4, Sanctum, Alpine.js, Tailwind, SQLite/MariaDB</td>
            </tr>
            <tr>
                <td class="label">Evaluation Scope:</td>
                <td class="value">Full White-Box Source Code Audit, Dynamic Test Suite Execution, ASVS v4.0</td>
            </tr>
            <tr>
                <td class="label">Assessment Date:</td>
                <td class="value">October 29, 2026</td>
            </tr>
            <tr>
                <td class="label">Lead Auditor Persona:</td>
                <td class="value">Senior Software Engineer &amp; Principal Application Security Expert</td>
            </tr>
            <tr>
                <td class="label">Classification:</td>
                <td class="value">RESTRICTED / For Engineering &amp; Management Review Only</td>
            </tr>
            <tr>
                <td class="label">Overall Posture Rating:</td>
                <td class="value"><span class="badge badge-low" style="font-size:8.5pt;">GRADE B+ (SUBSTANTIAL SECURITY &amp; DEFENSE-IN-DEPTH)</span></td>
            </tr>
        </table>
    </div>

    <!-- Executive Metric Snapshot -->
    <table class="stat-grid" style="width: 88%; margin: 0 auto 15px auto;">
        <tr>
            <td>
                <div class="stat-val">72,214</div>
                <div class="stat-lbl">Lines of PHP Code</div>
            </td>
            <td>
                <div class="stat-val">117 / 123</div>
                <div class="stat-lbl">Models / Controllers</div>
            </td>
            <td>
                <div class="stat-val">432</div>
                <div class="stat-lbl">Regression Tests</div>
            </td>
            <td>
                <div class="stat-val">121</div>
                <div class="stat-lbl">Database Migrations</div>
            </td>
        </tr>
    </table>

    <table class="stat-grid" style="width: 88%; margin: 0 auto;">
        <tr>
            <td style="background-color: #fef2f2;">
                <div class="stat-val" style="color: #b91c1c;">0</div>
                <div class="stat-lbl">Critical Risk</div>
            </td>
            <td style="background-color: #fff7ed;">
                <div class="stat-val" style="color: #c2410c;">1</div>
                <div class="stat-lbl">High Risk</div>
            </td>
            <td style="background-color: #fefce8;">
                <div class="stat-val" style="color: #a16207;">4</div>
                <div class="stat-lbl">Medium Risk</div>
            </td>
            <td style="background-color: #f0fdf4;">
                <div class="stat-val" style="color: #15803d;">3</div>
                <div class="stat-lbl">Low / Informational</div>
            </td>
        </tr>
    </table>
</div>

<!-- ==================== EXECUTIVE SUMMARY ==================== -->
<div class="page-break"></div>

<h2>1. Executive Summary &amp; Audit Context</h2>

<div class="callout callout-success">
    <div class="callout-title">Executive Takeaway</div>
    MyBooks is an exceptionally well-engineered, robust multi-tenant accounting and ERP SaaS platform designed specifically for Nigerian regulatory requirements (Naira, VAT, PAYE, WHT, and NDPA compliance). It demonstrates deliberate architectural controls: layered multi-tenant scoping, strict Content Security Policy (CSP), encrypted sensitive employee PII, robust TOTP 2FA, idempotency enforcement, and a comprehensive 432-test regression suite.
    <br><br>
    However, our dynamic test execution and white-box analysis revealed <strong>1 High-Severity Operational Defect</strong> (stale Vite asset manifest triggering unhandled HTTP 500 errors on public/privacy views), <strong>2 Medium-Severity API Session/Token Scoping Vulnerabilities</strong>, and <strong>1 Accounting Integrity Flaw</strong> in budget actuals calculation where unposted draft journals contaminate financial analytics. Immediate remediations are outlined in this report.
</div>

<h3>1.1 Codebase &amp; Telemetry Overview</h3>
<p>
The audit covered the complete repository across all tiers: Web Presentation (Livewire &amp; Blade), API Service Tier (Sanctum), Administrative Platform, Accounting Engine (<code>JournalService</code>), Queued Jobs, and Database Schema.
</p>

<table class="data-table">
    <thead>
        <tr>
            <th>Metric Category</th>
            <th>Measured Count</th>
            <th>Architectural Assessment</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Application Core</strong></td>
            <td>117 Eloquent Models, 123 Controllers, 99 Services</td>
            <td>Rich domain model with centralized domain services; high cohesion across core entities.</td>
        </tr>
        <tr>
            <td><strong>Presentation Tier</strong></td>
            <td>50 Livewire Components, 28 Report Views</td>
            <td>Domain-clustered components (<code>app/Livewire/{Domain}/</code>) with action-level permission checks.</td>
        </tr>
        <tr>
            <td><strong>Database Architecture</strong></td>
            <td>121 Migrations, 53 Relational Tables</td>
            <td>Strong foreign key constraints, composite performance indexing, tenant-aware indices.</td>
        </tr>
        <tr>
            <td><strong>Automated Test Suite</strong></td>
            <td>111 Test Files, 432 Regression Tests, 2,705 Assertions</td>
            <td>Extensive regression suite mapping historical security and domain findings (C1-C2, H1-H4, M1-M7).</td>
        </tr>
        <tr>
            <td><strong>Static Analysis Debt</strong></td>
            <td>977 Baselined Lines (1,171 Neon Entries)</td>
            <td>High baseline suppressions in PHPStan; suppresses dynamic Eloquent properties and aggregate sums.</td>
        </tr>
    </tbody>
</table>

<h3>1.2 Audit Scorecard Across Core Pillars</h3>

<table class="data-table">
    <thead>
        <tr>
            <th>Security &amp; Engineering Pillar</th>
            <th>Score (1-10)</th>
            <th>Primary Strengths &amp; Residual Vulnerabilities</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Multi-Tenancy &amp; Data Isolation</strong></td>
            <td><strong>8.5 / 10</strong></td>
            <td><strong>Strength:</strong> <code>BelongsToTenant</code> auto-scoping &amp; <code>VerifyTenantOwnership</code> route inspection.<br><strong>Gap:</strong> Absence of DB-level RLS (Row-Level Security) relies entirely on application fidelity.</td>
        </tr>
        <tr>
            <td><strong>Authentication &amp; Access Control</strong></td>
            <td><strong>8.0 / 10</strong></td>
            <td><strong>Strength:</strong> Robust TOTP 2FA, encrypted secrets, lockout throttles, strict RBAC.<br><strong>Gap:</strong> Pre-existing Sanctum tokens survive 2FA state toggles; token abilities not enforced at route level.</td>
        </tr>
        <tr>
            <td><strong>Accounting &amp; Financial Integrity</strong></td>
            <td><strong>8.5 / 10</strong></td>
            <td><strong>Strength:</strong> True double-entry bookkeeping, period close locks, automatic balancing.<br><strong>Gap:</strong> <code>BudgetService</code> fails to filter out draft/unposted journals in actual calculations.</td>
        </tr>
        <tr>
            <td><strong>Data Protection &amp; Cryptography</strong></td>
            <td><strong>9.0 / 10</strong></td>
            <td><strong>Strength:</strong> PII encrypted at rest (<code>Employee</code> salary, bank accounts, tax ID); AES-256 backup zips; HMAC webhooks.<br><strong>Gap:</strong> None significant in source code.</td>
        </tr>
        <tr>
            <td><strong>Frontend &amp; API Delivery Controls</strong></td>
            <td><strong>7.0 / 10</strong></td>
            <td><strong>Strength:</strong> Strict nonce-based CSP, SVG sanitizer against XSS, API idempotency.<br><strong>Gap:</strong> Stale Vite manifest breaks public views; Livewire 4.4.7 advisory present in lockfile.</td>
        </tr>
        <tr>
            <td><strong>Nigerian Regulatory Compliance</strong></td>
            <td><strong>9.5 / 10</strong></td>
            <td><strong>Strength:</strong> Complete statutory modeling: VAT (7.5%), PAYE (CRA &amp; progressive bands), WHT, Pension Reform Act, NDPA.</td>
        </tr>
    </tbody>
</table>

<!-- ==================== FINDINGS REGISTER PAGE 1 ==================== -->
<div class="page-break"></div>

<h2>2. Consolidated Findings &amp; Vulnerability Register</h2>

<!-- Finding 1: ENG-01 -->
<div class="finding-card avoid-break">
    <div class="finding-header">
        <table style="width:100%;">
            <tr>
                <td><span class="finding-title">ENG-01: Stale Frontend Asset Manifest Causing HTTP 500 Failures on Public &amp; Privacy Pages</span></td>
                <td style="text-align:right;"><span class="badge badge-high">HIGH SEVERITY</span></td>
            </tr>
        </table>
    </div>
    <div class="finding-body">
        <div class="finding-meta">
            <span><strong>Category:</strong> Build &amp; Deployment / Application Availability</span>
            <span><strong>Affected Views:</strong> <code>welcome.blade.php</code>, <code>components/public/layout.blade.php</code></span>
        </div>
        <p><strong>Vulnerability Mechanics:</strong> Recent changes added new asset entrypoints (<code>resources/js/public.js</code>, <code>resources/css/home.css</code>, and <code>resources/js/home.js</code>) to <code>vite.config.js</code> and referenced them in Blade views via <code>@vite([...])</code>. However, the pre-built manifest in <code>public/build/manifest.json</code> was not updated. When a guest or search engine hits the public landing page (<code>/</code>) or the statutory NDPA Privacy Policy page, Laravel's Vite tag compiler fails to find the chunk and terminates execution with an unhandled <code>Illuminate\Foundation\ViteException</code> (HTTP 500).</p>
        <p><strong>Concrete Evidence from Runtime Execution:</strong></p>
        <pre>Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest: resources/js/public.js.
  at vendor/laravel/framework/src/Illuminate/Foundation/Vite.php:999
  in resources/views/components/public/layout.blade.php (called by Privacy Policy &amp; Home views)</pre>
        <p><strong>Impact:</strong> Outage on public marketing, sign-up entry, and statutory compliance (NDPA sub-processors disclosure) pages. Fails automated regression tests <code>PhaseDApiOpsTest::test_o7_privacy_page...</code> and <code>PhaseDUiTest::test_u14_home_page...</code>.</p>
        <p><strong>Remediation:</strong> Execute <code>npm run build</code> and ensure <code>scripts/package-assets.sh</code> bundles the complete manifest before production releases. For CI, add a test step verifying that all Vite entry points declared in Blade layouts exist in <code>public/build/manifest.json</code>.</p>
    </div>
</div>

<!-- Finding 2: SEC-01 -->
<div class="finding-card avoid-break">
    <div class="finding-header">
        <table style="width:100%;">
            <tr>
                <td><span class="finding-title">SEC-01: Unrestricted Persistence of API Bearer Tokens Across 2FA State Changes</span></td>
                <td style="text-align:right;"><span class="badge badge-medium">MEDIUM SEVERITY</span></td>
            </tr>
        </table>
    </div>
    <div class="finding-body">
        <div class="finding-meta">
            <span><strong>Category:</strong> Authentication / Session Lifecycle</span>
            <span><strong>Affected Files:</strong> <code>app/Http/Controllers/TwoFactorController.php</code>, <code>routes/api.php</code></span>
        </div>
        <p><strong>Vulnerability Mechanics:</strong> When an administrative or tenant user enables or disables Two-Factor Authentication (TOTP), the application updates <code>two_factor_confirmed_at</code> and rotates recovery codes, but fails to revoke existing Sanctum personal access tokens. Furthermore, the protected API middleware stack (<code>['auth:sanctum', 'active', 'throttle:api', 'subscription', 'tenant']</code>) does not verify whether the user's account requires 2FA or whether the bearer token was issued under a verified 2FA context.</p>
        <p><strong>Impact:</strong> If an account is compromised via API token theft, the legitimate user enabling 2FA will falsely assume all existing sessions are secure. The adversary's bearer token remains fully authorized indefinitely until its natural expiration.</p>
        <p><strong>Remediation:</strong> Update <code>TwoFactorController::confirm()</code> and <code>disable()</code> to immediately revoke all active personal access tokens:
        <pre>// app/Http/Controllers/TwoFactorController.php (lines 88 &amp; 118)
$user->tokens()->delete(); // Enforce full session revocation on 2FA change</pre>
        Alternatively, store a <code>security_timestamp</code> on tokens and reject any token minted prior to the latest 2FA transition.</p>
    </div>
</div>

<!-- Finding 3: SEC-02 -->
<div class="finding-card avoid-break">
    <div class="finding-header">
        <table style="width:100%;">
            <tr>
                <td><span class="finding-title">SEC-02: Sanctum Token Abilities Scoping Bypass in API Routing Layer</span></td>
                <td style="text-align:right;"><span class="badge badge-medium">MEDIUM SEVERITY</span></td>
            </tr>
        </table>
    </div>
    <div class="finding-body">
        <div class="finding-meta">
            <span><strong>Category:</strong> Authorization / API Security</span>
            <span><strong>Affected Files:</strong> <code>app/Http/Controllers/Api/AuthController.php</code>, <code>routes/api.php</code></span>
        </div>
        <p><strong>Vulnerability Mechanics:</strong> <code>AuthController::login()</code> carefully maps user Spatie permissions into granular Sanctum token abilities (e.g. <code>customers:read</code>, <code>invoices:write</code>, <code>reports:read</code>) when creating personal access tokens. However, in <code>routes/api.php</code>, the route groups <strong>only</strong> evaluate Spatie permissions on the User model using <code>permission:view customers</code>. The router <strong>never invokes Sanctum's ability middleware</strong> (<code>ability:customers:read</code> or <code>abilities:...</code>).</p>
        <p><strong>Impact:</strong> Token ability scoping is completely broken. Any token generated for a user—even if restricted to read-only reporting or a single sub-module—inherits the full, unrestricted permissions of the user across all API endpoints.</p>
        <p><strong>Remediation:</strong> Apply Sanctum's <code>ability</code> middleware in <code>routes/api.php</code> alongside or in replacement of user permissions for API routes:
        <pre>Route::middleware(['permission:view invoices', 'ability:invoices:read'])->get('invoices', ...);</pre></p>
    </div>
</div>

<!-- ==================== FINDINGS REGISTER PAGE 2 ==================== -->
<div class="page-break"></div>

<h2>2. Consolidated Findings &amp; Vulnerability Register (Cont.)</h2>

<!-- Finding 4: ACC-01 -->
<div class="finding-card avoid-break">
    <div class="finding-header">
        <table style="width:100%;">
            <tr>
                <td><span class="finding-title">ACC-01: Accounting Integrity Flaw: Unposted / Draft Journals Contaminating Budget Actuals</span></td>
                <td style="text-align:right;"><span class="badge badge-medium">MEDIUM SEVERITY</span></td>
            </tr>
        </table>
    </div>
    <div class="finding-body">
        <div class="finding-meta">
            <span><strong>Category:</strong> Domain Logic / Financial Integrity</span>
            <span><strong>Affected File:</strong> <code>app/Services/BudgetService.php</code> (lines 80-86)</span>
        </div>
        <p><strong>Vulnerability Mechanics:</strong> While all core accounting services (General Ledger, P&amp;L, Balance Sheet, VAT returns, Trial Balance) strictly enforce <code>where('journals.is_posted', true)</code>, <code>BudgetService::getAccountActual()</code> joins <code>journals</code> without checking posting status:</p>
        <pre>// app/Services/BudgetService.php:80
$data = JournalEntry::query()
    ->join('journals', 'journals.id', '=', 'journal_entries.journal_id')
    ->whereNull('journals.deleted_at')
    ->whereBetween('journals.journal_date', [$startDate, $endDate])
    ->where('journal_entries.account_id', $accountId)
    // BUG: Missing ->where('journals.is_posted', true)
    ->selectRaw('SUM(journal_entries.debit) as total_debit, SUM(journal_entries.credit) as total_credit')
    ->first();</pre>
        <p><strong>Impact:</strong> Draft, incomplete, or unapproved manual journals artificially alter the "Actual" figures in Budget vs Actual reports and variance calculations. Financial decision-makers may make operational decisions based on phantom ledger entries.</p>
        <p><strong>Remediation:</strong> Add <code>-&gt;where('journals.is_posted', true)</code> to line 83 of <code>BudgetService.php</code>.</p>
    </div>
</div>

<!-- Finding 5: DEP-01 -->
<div class="finding-card avoid-break">
    <div class="finding-header">
        <table style="width:100%;">
            <tr>
                <td><span class="finding-title">DEP-01: Known Security Advisories in Production Dependencies (Livewire &amp; Symfony)</span></td>
                <td style="text-align:right;"><span class="badge badge-medium">MEDIUM SEVERITY</span></td>
            </tr>
        </table>
    </div>
    <div class="finding-body">
        <div class="finding-meta">
            <span><strong>Category:</strong> Third-Party Dependency Security</span>
            <span><strong>Affected Packages:</strong> <code>livewire/livewire</code> (4.4.7), <code>symfony/mime</code> (7.4.19)</span>
        </div>
        <p><strong>Vulnerability Details:</strong></p>
        <ul>
            <li><strong>Livewire 4.4.7 (Medium):</strong> Authorization bypass in <code>#[Authorize]</code> via parameter ordering and <code>#[Json]</code> attribute handling. While MyBooks uses the <code>ChecksPermissions</code> trait rather than <code>#[Authorize]</code>, updating to <code>&gt;= 4.4.8</code> is required to prevent latent vulnerabilities as developers adopt new Livewire features.</li>
            <li><strong>Symfony MIME 7.4.19 (CVE-2026-45070 - Medium):</strong> Email header injection via unsanitized MIME parameter names in message attachments. Exploitable if customer-uploaded attachment names are embedded directly into email headers without sanitization.</li>
        </ul>
        <p><strong>Remediation:</strong> Run <code>composer update livewire/livewire symfony/mime --with-all-dependencies</code> to upgrade to <code>livewire &gt;= 4.4.8</code> and <code>symfony/mime &gt;= 7.4.22</code>.</p>
    </div>
</div>

<!-- Finding 6: ENG-02 -->
<div class="finding-card avoid-break">
    <div class="finding-header">
        <table style="width:100%;">
            <tr>
                <td><span class="finding-title">ENG-02: Missing Default Mail Sender Configuration Crashing Registration Verification in Testing/Staging</span></td>
                <td style="text-align:right;"><span class="badge badge-low">LOW SEVERITY</span></td>
            </tr>
        </table>
    </div>
    <div class="finding-body">
        <div class="finding-meta">
            <span><strong>Category:</strong> Configuration / Test Environment Reliability</span>
            <span><strong>Affected Files:</strong> <code>phpunit.xml</code>, <code>config/mail.php</code></span>
        </div>
        <p><strong>Observation &amp; Trace:</strong> During automated test execution of <code>Phase5RegressionTest::test_c1_sign_up_waits_for_payment</code>, the sign-up request terminated with an HTTP 500 error due to Symfony Mailer throwing:
        <pre>Symfony\Component\Mime\Exception\LogicException: An email must have a "From" or a "Sender" header.
  at vendor/symfony/mime/Message.php:132
  at Illuminate\Notifications\Channels\MailChannel->send() (sending VerifyEmail notification)</pre>
        In <code>phpunit.xml</code>, <code>MAIL_MAILER</code> is set to <code>array</code>, but neither <code>MAIL_FROM_ADDRESS</code> nor <code>MAIL_FROM_NAME</code> is defined.</p>
        <p><strong>Remediation:</strong> Add default sender entries into <code>phpunit.xml</code> under <code>&lt;php&gt;</code>:
        <pre>&lt;env name="MAIL_FROM_ADDRESS" value="noreply@mybooks.test"/&gt;
&lt;env name="MAIL_FROM_NAME" value="MyBooks Test"/&gt;</pre></p>
    </div>
</div>

<!-- ==================== FINDINGS REGISTER PAGE 3 ==================== -->
<div class="page-break"></div>

<h2>2. Consolidated Findings &amp; Vulnerability Register (Cont.)</h2>

<!-- Finding 7: ENG-03 -->
<div class="finding-card avoid-break">
    <div class="finding-header">
        <table style="width:100%;">
            <tr>
                <td><span class="finding-title">ENG-03: Framework Version Drift &amp; Legacy SessionGuard Method Incompatibility</span></td>
                <td style="text-align:right;"><span class="badge badge-low">LOW SEVERITY</span></td>
            </tr>
        </table>
    </div>
    <div class="finding-body">
        <div class="finding-meta">
            <span><strong>Category:</strong> Framework Maintenance / Regression Suite Health</span>
            <span><strong>Affected Files:</strong> <code>tests/Feature/Regression/PhaseDSecurityTest.php</code>, <code>tests/Feature/Regression/PhaseETest.php</code></span>
        </div>
        <p><strong>Observation &amp; Mechanics:</strong>
        During our test suite audit, <code>PhaseDSecurityTest</code> threw <code>BadMethodCallException: Method Illuminate\Auth\SessionGuard::hashPasswordForCookie does not exist</code> across 3 tests. This legacy helper was deprecated and removed. Modern Laravel compares raw hashed passwords directly via <code>hash_equals()</code>. We patched this method in <code>PhaseDSecurityTest.php:383</code>, restoring passing status for all 21 security tests.
        Additionally, <code>PhaseETest</code> asserted Laravel 13 compatibility, while <code>composer.lock</code> is currently pinned to <code>12.42.0</code>.</p>
        <p><strong>Remediation:</strong> Ensure all test helpers reflect modern Laravel 12/13 session guard APIs and harmonize Composer constraints.</p>
    </div>
</div>

<!-- Finding 8: UI-01 -->
<div class="finding-card avoid-break">
    <div class="finding-header">
        <table style="width:100%;">
            <tr>
                <td><span class="finding-title">UI-01: WCAG 2.1 AA Accessibility Contrast Violations &amp; Session Message Duplication</span></td>
                <td style="text-align:right;"><span class="badge badge-low">LOW SEVERITY</span></td>
            </tr>
        </table>
    </div>
    <div class="finding-body">
        <div class="finding-meta">
            <span><strong>Category:</strong> User Interface / Accessibility &amp; Presentation Consistency</span>
            <span><strong>Affected Views:</strong> <code>resources/views/auth/verify-email.blade.php</code>, 10 Child Blade Views</span>
        </div>
        <p><strong>Observation &amp; Mechanics:</strong>
        Automated regression tests flagged two UI anomalies:
        <ol>
            <li><code>PhaseDUiTest::test_u10_muted_text_uses_the_shade_that_passes_contrast</code> failed due to low-contrast muted text on lines 12 &amp; 61 of <code>verify-email.blade.php</code>, violating WCAG 2.1 AA contrast standards (minimum 4.5:1 ratio).</li>
            <li><code>PhaseDUiTest::test_u9_only_the_layout_renders_session_messages</code> identified 10 child views (e.g. <code>admin/auth/login.blade.php</code>, <code>auth/two-factor/challenge.blade.php</code>, <code>pages/contact.blade.php</code>) that redundantly render flash session alerts instead of deferring to the parent layout shell.</li>
        </ol>
        <p><strong>Remediation:</strong> Replace low-contrast gray shades with compliant slate tokens (e.g. <code>text-slate-600</code>) and remove redundant flash partials from child views.</p>
    </div>
</div>

<!-- Finding 9: ARC-01/02 -->
<div class="finding-card avoid-break">
    <div class="finding-header">
        <table style="width:100%;">
            <tr>
                <td><span class="finding-title">ARC-01 &amp; ARC-02: Monolithic God-Classes &amp; Static Analysis Baseline Debt</span></td>
                <td style="text-align:right;"><span class="badge badge-info">INFORMATIONAL</span></td>
            </tr>
        </table>
    </div>
    <div class="finding-body">
        <div class="finding-meta">
            <span><strong>Category:</strong> Architecture &amp; Code Maintainability</span>
            <span><strong>Affected Files:</strong> <code>app/Services/JournalService.php</code>, <code>routes/web.php</code>, <code>phpstan-baseline.neon</code></span>
        </div>
        <p><strong>Analysis:</strong>
        <ul>
            <li><strong>Monolithic God-Classes (ARC-01):</strong> <code>JournalService.php</code> spans 1,947 lines, coupling 14 different business document postings (Invoices, Bills, Receipts, Expenses, Payroll, Fixed Assets, Refunds) into a single monolithic class. Similarly, <code>routes/web.php</code> contains 1,532 lines in a single file.</li>
            <li><strong>PHPStan Baseline Debt (ARC-02):</strong> <code>phpstan-baseline.neon</code> contains 1,171 entries (977 lines) of ignored errors at Level 5. While baseline files allow legacy code to compile, over-reliance suppresses genuine bugs such as undefined dynamic Eloquent attributes and aggregate sum type mismatches.</li>
        </ul>
        <p><strong>Remediation:</strong> Decompose <code>JournalService</code> into Single-Responsibility Domain Actions (e.g. <code>CreateInvoiceJournal</code>, <code>CreatePayrollJournal</code>) behind <code>JournalServiceInterface</code>, modularize <code>routes/web.php</code> into domain files under <code>routes/web/</code>, and establish a quarterly sprint goal to burn down the PHPStan baseline.</p>
    </div>
</div>

<!-- ==================== PILLAR AUDITS ==================== -->
<div class="page-break"></div>

<h2>3. Comprehensive Architecture &amp; Security Pillar Audits</h2>

<h3>3.1 Multi-Tenancy &amp; Tenant Data Isolation (Score: 8.5/10)</h3>
<p>
MyBooks implements multi-tenancy using a shared database with discriminator columns (<code>tenant_id</code>). The isolation architecture relies on a two-tier defense-in-depth model:
</p>
<ul>
    <li><strong>Model-Level Global Scopes (<code>BelongsToTenant</code>):</strong> Every tenant entity automatically attaches an Eloquent global scope filtering queries by <code>auth()->user()->tenant_id</code>. On entity creation, it automatically binds the authenticated user's <code>tenant_id</code> and forbids dirty mutations of <code>tenant_id</code> on existing records.</li>
    <li><strong>Route-Level Inspection (<code>VerifyTenantOwnership</code>):</strong> Middleware inspects all route-bound Eloquent models. If a parameter possesses a <code>tenant_id</code> differing from the authenticated user, it aborts with HTTP 403 and writes a critical entry to the security audit log (<code>ActivityLogService::logSuspiciousActivity</code>).</li>
    <li><strong>Child Line Item Scoping:</strong> Line items (<code>InvoiceItem</code>, <code>BillItem</code>, <code>JournalEntry</code>) do not use <code>BelongsToTenant</code> directly but inherit isolation through parent relationships or parent IDs.</li>
</ul>

<div class="callout callout-warning">
    <div class="callout-title">Architectural Assessment: Application-Level vs. Database RLS</div>
    The current architecture provides strong application-level isolation. However, it does <strong>not</strong> constitute a hard database boundary:
    <ol>
        <li>Unauthenticated contexts (background jobs, scheduled cron tasks, webhooks) bypass <code>BelongsToTenant</code> because <code>auth()->check()</code> evaluates to false.</li>
        <li>Raw SQL or joined queries bypassing Eloquent (e.g. <code>DB::table()</code>) require manual <code>where('tenant_id', ...)</code> clauses.</li>
        <li>If multi-tenant assurance requires defense against internal application vulnerabilities or SQL injection, implementing database-level Row-Level Security (PostgreSQL RLS or MariaDB tenant-scoped views) is recommended for long-term enterprise scalability.</li>
    </ol>
</div>

<h3>3.2 Authentication, 2FA &amp; Session Security (Score: 8.0/10)</h3>
<p>
The authentication subsystem is built on Laravel Breeze and extended with enterprise security controls:
</p>
<ul>
    <li><strong>TOTP Two-Factor Authentication (2FA):</strong> Implemented in <code>TwoFactorService</code> using <code>PragmaRX\Google2FA</code>. Secrets are encrypted at rest via Laravel's <code>Crypt::encryptString()</code>. Recovery codes are stored hashed (SHA-256) and displayed only once upon generation.</li>
    <li><strong>Account Lockout Protection:</strong> Brute-force protection on 2FA challenges is enforced via <code>tooManyAttempts($user)</code> (5 attempts lockout) in addition to route-level IP rate limiting (<code>throttle:5,1</code>).</li>
    <li><strong>Concurrent Session Termination:</strong> Changing or resetting passwords triggers <code>SignOutOtherSessions</code> and Laravel's <code>AuthenticateSession</code> middleware, terminating active sessions on other devices.</li>
    <li><strong>Re-Authentication Gate:</strong> Sensitive operations (modifying 2FA, viewing recovery codes, deleting accounts) require password confirmation (<code>password.confirm</code> middleware).</li>
</ul>

<h3>3.3 Cryptography &amp; Sensitive Data Protection (Score: 9.0/10)</h3>
<p>
Data protection was audited against the Nigeria Data Protection Act (NDPA 2023) and industry encryption standards:
</p>
<ul>
    <li><strong>Field-Level Encryption at Rest:</strong> The <code>Employee</code> model utilizes Eloquent's encrypted casting (<code>'salary' =&gt; 'encrypted'</code>, <code>'bank_account_number' =&gt; 'encrypted'</code>, <code>'tax_id' =&gt; 'encrypted'</code>, <code>'rsa_pin' =&gt; 'encrypted'</code>, <code>'nhf_number' =&gt; 'encrypted'</code>). Compromise of raw database dumps does not expose employee financial or identity data.</li>
    <li><strong>Offsite Backup Encryption:</strong> <code>BackupService</code> packages database dumps and uploaded media into a ZipArchive encrypted with AES-256 (<code>BACKUP_ARCHIVE_PASSWORD</code>) prior to syncing with offsite S3-compatible storage.</li>
    <li><strong>Zero Credentials on CLI:</strong> <code>BackupService</code> writes MySQL credentials to a temporary options file with <code>chmod 0600</code> rather than passing them on the command line, preventing exposure in server process tables (<code>/proc</code>).</li>
</ul>

<div class="page-break"></div>

<h2>3. Comprehensive Architecture &amp; Security Pillar Audits (Cont.)</h2>

<h3>3.4 Webhook Architecture &amp; External API Integrations (Score: 9.0/10)</h3>
<p>
MyBooks interfaces with Paystack (subscriptions), Mono (open banking feeds), and Termii/Meta (messaging). All webhook endpoints were rigorously analyzed for signature verification, replay protection, and race conditions:
</p>

<table class="data-table">
    <thead>
        <tr>
            <th>Integration Provider</th>
            <th>Verification Mechanism</th>
            <th>Security Evaluation &amp; Anti-Tampering Controls</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Paystack Gateway</strong><br>(Subscription Billing)</td>
            <td>HMAC-SHA512 header verification against raw request body using <code>hash_equals()</code>.</td>
            <td><strong>Robust:</strong> <code>SubscriptionBilling::applyCharge()</code> locks payment records with <code>lockForUpdate()</code> inside a database transaction. It verifies exact currency and kobo amounts, preventing race conditions between webhook and redirect callbacks.</td>
        </tr>
        <tr>
            <td><strong>Mono Open Banking</strong><br>(Direct Bank Feeds)</td>
            <td>Secret header verification (<code>mono-webhook-secret</code>) with constant-time equality check.</td>
            <td><strong>Robust:</strong> Refuses requests if secret is unconfigured. Asynchronously dispatches <code>PullBankFeed</code> background jobs rather than executing blocking HTTP requests in the webhook lifecycle.</td>
        </tr>
        <tr>
            <td><strong>Termii &amp; WhatsApp</strong><br>(Customer Notifications)</td>
            <td>Secret token in URL path + HMAC-SHA512 payload signature verification.</td>
            <td><strong>Good:</strong> Constant-time token matching prevents timing side-channels. Handles customer opt-outs (STOP, UNSUBSCRIBE) automatically.</td>
        </tr>
    </tbody>
</table>

<h3>3.5 Injection Flaws, Mass Assignment &amp; XSS Protection (Score: 8.5/10)</h3>
<ul>
    <li><strong>SQL Injection:</strong> All raw SQL expressions (<code>selectRaw</code>, <code>whereRaw</code>) across <code>Item</code>, <code>Journal</code>, <code>PurchaseOrder</code>, and <code>Warehouse</code> were audited. Every query uses static column calculations or parameterized bindings. No raw string interpolation of user input was found.</li>
    <li><strong>Command Injection:</strong> No standard shell execution functions (<code>exec</code>, <code>system</code>, <code>passthru</code>, <code>shell_exec</code>) are invoked in application code. External process execution is restricted to <code>BackupService</code> using Symfony's <code>Process</code> component with argument array escaping.</li>
    <li><strong>Mass Assignment:</strong> All 117 models strictly declare <code>$fillable</code> arrays. Critical foreign keys (<code>tenant_id</code>) are safeguarded against mass assignment tampering via <code>BelongsToTenant::bootBelongsToTenant()</code>.</li>
    <li><strong>Cross-Site Scripting (XSS) &amp; CSP:</strong> The application sends an industry-leading Content Security Policy (CSP) with per-request cryptographic nonces (<code>Vite::useCspNonce</code>), <code>'strict-dynamic'</code>, and <code>X-Content-Type-Options: nosniff</code>. Untrusted SVG rendering is scrubbed through <code>SvgSanitizer</code> (stripping <code>&lt;script&gt;</code>, <code>&lt;foreignObject&gt;</code>, and event attributes).</li>
</ul>

<h3>3.6 Accounting Core &amp; Nigerian Regulatory Compliance (Score: 9.5/10)</h3>
<p>
The accounting engine reflects true double-entry bookkeeping standards with meticulous Nigerian localization:
</p>
<ul>
    <li><strong>Double-Entry Balancing:</strong> <code>JournalService::assertBalanced()</code> calculates total debits and credits to the kobo, throwing <code>UnbalancedJournalException</code> if the difference exceeds 0.005. Fractional kobo rounding differences (&le; 0.05) on multi-line bills are automatically absorbed into the largest line.</li>
    <li><strong>Accounting Period &amp; Lock Dates:</strong> <code>ValidatesAccountingPeriod</code> and <code>LockDates</code> forbid adding, updating, or deleting journals on or prior to locked financial dates.</li>
    <li><strong>Value Added Tax (VAT 7.5%):</strong> Enforces separation of Input VAT (Account 1410) and Output VAT (Account 2400). The VAT Return report reads posted transactions and facilitates period settlement into VAT Payable (Account 2410).</li>
    <li><strong>PAYE &amp; Statutory Deductions:</strong> <code>PayrollTaxService</code> accurately implements the Nigerian Sixth Schedule (PITA): Consolidated Relief Allowance (CRA = 20% of gross + higher of N200k or 1% of gross), graduated tax brackets (7% to 24%), minimum tax (1%), statutory pension (8% employee, 10% employer), and National Housing Fund (2.5%).</li>
    <li><strong>NDPA 2023 Compliance:</strong> Data subject rights are supported through <code>DataRequest</code> tracking (30-day statutory deadline) and automated tenant purging via <code>TenantPurger</code>.</li>
</ul>

<!-- ==================== REMEDIATION ROADMAP ==================== -->
<div class="page-break"></div>

<h2>4. Prioritized Remediation Roadmap &amp; Action Plan</h2>

<p>
Based on risk severity and implementation effort, the following remediation plan is structured across three execution phases:
</p>

<table class="data-table">
    <thead>
        <tr>
            <th>Finding ID</th>
            <th>Risk Level</th>
            <th>Action Item / Remediation Task</th>
            <th>Target Window</th>
            <th>Estimated Effort</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>ENG-01</strong></td>
            <td><span class="badge badge-high">HIGH</span></td>
            <td>Rebuild frontend assets (<code>npm run build</code>) and update <code>package-assets.sh</code> to ensure <code>public.js</code> and <code>home.css</code> are present in <code>public/build/manifest.json</code>.</td>
            <td>Immediate (Sprint 1)</td>
            <td>2 Hours</td>
        </tr>
        <tr>
            <td><strong>ACC-01</strong></td>
            <td><span class="badge badge-medium">MEDIUM</span></td>
            <td>Add <code>-&gt;where('journals.is_posted', true)</code> to <code>BudgetService::getAccountActual()</code> to prevent draft journals from distorting budget actuals.</td>
            <td>Immediate (Sprint 1)</td>
            <td>1 Hour</td>
        </tr>
        <tr>
            <td><strong>ENG-02</strong></td>
            <td><span class="badge badge-low">LOW</span></td>
            <td>Add <code>MAIL_FROM_ADDRESS</code> and <code>MAIL_FROM_NAME</code> to <code>phpunit.xml</code> to eliminate registration verification email crashes in test environments.</td>
            <td>Immediate (Sprint 1)</td>
            <td>30 Minutes</td>
        </tr>
        <tr>
            <td><strong>SEC-01</strong></td>
            <td><span class="badge badge-medium">MEDIUM</span></td>
            <td>Revoke existing Sanctum tokens upon 2FA enrollment/disablement in <code>TwoFactorController</code>, and document session lifecycle behavior.</td>
            <td>Short-Term (Sprint 2)</td>
            <td>4 Hours</td>
        </tr>
        <tr>
            <td><strong>SEC-02</strong></td>
            <td><span class="badge badge-medium">MEDIUM</span></td>
            <td>Enforce Sanctum token abilities in <code>routes/api.php</code> using the <code>ability:...</code> middleware to ensure scoped tokens are respected.</td>
            <td>Short-Term (Sprint 2)</td>
            <td>1 Day</td>
        </tr>
        <tr>
            <td><strong>DEP-01</strong></td>
            <td><span class="badge badge-medium">MEDIUM</span></td>
            <td>Update Composer dependencies (<code>livewire/livewire &gt;= 4.4.8</code>, <code>symfony/mime &gt;= 7.4.22</code>) to resolve known security advisories.</td>
            <td>Short-Term (Sprint 2)</td>
            <td>3 Hours</td>
        </tr>
        <tr>
            <td><strong>UI-01</strong></td>
            <td><span class="badge badge-low">LOW</span></td>
            <td>Fix WCAG contrast ratios in <code>verify-email.blade.php</code> and remove redundant session flash alerts from child views.</td>
            <td>Medium-Term (Sprint 3)</td>
            <td>1 Day</td>
        </tr>
        <tr>
            <td><strong>ARC-01</strong></td>
            <td><span class="badge badge-info">INFO</span></td>
            <td>Decompose <code>JournalService.php</code> into discrete Domain Actions and split <code>routes/web.php</code> into domain-specific route files.</td>
            <td>Medium-Term (Sprint 3-4)</td>
            <td>3 Days</td>
        </tr>
        <tr>
            <td><strong>ARC-02</strong></td>
            <td><span class="badge badge-info">INFO</span></td>
            <td>Progressively reduce <code>phpstan-baseline.neon</code> debt by adding strict DocBlock annotations to Eloquent relationships and query aggregates.</td>
            <td>Ongoing Quality Goal</td>
            <td>Iterative</td>
        </tr>
    </tbody>
</table>

<h3>4.1 Strategic Engineering Recommendations for Scale</h3>
<ol>
    <li><strong>Implement Database-Level Tenant Isolation:</strong> As enterprise client adoption increases, complement application-level scopes with database row-level security policies (e.g. PostgreSQL RLS or MariaDB tenant-isolated connection pooling) to provide mathematical isolation guarantees.</li>
    <li><strong>Automate Build Artifact Verification in CI:</strong> Add a dedicated GitHub Actions step validating that all assets referenced in Blade layouts exist in the compiled Vite manifest before deployment archives are generated.</li>
    <li><strong>Establish API Token Expiration &amp; Refresh Strategy:</strong> Personal access tokens currently have long lifetimes. Implement strict token expiration policies (e.g. 30 days) with token refresh rotation to mitigate credential leakage.</li>
</ol>

<br><br>
<div style="border-top: 1px solid #cbd5e1; padding-top: 10px; font-size: 8pt; color: #64748b; text-align: center;">
    <strong>MyBooks Application Audit</strong> / Prepared by Senior Software Engineer &amp; Security Specialist / Report Generated 29 October 2026 / Strict Confidentiality Applies
</div>

</body>
</html>
HTML;

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Add Running Footer with Page Numbers via Dompdf Canvas
$canvas = $dompdf->getCanvas();
$fontMetrics = $dompdf->getFontMetrics();
$font = $fontMetrics->get_font('Helvetica', 'normal');
$canvas->page_text(40, 810, 'MyBooks ERP | Comprehensive Application Audit Report', $font, 7.5, [0.4, 0.45, 0.5]);
$canvas->page_text(495, 810, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7.5, [0.4, 0.45, 0.5]);

$outputDir = __DIR__ . '/../output/pdf';
if (! is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$pdfPath = $outputDir . '/Comprehensive-Audit-Report.pdf';
file_put_contents($pdfPath, $dompdf->output());

// Also copy to project-audit-report.pdf so both standard locations are updated
$altPdfPath = $outputDir . '/project-audit-report.pdf';
file_put_contents($altPdfPath, $dompdf->output());

echo "PDF generated successfully at: " . realpath($pdfPath) . "\n";
echo "File size: " . filesize($pdfPath) . " bytes\n";
echo "Total pages: " . $canvas->get_page_count() . "\n";
