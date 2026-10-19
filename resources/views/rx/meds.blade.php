@use('App\Support\Rx')
<table class="rx-meds"><thead><tr>@if ($num)<th>#</th>@endif<th>Medicine</th><th>Dose</th><th>When</th><th>Days</th></tr></thead><tbody>
  @forelse ($meds as $i => $m)
    <tr>@if ($num)<td>{{ $i + 1 }}</td>@endif<td><b>{{ Rx::prefix($m['form'] ?? null) }}{{ $m['name'] }}</b></td><td class="mono">{{ $m['dose'] ?? '' }}</td><td>{{ $m['timing'] ?? '' }}</td><td>{{ $m['days'] ?? '' }}</td></tr>
  @empty
    <tr><td colspan="{{ $num ? 5 : 4 }}" class="sm">No medicines.</td></tr>
  @endforelse
</tbody></table>
