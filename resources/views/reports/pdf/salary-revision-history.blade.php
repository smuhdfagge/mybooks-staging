@extends('reports.pdf.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 10px;">
        <p style="font-size: 9px; color: #6b7280;">
            Total Revisions: {{ $versions->count() }}
        </p>
    </div>

    <!-- Salary Structure Revisions -->
    <div class="section-title">Salary Structure Revision History</div>
    <table>
        <thead>
            <tr>
                <th>Structure</th>
                <th>Version</th>
                <th>Effective Date</th>
                <th>Changed By</th>
                <th>Change Reason</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($versions as $version)
                <tr>
                    <td>{{ $version->salaryStructure->name ?? 'N/A' }}</td>
                    <td>v{{ $version->version_number }}</td>
                    <td>{{ $version->effective_date ? \Carbon\Carbon::parse($version->effective_date)->format('M d, Y') : 'N/A' }}</td>
                    <td>{{ $version->changedByUser ? ($version->changedByUser->first_name . ' ' . $version->changedByUser->last_name) : 'System' }}</td>
                    <td>{{ $version->change_reason ?? '-' }}</td>
                    <td>{{ $version->created_at->format('M d, Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No salary revision history found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
