@auth
<section class="ra-timezone-banner" id="ra-timezone-reminder" aria-label="Timezone setup reminder">
  <div>
    <strong>Before you begin, confirm your timezone</strong>
    <p>Your current account setting is <b>{{ auth()->user()->timezone ?: 'UTC' }}</b>. Set your local timezone to see dates and audit timestamps correctly throughout your workspace.</p>
  </div>
  <div class="ra-timezone-actions">
    <a class="ra-timezone-primary" href="{{ route('profile.timezone.edit') }}">Set my timezone &rarr;</a>
    <button class="ra-timezone-dismiss" id="ra-timezone-dismiss" type="button">Remind me later</button>
  </div>
</section>
<script>
(function(){
 const reminder=document.getElementById('ra-timezone-reminder');
 if(!reminder)return;
 // A reminder for each browser session; never blocks navigation.
 try { if(sessionStorage.getItem('regatlas-tz-reminder-dismissed')==='1') reminder.hidden=true; }catch(e){}
 document.getElementById('ra-timezone-dismiss')?.addEventListener('click',()=>{
   reminder.hidden=true;
   try{sessionStorage.setItem('regatlas-tz-reminder-dismissed','1');}catch(e){}
 });
})();
</script>
@endauth
