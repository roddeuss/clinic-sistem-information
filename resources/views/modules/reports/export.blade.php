<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $dataset['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p { margin: 0 0 6px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; vertical-align: top; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; }
        .meta { margin-top: 10px; }
        .meta td { border: none; padding: 2px 0; }
    </style>
</head>
<body>
    <h1>{{ $dataset['title'] }}</h1>
    <p>{{ $dataset['description'] }}</p>

    <table class="meta">
        <tr><td>Branch</td><td>{{ $branch_label }}</td></tr>
        <tr><td>Periode</td><td>{{ $filters['date_from'] }} s/d {{ $filters['date_to'] }}</td></tr>
        <tr><td>Exported At</td><td>{{ $exported_at->format('Y-m-d H:i:s') }}</td></tr>
    </table>

    <table>
        <thead>
            <tr>
                @foreach ($dataset['columns'] as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($dataset['rows'] as $row)
                <tr>
                    @foreach ($dataset['columns'] as $column)
                        <td>{{ $row[$column] ?? '-' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($dataset['columns']) }}">No data available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
