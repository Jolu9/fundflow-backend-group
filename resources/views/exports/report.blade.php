<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: sans-serif;
            color: #111827;
            font-size: 12px;
        }

        h1 {
            font-size: 20px;
            color: #1E3A8A;
            margin-bottom: 2px;
        }

        .subtitle {
            font-size: 11px;
            color: #6B7280;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            margin-top: 26px;
            margin-bottom: 10px;
            border-bottom: 2px solid #E5E7EB;
            padding-bottom: 6px;
        }

        .stats {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .stats td {
            width: 33.3%;
            padding: 10px;
            border: 1px solid #E5E7EB;
        }

        .stat-label {
            font-size: 9px;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            margin-top: 4px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.data th {
            background: #F3F4F6;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            text-transform: uppercase;
            color: #6B7280;
            border-bottom: 1px solid #E5E7EB;
        }

        table.data td {
            padding: 6px 8px;
            font-size: 11px;
            border-bottom: 1px solid #F3F4F6;
        }

        .badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-active {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .badge-overdue {
            background: #FEE2E2;
            color: #B91C1C;
        }

        .badge-completed {
            background: #D1FAE5;
            color: #065F46;
        }

        .badge-pending {
            background: #FEF3C7;
            color: #92400E;
        }

        .badge-rejected {
            background: #F3F4F6;
            color: #6B7280;
        }

        .footer {
            margin-top: 30px;
            font-size: 9px;
            color: #9CA3AF;
            text-align: center;
        }

        .empty {
            color: #9CA3AF;
            font-style: italic;
            font-size: 11px;
        }
    </style>
</head>

<body>

    <h1>FundFlow Report</h1>
    <div class="subtitle">{{ $community->name ?? 'Community' }} &middot; Generated {{ $generatedAt->format('d M Y, H:i') }} by {{ $generatedBy }}</div>

    <div class="section-title">Group Fund Summary</div>
    <table class="stats">
        <tr>
            <td>
                <div class="stat-label">Current Fund</div>
                <div class="stat-value">K{{ number_format($currentFund, 2) }}</div>
            </td>
            <td>
                <div class="stat-label">Total Contributed</div>
                <div class="stat-value">K{{ number_format($totalContributed, 2) }}</div>
            </td>
            <td>
                <div class="stat-label">Total Disbursed</div>
                <div class="stat-value">K{{ number_format($totalDisbursed, 2) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="stat-label">Active Loans</div>
                <div class="stat-value">{{ $activeCount }}</div>
            </td>
            <td>
                <div class="stat-label">Overdue Loans</div>
                <div class="stat-value">{{ $overdueCount }}</div>
            </td>
            <td>
                <div class="stat-label">Completed Loans</div>
                <div class="stat-value">{{ $completedCount }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Loans</div>
    @if($loans->isEmpty())
    <div class="empty">No loan records.</div>
    @else
    <table class="data">
        <thead>
            <tr>
                <th>Member</th>
                <th>Amount</th>
                <th>Total Due</th>
                <th>Paid</th>
                <th>Remaining</th>
                <th>Status</th>
                <th>Applied</th>
            </tr>
        </thead>
        <tbody>
            @foreach($loans as $loan)
            <tr>
                <td>{{ $loan->user->name ?? 'Unknown' }}</td>
                <td>K{{ number_format($loan->amount, 2) }}</td>
                <td>K{{ number_format($loan->total_due, 2) }}</td>
                <td>K{{ number_format($loan->amount_paid, 2) }}</td>
                <td>K{{ number_format($loan->total_due - $loan->amount_paid, 2) }}</td>
                <td><span class="badge badge-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td>
                <td>{{ $loan->created_at->format('d M Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="section-title">Contributions</div>
    @if($contributions->isEmpty())
    <div class="empty">No contribution records.</div>
    @else
    <table class="data">
        <thead>
            <tr>
                <th>Member</th>
                <th>Amount</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($contributions as $c)
            <tr>
                <td>{{ $c->user->name ?? 'Unknown' }}</td>
                <td>K{{ number_format($c->amount, 2) }}</td>
                <td>{{ \Carbon\Carbon::parse($c->contribution_date ?? $c->created_at)->format('d M Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">Generated by FundFlow &middot; {{ $generatedAt->format('d M Y, H:i') }}</div>

</body>

</html>