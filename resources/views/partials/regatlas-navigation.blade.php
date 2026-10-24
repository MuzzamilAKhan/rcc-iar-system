<script>(function(){try{if(localStorage.getItem('regatlas-theme')==='dark')document.documentElement.dataset.raTheme='dark'}catch(e){}})();</script>
@auth
<aside class="ra-sidebar" id="ra-sidebar" aria-label="Workspace navigation">
  <a class="ra-sidebar-brand" href="{{ route('dashboard') }}"><span class="ra-logo">R</span><span><strong>RegAtlas</strong><small>Regulatory Workspace</small></span></a>
  <p class="ra-sidebar-label">WORKSPACE</p>
  <nav class="ra-sidebar-menu" aria-label="Primary navigation">
    <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><span aria-hidden="true">▦</span> Dashboard</a>
    @can('iar.create')<a href="{{ route('iar.create') }}" @if(request()->routeIs('iar.create')) aria-current="page" @endif><span aria-hidden="true">＋</span> New assessment</a>@endcan
    @if(auth()->user()->can('iar.view.own') || auth()->user()->can('iar.view.all') || auth()->user()->can('iar.view.assigned'))
      <a href="{{ route('iar.index') }}" @if(request()->routeIs('iar.index') || request()->routeIs('iar.edit') || request()->routeIs('iar.show') || request()->routeIs('iar.readiness')) aria-current="page" @endif><span aria-hidden="true">▤</span> Impact assessments</a>
    @endif
    @can('assessment.edit.assigned')<a href="{{ route('assessment.index') }}" @if(request()->routeIs('assessment.*')) aria-current="page" @endif><span aria-hidden="true">✓</span> My assessments</a>@endcan
    @can('manage-master-data')
      <a href="{{ route('admin.master-data') }}" @if(request()->routeIs('admin.master-data')) aria-current="page" @endif><span aria-hidden="true">▣</span> Master data</a>
      <a href="{{ route('admin.products') }}" @if(request()->routeIs('admin.products') || request()->routeIs('admin.products.show')) aria-current="page" @endif><span aria-hidden="true">◈</span> Product registry</a>
      <a href="{{ route('admin.products.import.requests') }}" @if(request()->routeIs('admin.products.import.requests*') || request()->routeIs('admin.products.import.evidence')) aria-current="page" @endif><span aria-hidden="true">◇</span> Import approvals</a>
    @endcan
    @if(auth()->user()->can('manage-master-data') || auth()->user()->hasRole('global-ra'))
      <a href="{{ route('admin.import-issues') }}" @if(request()->routeIs('admin.import-issues')) aria-current="page" @endif><span aria-hidden="true">⚑</span> Import issues</a>
    @endif
    @role('super-admin')<a href="{{ route('admin.users') }}" @if(request()->routeIs('admin.users')) aria-current="page" @endif><span aria-hidden="true">♙</span> Users</a>@endrole
  </nav>
  <div class="ra-sidebar-bottom">
    <a href="{{ route('profile.timezone.edit') }}" @if(request()->routeIs('profile.timezone.*')) aria-current="page" @endif><span aria-hidden="true">◷</span> My timezone</a>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><span aria-hidden="true">↪</span> Sign out</button></form>
  </div>
</aside>
<header class="ra-workspace-bar" role="banner">
  <button type="button" class="ra-menu-toggle" id="ra-menu-toggle" aria-controls="ra-sidebar" aria-expanded="false" aria-label="Toggle menu">☰</button>
  <div class="ra-workspace-context"><strong>RegAtlas</strong><span>Regulatory intelligence workspace</span></div>
  <div class="ra-workspace-tools">
    <span class="ra-user-id" title="Signed-in account">{{ auth()->user()->name }}</span>
    <button type="button" class="ra-theme-toggle" id="ra-theme-toggle" aria-label="Switch to dark mode" aria-pressed="false" title="Toggle light or dark appearance"><span id="ra-theme-icon" aria-hidden="true">☾</span><span id="ra-theme-label">Dark mode</span></button>
  </div>
</header>
<div class="ra-mobile-scrim" id="ra-mobile-scrim" hidden></div>
@endauth

<script src="{{ asset('regatlas-ui.js') }}" defer></script>
