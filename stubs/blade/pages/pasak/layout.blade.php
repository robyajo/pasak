<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($heading ?? 'Dashboard') }} — Pasak</title>
    <script>
        // Terapkan tema tersimpan sebelum paint agar tidak flicker.
        (function() {
            try {
                var mode = localStorage.getItem('pasak-theme') || 'auto';
                var isDark = mode === 'dark' || (mode !== 'light' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (mode === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    document.documentElement.classList.add('dark');
                } else if (mode === 'light') {
                    document.documentElement.setAttribute('data-theme', 'light');
                    document.documentElement.classList.remove('dark');
                } else {
                    document.documentElement.removeAttribute('data-theme');
                    if (isDark) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                }
            } catch (e) {
                /* abaikan: tetap ikuti sistem */ }
        })();
    </script>
    <style>
        :root,
        html,
        body.sec-body,
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
            color-scheme: light;
        }

        body.sec-body {
            background: var(--sec-bg);
            color: var(--sec-text);
            font-family: var(--sec-font);
            margin: 0;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .sec-root {
            font-family: var(--sec-font);
            color: var(--sec-text);
            width: 100%;
            box-sizing: border-box;
        }

        /* Mode Gelap Otomatis dari Sistem OS (hanya berlaku jika pengguna memilih 'auto' / tanpa data-theme="light") */
        @media (prefers-color-scheme: dark) {

            :root:not([data-theme="light"]),
            html:not([data-theme="light"]),
            html:not([data-theme="light"]) body.sec-body,
            html:not([data-theme="light"]) .sec-root {
                color-scheme: dark;
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

        /* Override Mode Terang Eksplisit */
        html[data-theme="light"],
        html[data-theme="light"] body.sec-body,
        html[data-theme="light"] .sec-root,
        [data-theme="light"] {
            color-scheme: light !important;
            --sec-bg: #f8fafc !important;
            --sec-card: #ffffff !important;
            --sec-card-hover: #f1f5f9 !important;
            --sec-border: #e2e8f0 !important;
            --sec-border-light: #f1f5f9 !important;
            --sec-text: #0f172a !important;
            --sec-text-muted: #64748b !important;
            --sec-text-subtle: #94a3b8 !important;
            --sec-primary: #2563eb !important;
            --sec-primary-hover: #1d4ed8 !important;
            --sec-primary-fg: #ffffff !important;
            --sec-danger: #ef4444 !important;
            --sec-danger-bg: #fef2f2 !important;
            --sec-danger-border: #fecaca !important;
            --sec-danger-hover: #dc2626 !important;
            --sec-warning: #f59e0b !important;
            --sec-warning-bg: #fffbeb !important;
            --sec-warning-border: #fde68a !important;
            --sec-success: #10b981 !important;
            --sec-success-bg: #ecfdf5 !important;
            --sec-success-border: #a7f3d0 !important;
            --sec-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.07), 0 1px 2px -1px rgba(0, 0, 0, 0.05) !important;
            --sec-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -2px rgba(0, 0, 0, 0.05) !important;
        }

        /* Override Mode Gelap Eksplisit */
        html[data-theme="dark"],
        html[data-theme="dark"] body.sec-body,
        html[data-theme="dark"] .sec-root,
        html.dark,
        html.dark body.sec-body,
        html.dark .sec-root,
        [data-theme="dark"] {
            color-scheme: dark !important;
            --sec-bg: #090d16 !important;
            --sec-card: #111827 !important;
            --sec-card-hover: #1e293b !important;
            --sec-border: #1e293b !important;
            --sec-border-light: #162032 !important;
            --sec-text: #f9fafb !important;
            --sec-text-muted: #9ca3af !important;
            --sec-text-subtle: #6b7280 !important;
            --sec-primary: #3b82f6 !important;
            --sec-primary-hover: #2563eb !important;
            --sec-danger: #f87171 !important;
            --sec-danger-bg: rgba(239, 68, 68, 0.15) !important;
            --sec-danger-border: rgba(239, 68, 68, 0.3) !important;
            --sec-warning: #fbbf24 !important;
            --sec-warning-bg: rgba(245, 158, 11, 0.15) !important;
            --sec-warning-border: rgba(245, 158, 11, 0.3) !important;
            --sec-success: #34d399 !important;
            --sec-success-bg: rgba(16, 185, 129, 0.15) !important;
            --sec-success-border: rgba(16, 185, 129, 0.3) !important;
            --sec-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.3) !important;
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

        .sec-content>*:last-child {
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

        .sec-input,
        .sec-select {
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

        .sec-input:focus,
        .sec-select:focus,
        .sec-textarea:focus {
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

        .sec-badge-critical,
        .sec-badge-red {
            background: var(--sec-danger-bg);
            color: var(--sec-danger);
            border: 1px solid var(--sec-danger-border);
        }

        .sec-badge-high,
        .sec-badge-orange,
        .sec-badge-amber,
        .sec-badge-warning {
            background: var(--sec-warning-bg);
            color: var(--sec-warning);
            border: 1px solid var(--sec-warning-border);
        }

        .sec-badge-medium {
            background: rgba(245, 158, 11, 0.12);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.25);
        }

        .sec-badge-low,
        .sec-badge-zinc {
            background: var(--sec-card-hover);
            color: var(--sec-text-muted);
            border: 1px solid var(--sec-border);
        }

        .sec-badge-success,
        .sec-badge-green {
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

        /* Data Table & Pagination */
        .sec-datatable-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-top: 1px solid var(--sec-border);
            font-size: 13px;
            color: var(--sec-text-muted);
            flex-wrap: wrap;
            gap: 12px;
        }

        .sec-pagination {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
            padding: 0;
            margin: 0;
            flex-wrap: wrap;
        }

        .sec-page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            font-size: 13px;
            font-weight: 500;
            border-radius: var(--sec-radius-sm);
            border: 1px solid var(--sec-border);
            background: var(--sec-card);
            color: var(--sec-text);
            text-decoration: none;
            transition: all 0.15s ease-in-out;
            user-select: none;
        }

        .sec-page-btn:hover:not(.disabled) {
            background: var(--sec-card-hover);
            border-color: var(--sec-border-light);
            color: var(--sec-text);
        }

        .sec-page-btn.active {
            background: var(--sec-primary);
            border-color: var(--sec-primary);
            color: #fff;
            font-weight: 600;
        }

        .sec-page-btn.disabled {
            opacity: 0.35;
            cursor: not-allowed;
            pointer-events: none;
        }

        .sec-page-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 32px;
            color: var(--sec-text-muted);
            font-size: 13px;
        }

        .sec-font-mono {
            font-family: var(--sec-font-mono);
        }

        /* Floating Toast Notifications */
        .sec-toast-stack {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 100000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 420px;
            width: calc(100vw - 48px);
            pointer-events: none;
        }

        .sec-toast {
            pointer-events: auto;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: var(--sec-radius);
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            color: var(--sec-text);
            font-size: 13.5px;
            line-height: 1.45;
            animation: sec-toast-slide 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .sec-toast.sec-toast-hiding {
            opacity: 0;
            transform: translateX(30px);
        }

        @keyframes sec-toast-slide {
            from {
                opacity: 0;
                transform: translateY(-16px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .sec-toast-icon {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
        }

        .sec-toast-success .sec-toast-icon {
            background: var(--sec-success-bg);
            color: var(--sec-success);
            border: 1px solid var(--sec-success-border);
        }

        .sec-toast-error .sec-toast-icon,
        .sec-toast-danger .sec-toast-icon {
            background: var(--sec-danger-bg);
            color: var(--sec-danger);
            border: 1px solid var(--sec-danger-border);
        }

        .sec-toast-warning .sec-toast-icon {
            background: var(--sec-warning-bg);
            color: var(--sec-warning);
            border: 1px solid var(--sec-warning-border);
        }

        .sec-toast-info .sec-toast-icon {
            background: rgba(37, 99, 235, 0.1);
            color: var(--sec-primary);
            border: 1px solid rgba(37, 99, 235, 0.25);
        }

        .sec-toast-content {
            flex: 1;
            min-width: 0;
        }

        .sec-toast-title {
            font-weight: 700;
            font-size: 13.5px;
            margin-bottom: 2px;
            color: var(--sec-text);
        }

        .sec-toast-message {
            font-size: 13px;
            color: var(--sec-text-muted);
            word-break: break-word;
        }

        .sec-toast-close {
            background: transparent;
            border: none;
            color: var(--sec-text-subtle);
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            padding: 2px 4px;
            margin: -2px -4px 0 0;
            border-radius: 4px;
            transition: color 0.15s;
        }

        .sec-toast-close:hover {
            color: var(--sec-text);
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
        .sec-modal-backdrop[hidden] {
            display: none !important;
        }

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
            from {
                opacity: 0;
                transform: scale(0.96);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
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

        /* Settings — scope pills, level cards, toggle switch */
        .sec-scope-box.active-info {
            border-color: var(--sec-primary);
            background: rgba(59, 130, 246, 0.08);
        }

        .sec-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 22px;
            padding: 0 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .sec-pill-info {
            background: var(--sec-success-bg);
            color: #047857;
            border: 1px solid var(--sec-success-border);
        }

        .sec-switch-wrap {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
        }

        .sec-switch {
            position: relative;
            width: 46px;
            height: 26px;
            flex: 0 0 46px;
        }

        .sec-switch input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
            z-index: 2;
        }

        .sec-switch-track {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            background: var(--sec-border);
            transition: background 0.2s ease-in-out;
        }

        .sec-switch-track::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
            transition: transform 0.2s ease-in-out;
        }

        .sec-switch input:checked~.sec-switch-track {
            background: var(--sec-success);
        }

        .sec-switch input:checked~.sec-switch-track::after {
            transform: translateX(20px);
        }

        .sec-switch-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--sec-success);
        }

        .sec-switch-label.off {
            color: var(--sec-text-muted);
        }

        .sec-level-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 14px;
        }

        .sec-level-card {
            border: 1px solid var(--sec-border);
            border-left-width: 4px;
            border-left-style: solid;
            border-radius: var(--sec-radius);
            padding: 14px 16px;
            background: var(--sec-card);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .sec-level-card.critical {
            border-left-color: var(--sec-danger);
        }

        .sec-level-card.high {
            border-left-color: var(--sec-warning);
        }

        .sec-level-card.medium {
            border-left-color: var(--sec-warning);
        }

        .sec-level-card.low {
            border-left-color: var(--sec-primary);
        }

        .sec-level-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .sec-level-title {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-weight: 700;
            font-size: 14px;
            color: var(--sec-text);
        }

        .sec-level-badge {
            font-size: 10.5px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: var(--sec-radius-sm);
            white-space: nowrap;
        }

        .sec-level-badge.critical {
            background: var(--sec-danger-bg);
            color: #b91c1c;
        }

        .sec-level-badge.high {
            background: var(--sec-warning-bg);
            color: #92400e;
        }

        .sec-level-badge.medium {
            background: var(--sec-warning-bg);
            color: #92400e;
        }

        .sec-level-badge.low {
            background: var(--sec-primary-bg, #eff6ff);
            color: #1d4ed8;
        }

        .sec-level-input {
            width: 100%;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 700;
            font-family: var(--sec-font-mono);
            border: 1px solid var(--sec-border);
            border-radius: var(--sec-radius-sm);
            background: var(--sec-card-hover);
            color: var(--sec-text);
        }

        .sec-level-input:focus {
            outline: none;
            border-color: var(--sec-primary);
        }

        .sec-level-desc {
            font-size: 11.5px;
            line-height: 1.5;
            color: var(--sec-text-muted);
            margin: 0;
        }

        .sec-level-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border: 1px solid var(--sec-border);
            border-radius: var(--sec-radius);
            background: var(--sec-card);
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }

        .sec-level-check:hover {
            border-color: var(--sec-border-light);
            background: var(--sec-card-hover);
        }

        .sec-level-check.checked {
            border-color: var(--sec-primary);
            background: rgba(59, 130, 246, 0.06);
        }

        .sec-level-check input {
            margin-top: 2px;
            accent-color: var(--sec-primary);
        }

        .sec-select-input {
            width: 100%;
            padding: 9px 12px;
            font-size: 13.5px;
            font-weight: 600;
            border: 1px solid var(--sec-border);
            border-radius: var(--sec-radius-sm);
            background: var(--sec-card-hover);
            color: var(--sec-text);
        }

        .sec-select-input:focus {
            outline: none;
            border-color: var(--sec-primary);
        }

        .sec-sim-box {
            border: 1px solid var(--sec-warning-border);
            background: var(--sec-warning-bg);
            border-radius: var(--sec-radius);
            padding: 14px 16px;
            color: var(--sec-warning);
        }

        .sec-sim-chip {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: var(--sec-radius-sm);
            font-family: var(--sec-font-mono);
            font-size: 11.5px;
            font-weight: 700;
            color: #ffffff;
        }

        .sec-sim-chip.critical {
            background: #dc2626;
        }

        .sec-sim-chip.high {
            background: #d97706;
        }

        .sec-sim-chip.medium {
            background: #ca8a04;
        }

        .sec-sim-chip.low {
            background: #2563eb;
        }

        .sec-sim-chip.combined {
            background: #7c3aed;
        }

        /* Theme Toggle */
        .sec-theme-toggle {
            margin-top: 16px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 36px;
            padding: 0 12px;
            font-size: 13px;
            font-weight: 500;
            border-radius: var(--sec-radius-sm);
            cursor: pointer;
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            color: var(--sec-text-muted);
            font-family: inherit;
            transition: all 0.15s ease-in-out;
        }

        .sec-theme-toggle:hover {
            color: var(--sec-text);
            background: var(--sec-card-hover);
        }

        .sec-theme-toggle svg {
            width: 16px;
            height: 16px;
        }

        .sec-sidebar-version {
            margin-top: 10px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: var(--sec-radius-sm);
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .sec-sidebar-version:hover {
            background: var(--sec-card-hover);
            border-color: var(--sec-primary);
        }

        .sec-sidebar-version-brand {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--sec-text);
            letter-spacing: -0.01em;
        }

        .sec-sidebar-version-icon {
            width: 14px;
            height: 14px;
            color: var(--sec-primary);
            flex-shrink: 0;
        }

        .sec-sidebar-version-badge {
            font-family: var(--sec-font-mono);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 9999px;
            background: rgba(37, 99, 235, 0.08);
            color: var(--sec-primary);
            border: 1px solid rgba(37, 99, 235, 0.2);
            line-height: 1.2;
            transition: all 0.15s ease-in-out;
        }

        .sec-sidebar-version:hover .sec-sidebar-version-badge {
            background: var(--sec-primary);
            color: #ffffff;
        }

        /* Detail Pages */
        .sec-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px 20px;
        }

        .sec-detail-item {
            min-width: 0;
        }

        .sec-detail-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--sec-text-subtle);
            margin-bottom: 4px;
        }

        .sec-detail-value {
            font-size: 14px;
            color: var(--sec-text);
            word-break: break-word;
        }

        .sec-code-block {
            font-family: var(--sec-font-mono);
            font-size: 12.5px;
            line-height: 1.6;
            background: var(--sec-card-hover);
            border: 1px solid var(--sec-border);
            border-radius: var(--sec-radius-sm);
            padding: 14px 16px;
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 320px;
            overflow: auto;
            color: var(--sec-text);
        }

        .sec-back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
            color: var(--sec-text-muted);
            text-decoration: none;
            margin-bottom: 16px;
        }

        .sec-back-link:hover {
            color: var(--sec-primary);
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

            .sec-input,
            .sec-select {
                width: 100%;
            }
        }
    </style>
