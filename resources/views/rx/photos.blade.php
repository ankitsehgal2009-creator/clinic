{{-- Prescription photos and X-rays for one visit. $visit with files loaded. --}}
@use('App\Models\VisitFile')
@use('App\Http\Controllers\VisitFileController')
@php($me = auth()->user())
<div class="card noprint vphotos" data-vphotos="{{ json_encode([
    'upload' => route('visitfiles.store', $visit),
    'limits' => VisitFile::LIMITS,
    'files' => $visit->files->map(fn ($f) => VisitFileController::json($f, $me))->values(),
]) }}">
  @foreach (VisitFile::KINDS as $k => $label)
    <section class="vp-sec" data-kind="{{ $k }}">
      <div class="row" style="justify-content:space-between;margin-bottom:10px">
        <h2 style="margin:0">{{ $label }} <small class="muted" data-count></small></h2>
        <div class="row" data-add>
          <label class="btn sm"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>Camera<input type="file" accept="image/*" capture="environment" class="vp-in" data-pick></label>
          <label class="btn sm"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v4h16v-4"/></svg>Upload<input type="file" accept="image/jpeg,image/png,image/webp" multiple class="vp-in" data-pick></label>
        </div>
      </div>
      <div class="vp-grid" data-grid></div>
      <p class="hint" style="margin:8px 0 0">{{ $k === 'RX' ? 'Photo of a handwritten prescription. Up to 5 for each visit.' : 'Photos or scans of X-rays (IOPA, OPG). Up to 50 for each visit.' }} Big photos are made smaller before upload.</p>
    </section>
  @endforeach
</div>
