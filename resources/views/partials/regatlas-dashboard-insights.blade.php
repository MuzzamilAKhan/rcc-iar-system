@php
  $viewer=auth()->user();
  $scope=\App\Models\ImpactAssessment::query();
  if (!$viewer->can('iar.view.all')) {
    $scope->where(function($q) use($viewer) {
      if ($viewer->can('iar.view.own') || $viewer->can('iar.create')) $q->where('creator_id',$viewer->id);
      if ($viewer->can('iar.view.assigned')) $q->orWhereHas('assignments',fn($a)=>$a->where('assigned_to_user_id',$viewer->id));
    });
  }
  $counts=(clone $scope)->select('status')->selectRaw('count(*) as count')->groupBy('status')->pluck('count','status');
  $total=(int)$counts->sum();
  $draft=(int)($counts['draft']??0);
  $submitted=(int)($counts['submitted']??0);
  $other=max(0,$total-$draft-$submitted);
  $submittedPercent=$total?round(($submitted/$total)*100):0;
  $canSeeRegistry=$viewer->can('manage-master-data');
  $registrationSummary=$canSeeRegistry ? \Illuminate\Support\Facades\DB::table('registry_import_requests')->select('status')->selectRaw('count(*) as count')->groupBy('status')->pluck('count','status') : collect();
  $pending=(int)($registrationSummary['pending']??0);
  $approved=(int)($registrationSummary['approved']??0);
  $rejected=(int)($registrationSummary['rejected']??0);
  $maxImport=max(1,$pending,$approved,$rejected);
@endphp
<section class="ra-chart-grid" aria-label="Workspace insights">
  <div class="ra-chart-card">
    <h3>Impact assessment overview</h3>
    <p>{{ $viewer->can('iar.view.all') ? 'Assessments across your authorized workspace' : 'Assessments visible to your account' }} · current snapshot</p>
    <div class="ra-chart-row">
      <div class="ra-chart-donut" style="--ra-chart-part:{{ $submittedPercent }}%" role="img" aria-label="{{ $submitted }} of {{ $total }} visible assessments submitted"><span><span>{{ $total }}<small>Total IARs</small></span></span></div>
      <div class="ra-chart-legend">
        <div><span class="ra-chart-dot"></span>Submitted: <strong>{{ $submitted }}</strong></div>
        <div><span class="ra-chart-dot alt"></span>Draft: <strong>{{ $draft }}</strong></div>
        @if($other)<div>Other states: <strong>{{ $other }}</strong></div>@endif
        @if(!$total)<small>No accessible IARs yet</small>@endif
      </div>
    </div>
  </div>
  @if($canSeeRegistry)
    <div class="ra-chart-card">
      <h3>Registry import decisions</h3>
      <p>Controlled import requests by review status · current snapshot</p>
      <div class="ra-bars" role="img" aria-label="{{ $pending }} pending, {{ $approved }} approved, {{ $rejected }} rejected requests">
        @foreach (['Pending'=>$pending,'Approved'=>$approved,'Rejected'=>$rejected] as $label=>$value)
          <div class="ra-bar-item"><span class="ra-bar-num">{{ $value }}</span><span class="ra-bar-fill" style="--ra-bar-height:{{ round(100*$value/$maxImport) }}%"></span><span class="ra-bar-label">{{ $label }}</span></div>
        @endforeach
      </div>
      <p><a href="{{ route('admin.products.import.requests') }}">Review import history →</a></p>
    </div>
  @else
    <div class="ra-chart-card">
      <h3>Assessment status</h3>
      <p>Visible impact assessments by stage · current snapshot</p>
      @php $maxIar=max(1,$draft,$submitted,$other); @endphp
      <div class="ra-bars" role="img" aria-label="{{ $draft }} drafts, {{ $submitted }} submitted, {{ $other }} other">
        @foreach (['Draft'=>$draft,'Submitted'=>$submitted,'Other'=>$other] as $label=>$value)
          <div class="ra-bar-item"><span class="ra-bar-num">{{ $value }}</span><span class="ra-bar-fill" style="--ra-bar-height:{{ round(100*$value/$maxIar) }}%"></span><span class="ra-bar-label">{{ $label }}</span></div>
        @endforeach
      </div>
    </div>
  @endif
</section>