</head>

<body class="sec-body" style="margin: 0;">
    <!-- Floating Toast Notifications -->
    <div class="sec-toast-stack" id="pasakToastStack" aria-live="polite" aria-atomic="true"></div>

    <!-- Alert-Dialog Confirm Modal -->
    <div class="sec-modal-backdrop" id="pasakConfirmModal" hidden>
        <div class="sec-modal-box" role="dialog" aria-modal="true" aria-labelledby="pasakConfirmTitle" style="max-width: 460px;">
            <div class="sec-modal-header" style="display: flex; align-items: center; gap: 12px; padding: 16px 20px;">
                <div id="pasakConfirmIcon" style="width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;"></div>
                <div style="min-width: 0; flex: 1;">
                    <h3 class="sec-card-title" id="pasakConfirmTitle" style="font-size: 16px; margin: 0;">{{ __('Konfirmasi Tindakan') }}</h3>
                </div>
            </div>
            <div class="sec-modal-body" style="padding: 18px 20px; font-size: 13.5px; color: var(--sec-text); line-height: 1.55;">
                <p id="pasakConfirmMessage" style="margin: 0;"></p>
            </div>
            <div class="sec-modal-footer" style="padding: 14px 20px; display: flex; justify-content: flex-end; gap: 10px; background: var(--sec-card-hover);">
                <button type="button" class="sec-btn sec-btn-secondary" id="pasakConfirmCancel">{{ __('Batal') }}</button>
                <button type="button" class="sec-btn" id="pasakConfirmOk">{{ __('Lanjutkan') }}</button>
            </div>
        </div>
    </div>
    <div class="sec-root">
        <div class="sec-container" style="max-width: 1600px; margin: 0 auto; padding: 24px 20px;">
            <!-- Sidebar Navigation -->
            <aside class="sec-sidebar" aria-label="Security Navigation">
                <nav>
                    <ul class="sec-nav-list">
                        <li>
                            <a href="{{ route('pasak.dashboard.overview') }}"
                                class="sec-nav-item {{ request()->routeIs('pasak.dashboard.overview*') ? 'active' : '' }}">
                                <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect width="7" height="7" x="3" y="3" rx="1" />
                                    <rect width="7" height="7" x="14" y="3" rx="1" />
                                    <rect width="7" height="7" x="14" y="14" rx="1" />
                                    <rect width="7" height="7" x="3" y="14" rx="1" />
                                </svg>
                                <span>{{ __('Overview') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('pasak.dashboard.logs') }}"
                                class="sec-nav-item {{ request()->routeIs('pasak.dashboard.logs*') ? 'active' : '' }}">
                                <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <path d="M14 2v6h6" />
                                    <path d="M9 13h6" />
                                    <path d="M9 17h6" />
                                </svg>
                                <span>{{ __('Security Logs') }}</span>
                            </a>
                        </li>
                        @if (\Route::has('pasak.dashboard.threat-map'))
                        <li>
                            <a href="{{ route('pasak.dashboard.threat-map') }}"
                                class="sec-nav-item {{ request()->routeIs('pasak.dashboard.threat-map*') ? 'active' : '' }}">
                                <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M2 12h20" />
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                                </svg>
                                <span>{{ __('Threat Map') }}</span>
                            </a>
                        </li>
                        @endif
                        <li>
                            <a href="{{ route('pasak.dashboard.blocked-ips') }}"
                                class="sec-nav-item {{ request()->routeIs('pasak.dashboard.blocked-ips*') ? 'active' : '' }}">
                                <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="m4.9 4.9 14.2 14.2" />
                                </svg>
                                <span>{{ __('Blocked IPs') }}</span>
                            </a>
                        </li>
                        @if (\Route::has('pasak.dashboard.allowed-ips'))
                        <li>
                            <a href="{{ route('pasak.dashboard.allowed-ips') }}"
                                class="sec-nav-item {{ request()->routeIs('pasak.dashboard.allowed-ips') ? 'active' : '' }}">
                                <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg>
                                <span>{{ __('Allowed IPs') }}</span>
                            </a>
                        </li>
                        @endif
                        <li>
                            <a href="{{ route('pasak.dashboard.server') }}"
                                class="sec-nav-item {{ request()->routeIs('pasak.dashboard.server*') ? 'active' : '' }}">
                                <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect width="20" height="8" x="2" y="2" rx="2" />
                                    <rect width="20" height="8" x="2" y="14" rx="2" />
                                    <path d="M6 6h.01" />
                                    <path d="M6 18h.01" />
                                </svg>
                                <span>{{ __('Server Audit') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('pasak.dashboard.sessions') }}"
                                class="sec-nav-item {{ request()->routeIs('pasak.dashboard.sessions*') ? 'active' : '' }}">
                                <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                <span>{{ __('Sessions') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('pasak.dashboard.tickets') }}"
                                class="sec-nav-item {{ request()->routeIs('pasak.dashboard.tickets*') ? 'active' : '' }}">
                                <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
                                    <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                                </svg>
                                <span>{{ __('Appeals') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('pasak.dashboard.settings') }}"
                                class="sec-nav-item {{ request()->routeIs('pasak.dashboard.settings*') ? 'active' : '' }}">
                                <svg class="sec-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="3" />
                                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
                                </svg>
                                <span>{{ __('Settings') }}</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <button type="button" class="sec-theme-toggle" data-sec-theme-toggle aria-label="{{ __('Toggle dark mode') }}">
                    <svg data-sec-theme-icon="moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
                    </svg>
                    <svg data-sec-theme-icon="sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display: none;">
                        <circle cx="12" cy="12" r="4" />
                        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
                    </svg>
                    <span data-sec-theme-label>{{ __('Dark mode') }}</span>
                </button>
                <a href="https://pasak.is-a.dev"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="sec-sidebar-version"
                    title="{{ __('Versi Pasak aktif: v:version — Klik untuk melihat catatan rilis', ['version' => \Internal\Pasak\Services\PasakService::VERSION]) }}">
                    <span class="sec-sidebar-version-brand">
                        <svg class="sec-sidebar-version-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                        <span>Pasak</span>
                    </span>
                    <span class="sec-sidebar-version-badge">v{{ \Internal\Pasak\Services\PasakService::VERSION }}</span>
                </a>
            </aside>


            <main class="sec-main">
                @php
                $flashToasts = [];
                if (session()->has('toast')) {
                $t = session('toast');
                if (is_array($t)) {
                $flashToasts[] = [
                'type' => $t['type'] ?? 'success',
                'title' => $t['title'] ?? (($t['type'] ?? 'success') === 'error' ? __('Gagal') : __('Berhasil')),
                'message' => (string) ($t['message'] ?? ''),
                ];
                } else {
                $flashToasts[] = ['type' => 'success', 'title' => __('Berhasil'), 'message' => (string) $t];
                }
                }
                if (session()->has('security_message')) {
                $flashToasts[] = ['type' => 'success', 'title' => __('Berhasil'), 'message' => (string) session('security_message')];
                }
                if (session()->has('success')) {
                $flashToasts[] = ['type' => 'success', 'title' => __('Berhasil'), 'message' => (string) session('success')];
                }
                if (session()->has('error')) {
                $flashToasts[] = ['type' => 'error', 'title' => __('Gagal'), 'message' => (string) session('error')];
                }
                if (session()->has('status') && ! session()->has('toast')) {
                $flashToasts[] = ['type' => 'info', 'title' => __('Status'), 'message' => (string) session('status')];
                }
                if (session()->has('message') && ! session()->has('toast')) {
                $flashToasts[] = ['type' => 'info', 'title' => __('Informasi'), 'message' => (string) session('message')];
                }
                if (isset($errors) && $errors->any()) {
                foreach ($errors->all() as $err) {
                $flashToasts[] = ['type' => 'error', 'title' => __('Gagal Validasi'), 'message' => (string) $err];
                }
                }
                @endphp
                @if (session()->has('security_message'))
                <div class="sec-alert sec-alert-success">
                    <span>{{ session('security_message') }}</span>
                </div>
                @endif
                @if (session()->has('toast'))
                <div class="sec-alert sec-alert-{{ (session('toast.type', 'success') === 'success') ? 'success' : 'danger' }}">
                    <span>{{ session('toast.message') }}</span>
                </div>
                @endif

                <header class="sec-header">
                    @if (! empty($heading))
                    <h1 class="sec-title">{{ $heading }}</h1>
                    @endif
                    @if (! empty($subheading))
                    <p class="sec-subtitle">{{ $subheading }}</p>
                    @endif
                </header>

                @yield('content')
            </main>
        </div>

        <!-- Pengumuman kunjungan pertama (hanya local + debug) -->
        @if (app()->environment('local') && config('app.debug'))
        <div class="sec-modal-backdrop" id="pasakAnnounce" hidden>
            <div class="sec-modal-box" role="dialog" aria-modal="true" aria-labelledby="pasakAnnounceTitle">
                <div class="sec-modal-header">
                    <h3 class="sec-card-title" id="pasakAnnounceTitle">👋 {{ __('Selamat datang di dashboard Pasak') }}</h3>
                </div>
                <div class="sec-modal-body" style="font-size: 14px; color: var(--sec-text-muted); line-height: 1.6;">
                    <div>🛡️ {{ __('Bawaan instalasi adalah monitoring saja: ancaman dideteksi dan dicatat tanpa blokir otomatis.') }}</div>
                    <div>⚙️ {{ __('Siap karantina otomatis? Atur di halaman Settings atau via API.') }}</div>
                    <div>📖 {{ __('Panduan lengkap:') }} <a href="https://github.com/robyajo/pasak" target="_blank" rel="noopener" style="color: var(--sec-primary);">github.com/robyajo/pasak</a></div>
                </div>
                <div class="sec-modal-footer">
                    <button type="button" class="sec-btn sec-btn-primary" data-pasak-announce-close>{{ __('Mengerti') }}</button>
                </div>
            </div>
        </div>
        <script>
            (function() {
                try {
                    var key = 'pasak-announce-{{ str_replace('.
                    ', ' - ', \Internal\Pasak\Services\PasakService::VERSION) }}';
                    if (window.localStorage && localStorage.getItem(key)) return;
                    var el = document.getElementById('pasakAnnounce');
                    if (!el) return;
                    el.hidden = false;
                    var btn = el.querySelector('[data-pasak-announce-close]');
                    if (btn) btn.addEventListener('click', function() {
                        el.hidden = true;
                        try {
                            localStorage.setItem(key, '1');
                        } catch (e) {}
                    });
                } catch (e) {}
            })();
        </script>
        @endif
    </div>
    <script>
        // Toggle tema gelap/terang manual (tersimpan di localStorage, default ikuti sistem).
        (function() {
            var key = 'pasak-theme';
            var labels = {
                dark: {
                    !!json_encode(__('Dark')) !!
                },
                light: {
                    !!json_encode(__('Light')) !!
                },
                auto: {
                    !!json_encode(__('Auto')) !!
                }
            };

            function stored() {
                try {
                    return localStorage.getItem(key) || 'auto';
                } catch (e) {
                    return 'auto';
                }
            }

            function isDark(mode) {
                if (mode === 'dark') {
                    return true;
                }
                if (mode === 'light') {
                    return false;
                }

                return window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            function paint() {
                var mode = stored();
                var dark = isDark(mode);
                var root = document.documentElement;

                if (mode === 'auto') {
                    root.removeAttribute('data-theme');
                } else {
                    root.setAttribute('data-theme', mode);
                }

                if (dark) {
                    root.classList.add('dark');
                } else {
                    root.classList.remove('dark');
                }

                document.querySelectorAll('[data-sec-theme-toggle]').forEach(function(btn) {
                    var moon = btn.querySelector('[data-sec-theme-icon="moon"]');
                    var sun = btn.querySelector('[data-sec-theme-icon="sun"]');
                    var label = btn.querySelector('[data-sec-theme-label]');

                    if (moon) {
                        moon.style.display = dark ? 'none' : '';
                    }
                    if (sun) {
                        sun.style.display = dark ? '' : 'none';
                    }
                    if (label) {
                        label.textContent = labels[mode] || labels.auto;
                    }
                });
            }

            document.querySelectorAll('[data-sec-theme-toggle]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var order = ['auto', 'light', 'dark'];
                    var next = order[(order.indexOf(stored()) + 1) % order.length];

                    try {
                        localStorage.setItem(key, next);
                    } catch (e) {
                        /* abaikan */ }

                    paint();
                });
            });

            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', paint);
            }

            paint();
        })();
    </script>
    <script>
        window.PasakToasts = @json($flashToasts ?? []);

        (function() {
            // 1. Toast Notification System
            window.pasakToast = function(message, type, title) {
                if (!message) return;
                type = type || 'success';
                var stack = document.getElementById('pasakToastStack');
                if (!stack) return;

                var toast = document.createElement('div');
                toast.className = 'sec-toast sec-toast-' + type;

                var icons = {
                    success: '✓',
                    error: '✕',
                    danger: '✕',
                    warning: '⚠',
                    info: 'ℹ'
                };

                var defaultTitles = {
                    success: {
                        !!json_encode(__('Berhasil')) !!
                    },
                    error: {
                        !!json_encode(__('Gagal')) !!
                    },
                    danger: {
                        !!json_encode(__('Gagal')) !!
                    },
                    warning: {
                        !!json_encode(__('Peringatan')) !!
                    },
                    info: {
                        !!json_encode(__('Informasi')) !!
                    }
                };

                var displayTitle = title || defaultTitles[type] || '';

                toast.innerHTML =
                    '<div class="sec-toast-icon">' + (icons[type] || 'ℹ') + '</div>' +
                    '<div class="sec-toast-content">' +
                    (displayTitle ? '<div class="sec-toast-title">' + displayTitle + '</div>' : '') +
                    '<div class="sec-toast-message">' + message + '</div>' +
                    '</div>' +
                    '<button type="button" class="sec-toast-close" aria-label="' + {
                        !!json_encode(__('Tutup')) !!
                    } + '">&times;</button>';

                stack.appendChild(toast);

                var removeToast = function() {
                    toast.classList.add('sec-toast-hiding');
                    setTimeout(function() {
                        if (toast.parentNode) toast.parentNode.removeChild(toast);
                    }, 200);
                };

                toast.querySelector('.sec-toast-close').addEventListener('click', removeToast);

                var timer = setTimeout(removeToast, 5000);
                toast.addEventListener('mouseenter', function() {
                    clearTimeout(timer);
                });
                toast.addEventListener('mouseleave', function() {
                    timer = setTimeout(removeToast, 3000);
                });
            };

            if (window.PasakToasts && Array.isArray(window.PasakToasts)) {
                window.PasakToasts.forEach(function(t, i) {
                    setTimeout(function() {
                        window.pasakToast(t.message, t.type, t.title);
                    }, i * 150);
                });
            }

            // 2. Alert-Dialog Confirm System
            var modal = document.getElementById('pasakConfirmModal');
            if (!modal) return;

            var titleEl = document.getElementById('pasakConfirmTitle');
            var msgEl = document.getElementById('pasakConfirmMessage');
            var iconEl = document.getElementById('pasakConfirmIcon');
            var okBtn = document.getElementById('pasakConfirmOk');
            var cancelBtn = document.getElementById('pasakConfirmCancel');

            function showConfirm(options, onConfirm) {
                titleEl.textContent = options.title || {
                    !!json_encode(__('Konfirmasi Tindakan')) !!
                };
                msgEl.textContent = options.message || {
                    !!json_encode(__('Apakah Anda yakin ingin melanjutkan tindakan ini?')) !!
                };
                okBtn.textContent = options.btnText || {
                    !!json_encode(__('Ya, Lanjutkan')) !!
                };

                var type = options.type || 'primary';
                okBtn.className = 'sec-btn ' + (type === 'danger' ? 'sec-btn-danger' : (type === 'warning' ? 'sec-btn-danger' : 'sec-btn-primary'));

                if (type === 'danger') {
                    iconEl.textContent = '🚨';
                    iconEl.style.background = 'var(--sec-danger-bg)';
                    iconEl.style.color = 'var(--sec-danger)';
                    iconEl.style.border = '1px solid var(--sec-danger-border)';
                } else if (type === 'warning') {
                    iconEl.textContent = '⚠';
                    iconEl.style.background = 'var(--sec-warning-bg)';
                    iconEl.style.color = 'var(--sec-warning)';
                    iconEl.style.border = '1px solid var(--sec-warning-border)';
                } else {
                    iconEl.textContent = '🛡️';
                    iconEl.style.background = 'rgba(37, 99, 235, 0.1)';
                    iconEl.style.color = 'var(--sec-primary)';
                    iconEl.style.border = '1px solid rgba(37, 99, 235, 0.25)';
                }

                modal.hidden = false;
                okBtn.focus();

                var cleanup = function() {
                    modal.hidden = true;
                    okBtn.onclick = null;
                    cancelBtn.onclick = null;
                    document.removeEventListener('keydown', handleKey);
                };

                var handleKey = function(e) {
                    if (e.key === 'Escape') cleanup();
                };

                cancelBtn.onclick = cleanup;
                modal.onclick = function(e) {
                    if (e.target === modal) cleanup();
                };

                okBtn.onclick = function() {
                    cleanup();
                    if (onConfirm) onConfirm();
                };

                document.addEventListener('keydown', handleKey);
            }

            // Intercept form submissions
            document.addEventListener('submit', function(e) {
                var form = e.target;
                if (!form || (form.method && form.method.toLowerCase() === 'get')) return;
                if (form.dataset.pasakConfirmed === 'true') {
                    form.removeAttribute('data-pasak-confirmed');
                    return;
                }

                var submitBtn = e.submitter;

                var confirmMsg = form.getAttribute('data-sec-confirm');
                if (submitBtn && submitBtn.getAttribute('data-sec-confirm')) {
                    confirmMsg = submitBtn.getAttribute('data-sec-confirm');
                }

                var title = form.getAttribute('data-confirm-title');
                if (submitBtn && submitBtn.getAttribute('data-confirm-title')) {
                    title = submitBtn.getAttribute('data-confirm-title');
                }

                var btnText = form.getAttribute('data-confirm-btn');
                if (submitBtn && submitBtn.getAttribute('data-confirm-btn')) {
                    btnText = submitBtn.getAttribute('data-confirm-btn');
                }

                var type = form.getAttribute('data-confirm-type');
                if (submitBtn && submitBtn.getAttribute('data-confirm-type')) {
                    type = submitBtn.getAttribute('data-confirm-type');
                }

                if (!confirmMsg) {
                    var isDelete = form.querySelector('input[name="_method"][value="DELETE"]') ||
                        (submitBtn && (submitBtn.classList.contains('sec-btn-danger') || (submitBtn.textContent && submitBtn.textContent.indexOf('🗑️') !== -1)));
                    var isPatch = form.querySelector('input[name="_method"][value="PATCH"]');
                    var btnLabel = submitBtn ? submitBtn.textContent.trim().toLowerCase() : '';

                    if (isDelete) {
                        confirmMsg = {
                            !!json_encode(__('Apakah Anda yakin ingin menghapus data ini secara permanen?')) !!
                        };
                        title = title || {
                            !!json_encode(__('Konfirmasi Hapus')) !!
                        };
                        type = type || 'danger';
                        btnText = btnText || {
                            !!json_encode(__('Ya, Hapus')) !!
                        };
                    } else if (isPatch || btnLabel.indexOf('disable') !== -1 || btnLabel.indexOf('enable') !== -1 || btnLabel.indexOf('nonaktifkan') !== -1 || btnLabel.indexOf('aktifkan') !== -1) {
                        confirmMsg = {
                            !!json_encode(__('Apakah Anda yakin ingin mengubah status data ini?')) !!
                        };
                        title = title || {
                            !!json_encode(__('Konfirmasi Pengubahan Status')) !!
                        };
                        type = type || 'warning';
                        btnText = btnText || {
                            !!json_encode(__('Ya, Ubah Status')) !!
                        };
                    } else if (form.id === 'pasak-settings-form') {
                        confirmMsg = {
                            !!json_encode(__('Simpan seluruh perubahan konfigurasi WAF ke database?')) !!
                        };
                        title = title || {
                            !!json_encode(__('Konfirmasi Simpan Pengaturan')) !!
                        };
                        type = type || 'primary';
                        btnText = btnText || {
                            !!json_encode(__('Ya, Simpan')) !!
                        };
                    } else {
                        return;
                    }
                }

                e.preventDefault();

                showConfirm({
                    title: title || {
                        !!json_encode(__('Konfirmasi Tindakan')) !!
                    },
                    message: confirmMsg,
                    type: type || 'primary',
                    btnText: btnText || {
                        !!json_encode(__('Ya, Lanjutkan')) !!
                    }
                }, function() {
                    form.dataset.pasakConfirmed = 'true';
                    if (submitBtn && submitBtn.name) {
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = submitBtn.name;
                        hidden.value = submitBtn.value;
                        form.appendChild(hidden);
                    }
                    if (form.requestSubmit) {
                        form.requestSubmit(submitBtn);
                    } else {
                        form.submit();
                    }
                });
            });
        })();
    </script>
</body>

</html>