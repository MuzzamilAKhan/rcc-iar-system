<div class="mx-auto max-w-7xl p-6">
 <a href="{{ route('admin.products') }}" class="text-blue-700">&larr; Product Registry</a>
 <section class="mt-6 rounded-xl bg-white p-6 shadow-sm">
 <h1 class="text-2xl font-bold">Product Registry — Review Excel Changes</h1>
 <p class="mt-2 text-sm text-amber-800">PREVIEW ONLY — no Product Registry changes are saved or approved by this screen. Approval workflow will be implemented separately.</p>
 <p class="mt-2 text-sm text-slate-600">Upload .xlsx or .csv (max 4 MB / 1,000 data rows). Required headers: SAP Code, Country of Registration, Registration Status. Optional: Registration Number. Country names must match existing registry values.</p>
 <form wire:submit="preview" class="mt-5 flex flex-wrap items-center gap-3">
  <input type="file" wire:model="registryFile" accept=".xlsx,.csv" class="max-w-full rounded border p-2">
  <button type="submit" wire:loading.attr="disabled" class="rounded bg-blue-700 px-5 py-3 font-semibold text-white disabled:opacity-50">Preview Proposed Changes</button>
  <span wire:loading wire:target="registryFile,preview">Reading file...</span>
 </form>
 @error('registryFile')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
 @if($summary)
  <p class="mt-5 rounded bg-slate-100 p-3 text-sm">Unchanged: <strong>{{ $summary['unchanged'] }}</strong> &nbsp; Proposed Updates: <strong>{{ $summary['proposed_update'] }}</strong> &nbsp; New Country Registrations: <strong>{{ $summary['proposed_new_registration'] }}</strong> &nbsp; Blocked: <strong>{{ $summary['blocked'] }}</strong></p>
  <div class="mt-4 overflow-auto"><table class="w-full min-w-[1000px] text-left text-sm"><thead class="bg-slate-100"><tr><th class="p-2">Excel Row</th><th class="p-2">SAP Code</th><th class="p-2">Country</th><th class="p-2">Current Status</th><th class="p-2">Proposed Status</th><th class="p-2">Current Number</th><th class="p-2">Proposed Number</th><th class="p-2">Result</th></tr></thead><tbody>
  @foreach($previewRows as $item)<tr class="border-t"><td class="p-2">{{ $item['row'] }}</td><td class="p-2">{{ $item['sap_code'] }}</td><td class="p-2">{{ $item['country'] }}</td><td class="p-2">{{ $item['current_status'] }}</td><td class="p-2">{{ $item['proposed_status'] }}</td><td class="p-2">{{ $item['current_number'] }}</td><td class="p-2">{{ $item['proposed_number'] }}</td><td class="p-2">{{ str_replace('_',' ', $item['result']) }} @if($item['reason']) — {{ $item['reason'] }} @endif</td></tr>@endforeach
  </tbody></table></div>
  <p class="mt-4 text-sm font-medium text-amber-800">This preview does not create a pending approval request. Do not treat it as an authorized or applied master-data change.</p>
 @endif
 </section>
</div>
