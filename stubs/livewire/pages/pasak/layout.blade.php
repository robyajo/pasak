{{--
    Pasak — Vanilla CSS sub-layout dashboard Livewire.
    Mendukung dark mode, responsive, dan 100% tanpa dependensi UI eksternal.
--}}
@props(['heading' => null, 'subheading' => null])

<div class="sec-root">
    <style>
        .sec-root {
            --sec-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --sec-font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            --sec-bg: #f8fafc;
            --sec-card: #ffffff;
            --sec-card-hover: #f1f5f9;
            --sec-border: #e2e8f0;
            --sec-border-light: #f1f5f9;
            --sec-text: #0f172a;
            --sec-text-muted: #64748b;
            --sec-text-subtle: #94a3b8;
            --sec-primary: #2563eb;
            --sec-primary-hover: #1d4ed8;
            --sec-primary-fg: #ffffff;
            --sec-danger: #ef4444;
            --sec-danger-bg: #fef2f2;
            --sec-danger-border: #fecaca;
            --sec-danger-hover: #dc2626;
            --sec-warning: #f59e0b;
            --sec-warning-bg: #fffbeb;
            --sec-warning-border: #fde68a;
            --sec-success: #10b981;
            --sec-success-bg: #ecfdf5;
            --sec-success-border: #a7f3d0;
            --sec-radius: 10px;
            --sec-radius-sm: 6px;
            --sec-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.07), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
            --sec-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -2px rgba(0, 0, 0, 0.05);

            font-family: var(--sec-font);
            color: var(--sec-text);
            width: 100%;
            box-sizing: border-box;
        }

        @media (prefers-color-scheme: dark) {
            .sec-root {
                --sec-bg: #090d16;
                --sec-card: #111827;
                --sec-card-hover: #1e293b;
                --sec-border: #1e293b;
                --sec-border-light: #162032;
                --sec-text: #f9fafb;
                --sec-text-muted: #9ca3af;
                --sec-text-subtle: #6b7280;
                --sec-primary: #3b82f6;
                --sec-primary-hover: #2563eb;
                --sec-danger: #f87171;
                --sec-danger-bg: rgba(239, 68, 68, 0.15);
                --sec-danger-border: rgba(239, 68, 68, 0.3);
                --sec-warning: #fbbf24;
                --sec-warning-bg: rgba(245, 158, 11, 0.15);
                --sec-warning-border: rgba(245, 158, 11, 0.3);
                --sec-success: #34d399;
                --sec-success-bg: rgba(16, 185, 129, 0.15);
                --sec-success-border: rgba(16, 185, 129, 0.3);
                --sec-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.3);
            }
        }

        .dark .sec-root, [data-theme="dark"] .sec-root {
            --sec-bg: #090d16;
            --sec-card: #111827;
            --sec-card-hover: #1e293b;
            --sec-border: #1e293b;
            --sec-border-light: #162032;
            --sec-text: #f9fafb;
            --sec-text-muted: #9ca3af;
            --sec-text-subtle: #6b7280;
            --sec-primary: #3b82f6;
            --sec-primary-hover: #2563eb;
            --sec-danger: #f87171;
            --sec-danger-bg: rgba(239, 68, 68, 0.15);
            --sec-danger-border: rgba(239, 68, 68, 0.3);
            --sec-warning: #fbbf24;
            --sec-warning-bg: rgba(245, 158, 11, 0.15);
            --sec-warning-border: rgba(245, 158, 11, 0.3);
            --sec-success: #34d399;
            --sec-success-bg: rgba(16, 185, 129, 0.15);
            --sec-success-border: rgba(16, 185, 129, 0.3);
            --sec-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.3);
        }

        .sec-root * {
            box-sizing: border-box;
        }

        /* Layout Structure */
        .sec-container {
            display: flex;
            gap: 28px;
            align-items: flex-start;
            width: 100%;
        }

        .sec-sidebar {
            width: 210px;
            flex-shrink: 0;
            position: sticky;
            top: 20px;
        }

        .sec-main {
            flex: 1;
            min-width: 0;
            width: 100%;
        }

        /* Navigation */
        .sec-nav-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sec-nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 500;
            color: var(--sec-text-muted);
            text-decoration: none;
            border-radius: var(--sec-radius-sm);
            transition: all 0.15s ease-in-out;
        }

        .sec-nav-item:hover {
            color: var(--sec-text);
            background: var(--sec-card-hover);
        }

        .sec-nav-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .sec-nav-item.active {
            color: var(--sec-primary);
            background: rgba(37, 99, 235, 0.08);
            font-weight: 600;
        }

        .sec-nav-badge {
            margin-left: auto;
            background: var(--sec-danger);
            color: #fff;
            font-size: 11px;
            padding: 1px 6px;
            border-radius: 9999px;
            font-weight: 600;
        }

        /* Header */
        .sec-header {
            margin-bottom: 24px;
        }

        .sec-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 6px 0;
            color: var(--sec-text);
            letter-spacing: -0.02em;
        }

        .sec-subtitle {
            font-size: 14px;
            color: var(--sec-text-muted);
            margin: 0;
        }

        /* Cards */
        .sec-card {
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: var(--sec-radius);
            padding: 20px;
            box-shadow: var(--sec-shadow);
            margin-bottom: 20px;
        }

        .sec-card-flush {
            padding: 0;
            overflow: hidden;
        }

        .sec-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--sec-border);
            flex-wrap: wrap;
            gap: 12px;
        }

        .sec-card-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            color: var(--sec-text);
        }

        .sec-card-subtitle {
            font-size: 13px;
            color: var(--sec-text-muted);
            margin: 2px 0 0 0;
        }

        .sec-card-body {
            padding: 20px;
        }

        .sec-card-desc {
            font-size: 13px;
            color: var(--sec-text-muted);
            margin: 0 0 14px 0;
            line-height: 1.55;
        }

        .sec-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 13px;
            color: var(--sec-text);
        }

        .sec-content > *:last-child {
            margin-bottom: 0;
        }

        /* Grids */
        .sec-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .sec-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .sec-stat-card {
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: var(--sec-radius);
            padding: 18px 20px;
            box-shadow: var(--sec-shadow);
        }

        .sec-stat-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--sec-text-muted);
            margin-bottom: 6px;
        }

        .sec-stat-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--sec-text);
            line-height: 1.1;
            margin-bottom: 6px;
            font-variant-numeric: tabular-nums;
        }

        .sec-stat-value.sec-text-danger {
            color: var(--sec-danger);
        }

        .sec-stat-desc {
            font-size: 12px;
            color: var(--sec-text-subtle);
        }

        /* Toolbar & Controls */
        .sec-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .sec-toolbar-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .sec-input, .sec-select {
            height: 38px;
            padding: 0 12px;
            font-size: 14px;
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: var(--sec-radius-sm);
            color: var(--sec-text);
            font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .sec-input:focus, .sec-select:focus, .sec-textarea:focus {
            outline: none;
            border-color: var(--sec-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .sec-textarea {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: var(--sec-radius-sm);
            color: var(--sec-text);
            font-family: inherit;
        }

        /* Buttons */
        .sec-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 38px;
            padding: 0 16px;
            font-size: 14px;
            font-weight: 500;
            border-radius: var(--sec-radius-sm);
            cursor: pointer;
            border: 1px solid transparent;
            font-family: inherit;
            transition: all 0.15s ease-in-out;
            text-decoration: none;
            white-space: nowrap;
        }

        .sec-btn-sm {
            height: 30px;
            padding: 0 10px;
            font-size: 12px;
        }

        .sec-btn-primary {
            background: var(--sec-primary);
            color: #fff;
        }
        .sec-btn-primary:hover {
            background: var(--sec-primary-hover);
        }

        .sec-btn-danger {
            background: var(--sec-danger);
            color: #fff;
        }
        .sec-btn-danger:hover {
            background: var(--sec-danger-hover);
        }

        .sec-btn-secondary {
            background: var(--sec-card);
            border-color: var(--sec-border);
            color: var(--sec-text);
        }
        .sec-btn-secondary:hover {
            background: var(--sec-card-hover);
        }

        .sec-btn-ghost {
            background: transparent;
            color: var(--sec-text-muted);
            border: none;
            padding: 4px 8px;
            height: auto;
        }
        .sec-btn-ghost:hover {
            color: var(--sec-text);
            background: var(--sec-card-hover);
        }

        /* Badges */
        .sec-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .sec-badge-critical, .sec-badge-red {
            background: var(--sec-danger-bg);
            color: var(--sec-danger);
            border: 1px solid var(--sec-danger-border);
        }

        .sec-badge-high, .sec-badge-orange, .sec-badge-amber, .sec-badge-warning {
            background: var(--sec-warning-bg);
            color: var(--sec-warning);
            border: 1px solid var(--sec-warning-border);
        }

        .sec-badge-medium {
            background: rgba(245, 158, 11, 0.12);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.25);
        }

        .sec-badge-low, .sec-badge-zinc {
            background: var(--sec-card-hover);
            color: var(--sec-text-muted);
            border: 1px solid var(--sec-border);
        }

        .sec-badge-success, .sec-badge-green {
            background: var(--sec-success-bg);
            color: var(--sec-success);
            border: 1px solid var(--sec-success-border);
        }

        /* Table */
        .sec-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .sec-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .sec-table th {
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--sec-text-muted);
            background: var(--sec-card-hover);
            border-bottom: 1px solid var(--sec-border);
            white-space: nowrap;
        }

        .sec-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--sec-border-light);
            color: var(--sec-text);
            vertical-align: middle;
        }

        .sec-table tr:last-child td {
            border-bottom: none;
        }

        .sec-table tr:hover td {
            background: var(--sec-card-hover);
        }

        .sec-font-mono {
            font-family: var(--sec-font-mono);
        }

        /* Notification / Toast */
        .sec-alert {
            padding: 12px 16px;
            border-radius: var(--sec-radius-sm);
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sec-alert-success {
            background: var(--sec-success-bg);
            border: 1px solid var(--sec-success-border);
            color: var(--sec-success);
        }

        .sec-alert-danger {
            background: var(--sec-danger-bg);
            border: 1px solid var(--sec-danger-border);
            color: var(--sec-danger);
        }

        /* Trend Chart Bars */
        .sec-chart-box {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            height: 150px;
            padding-top: 10px;
            width: 100%;
        }

        .sec-chart-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            height: 100%;
            gap: 6px;
            position: relative;
        }

        .sec-chart-bar {
            width: 100%;
            border-radius: 4px 4px 0 0;
            background: rgba(239, 68, 68, 0.7);
            transition: all 0.2s;
            min-height: 2px;
        }

        .sec-chart-bar:hover {
            background: var(--sec-danger);
            transform: scaleY(1.02);
        }

        .sec-chart-label {
            font-size: 10px;
            color: var(--sec-text-subtle);
            white-space: nowrap;
        }

        /* Modal Styles */
        .sec-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
        }

        .sec-modal-box {
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: var(--sec-radius);
            box-shadow: var(--sec-shadow-md);
            max-width: 520px;
            width: 100%;
            overflow: hidden;
            animation: sec-pop 0.15s ease-out;
        }

        @keyframes sec-pop {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }

        .sec-modal-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--sec-border);
        }

        .sec-modal-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .sec-modal-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--sec-border);
            background: var(--sec-card-hover);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }


        /* Scope Selector (Settings) */
        .sec-scope-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }
        .sec-scope-box {
            cursor: pointer;
            border: 2px solid var(--sec-border);
            border-radius: var(--sec-radius);
            padding: 18px;
            background: var(--sec-card);
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: all 0.2s ease-in-out;
        }
        .sec-scope-box:hover {
            border-color: var(--sec-border-light);
            background: var(--sec-card-hover);
        }
        .sec-scope-box.active-primary {
            border-color: var(--sec-primary);
            background: rgba(59, 130, 246, 0.08);
        }
        .sec-scope-box.active-warning {
            border-color: var(--sec-warning);
            background: rgba(245, 158, 11, 0.08);
        }
        .sec-scope-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sec-scope-title-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sec-scope-title {
            font-weight: 700;
            font-size: 15px;
            color: var(--sec-text);
        }
        .sec-scope-desc {
            font-size: 13px;
            color: var(--sec-text-muted);
            line-height: 1.5;
            margin: 0;
        }
        .sec-param-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--sec-border);
        }

        /* Responsive Breakpoints */
        @media (max-width: 900px) {
            .sec-container {
                flex-direction: column;
                gap: 16px;
            }

            .sec-sidebar {
                width: 100%;
                position: static;
                border-bottom: 1px solid var(--sec-border);
                padding-bottom: 12px;
            }

            .sec-nav-list {
                flex-direction: row;
                overflow-x: auto;
                padding-bottom: 4px;
            }

            .sec-nav-item {
                white-space: nowrap;
            }

            .sec-grid-4 {
                grid-template-columns: repeat(2, 1fr);
            }

            .sec-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .sec-grid-4 {
                grid-template-columns: 1fr;
            }
            .sec-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .sec-toolbar-group {
                flex-direction: column;
                align-items: stretch;
            }
            .sec-input, .sec-select {
                width: 100%;
            }
        }
    </style>

    <div class="sec-container">
        <!-- Sidebar Navigation -->
        <aside class="sec-sidebar" aria-label="Security Navigation">
            <nav>
                <ul class="sec-nav-list">
                    <li>
                        <a href="{{ route('pasak.dashboard.overview') }}"
                           class="sec-nav-item {{ request()->routeIs('pasak.dashboard.overview') ? 'active' : '' }}"
                           wire:navigate>
                            <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                            <span>{{ __('Overview') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pasak.dashboard.logs') }}"
                           class="sec-nav-item {{ request()->routeIs('pasak.dashboard.logs') ? 'active' : '' }}"
                           wire:navigate>
                            <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>
                            <span>{{ __('Security Logs') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pasak.dashboard.blocked-ips') }}"
                           class="sec-nav-item {{ request()->routeIs('pasak.dashboard.blocked-ips') ? 'active' : '' }}"
                           wire:navigate>
                            <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>
                            <span>{{ __('Blocked IPs') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pasak.dashboard.server') }}"
                           class="sec-nav-item {{ request()->routeIs('pasak.dashboard.server') ? 'active' : '' }}"
                           wire:navigate>
                            <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="8" x="2" y="2" rx="2"/><rect width="20" height="8" x="2" y="14" rx="2"/><path d="M6 6h.01"/><path d="M6 18h.01"/></svg>
                            <span>{{ __('Server Audit') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pasak.dashboard.sessions') }}"
                           class="sec-nav-item {{ request()->routeIs('pasak.dashboard.sessions') ? 'active' : '' }}"
                           wire:navigate>
                            <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span>{{ __('Sessions') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pasak.dashboard.tickets') }}"
                           class="sec-nav-item {{ request()->routeIs('pasak.dashboard.tickets') ? 'active' : '' }}"
                           wire:navigate>
                            <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                            <span>{{ __('Appeals') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pasak.dashboard.settings') }}"
                           class="sec-nav-item {{ request()->routeIs('pasak.dashboard.settings') ? 'active' : '' }}"
                           wire:navigate>
                            <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                            <span>{{ __('Settings') }}</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="sec-main">
            @if (session()->has('security_message'))
                <div class="sec-alert sec-alert-success">
                    <span>{{ session('security_message') }}</span>
                </div>
            @endif

            <header class="sec-header">
                @if ($heading)
                    <h1 class="sec-title">{{ $heading }}</h1>
                @endif
                @if ($subheading)
                    <p class="sec-subtitle">{{ $subheading }}</p>
                @endif
            </header>

            <div class="sec-content">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>
