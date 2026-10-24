@auth
<header class="ra-topbar" role="banner">
  <div class="ra-topbar-inner">
    <a class="ra-brand" href="{{ route('dashboard') }}" aria-label="RegAtlas Dashboard">
      <span class="ra-brand-mark" aria-hidden="true">R</span>
      <span>RegAtlas <span class="ra-brand-sub" style="display:block">Regulatory intelligence workspace</span></span>
    </a>
    <nav class="ra-links" aria-label="Primary navigation">
      <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Dashboard</a>
      @if(auth()->user()->can('iar.view.own') || auth()->user()->can('iar.view.all') || auth()->user()->can('iar.view.assigned'))<a href="{{ route('iar.index') }}" @if(request()->routeIs('iar.*')) aria-current="page" @endif>IARs</a>@endif
      @can('assessment.edit.assigned')<a href="{{ route('assessment.index') }}" @if(request()->routeIs('assessment.*')) aria-current="page" @endif>Assessments</a>@endcan
      @can('manage-master-data')<a href="{{ route('admin.master-data') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>Master Data</a>@endcan
      <a href="{{ route('profile.timezone.edit') }}" @if(request()->routeIs('profile.timezone.*')) aria-current="page" @endif>Timezone</a>
    </nav>
    <div class="ra-account">
      <span>{{ auth()->user()->name }}</span>
      <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
    </div>
  </div>
</header>
@endauth
