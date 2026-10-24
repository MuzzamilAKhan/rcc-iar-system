/* RegAtlas progressive enhancement: no AJAX navigation or interception of Laravel forms. */
(()=>{
  const root=document.documentElement;
  const pref=()=>{try{return localStorage.getItem('regatlas-theme')}catch(_){return null}};
  const apply=(mode)=>{if(mode==='dark')root.dataset.raTheme='dark';else delete root.dataset.raTheme;const b=document.getElementById('ra-theme-toggle');const l=document.getElementById('ra-theme-label');const i=document.getElementById('ra-theme-icon');if(b){b.setAttribute('aria-pressed',String(mode==='dark'));b.setAttribute('aria-label',mode==='dark'?'Switch to light mode':'Switch to dark mode')}if(l)l.textContent=mode==='dark'?'Light mode':'Dark mode';if(i)i.textContent=mode==='dark'?'☀':'☾';};
  const initial=pref();apply(initial==='dark'?'dark':'light');
  document.getElementById('ra-theme-toggle')?.addEventListener('click',()=>{const next=root.dataset.raTheme==='dark'?'light':'dark';apply(next);try{localStorage.setItem('regatlas-theme',next)}catch(_){}});
  const toggle=document.getElementById('ra-menu-toggle'),scrim=document.getElementById('ra-mobile-scrim');
  const close=()=>{document.body.classList.remove('ra-nav-open');toggle?.setAttribute('aria-expanded','false');if(scrim)scrim.hidden=true};
  toggle?.addEventListener('click',()=>{const show=!document.body.classList.contains('ra-nav-open');document.body.classList.toggle('ra-nav-open',show);toggle.setAttribute('aria-expanded',String(show));if(scrim)scrim.hidden=!show});
  scrim?.addEventListener('click',close);document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
})();
