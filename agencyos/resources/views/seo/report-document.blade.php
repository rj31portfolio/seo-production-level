<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>{{ $report->title }}</title>
<style>
    @page { margin: 110px 38px 55px; }
    body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 11px; line-height: 1.6; }
    p { overflow-wrap: anywhere; word-wrap: break-word; }
    table { width: 100%; border-collapse: collapse; }
    td, th { text-align: left; padding: 7px; }
    th { background: #f8f8f8; }
    pre { white-space: pre-wrap; overflow-wrap: anywhere; }
    .pdf-header { position: fixed; top: -85px; left: 0; right: 0; height: 65px; border-bottom: 2px solid {{ $report->snapshot['branding']['accent_color'] ?? '#ea580c' }}; }
    .pdf-header td { border: 0; padding: 0 8px 10px 0; vertical-align: middle; }
    .pdf-header img { max-height: 42px; max-width: 135px; }
    .pdf-header .contact { text-align: right; font-size: 9px; color: #64748b; }
    .pdf-footer { position: fixed; bottom: -30px; left: 0; right: 50px; color: #64748b; font-size: 8px; }
    .audit-report tr { page-break-inside: avoid; }
    .audit-report h2, .audit-report h3 { page-break-after: avoid; }
</style></head>
<body>
@php($brand = $report->snapshot['branding'] ?? ['name' => $report->snapshot['agency']])
<header class="pdf-header"><table><tr><td>
@if(!empty($brand['logo_data']))<img src="{{ $brand['logo_data'] }}" alt="{{ $brand['name'] }} logo">@else<strong>{{ $brand['name'] }}</strong>@endif
</td><td class="contact"><strong>{{ $brand['name'] }}</strong><br>{{ $brand['email'] ?? '' }}@if(!empty($brand['support_email']))<br>Support: {{ $brand['support_email'] }}@endif</td></tr></table></header>
<footer class="pdf-footer">{{ $brand['name'] }} · Report #{{ $report->id }}</footer>
@include('seo.report-body')
</body></html>
