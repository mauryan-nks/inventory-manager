(function(){
  const sidebar=document.querySelector('.sidebar');
  const menus=document.querySelectorAll('[data-menu]');
  const overlay=document.querySelector('[data-menu-close]');
  const body=document.body;
  const closeMenu=()=>{
    if(!sidebar) return;
    sidebar.classList.remove('open');
    overlay?.classList.remove('show');
    body.classList.remove('nav-open');
    menus.forEach(m=>m.setAttribute('aria-expanded','false'));
  };
  const openMenu=()=>{
    if(!sidebar) return;
    sidebar.classList.add('open');
    overlay?.classList.add('show');
    body.classList.add('nav-open');
    menus.forEach(m=>m.setAttribute('aria-expanded','true'));
  };
  menus.forEach(menu=>menu.addEventListener('click',()=>sidebar?.classList.contains('open') ? closeMenu() : openMenu()));
  overlay?.addEventListener('click',closeMenu);
  sidebar?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{
    if(window.matchMedia('(max-width: 820px)').matches) closeMenu();
  }));
  window.addEventListener('resize',()=>{
    if(window.innerWidth>820) closeMenu();
  });
  document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeMenu(); });

  // Language selector: navigate to a dedicated language URL. This avoids the
  // previous POST-to-/hinglish or POST-to-/hi routing bug entirely.
  document.querySelectorAll('[data-language-select]').forEach(select => {
    select.addEventListener('change', () => {
      const base = select.getAttribute('data-language-url');
      const redirect = select.getAttribute('data-language-redirect') || '/dashboard';
      const lang = select.value;
      if (!base || !['en','hi','hinglish'].includes(lang)) return;
      window.location.assign(base.replace(/\/$/, '') + '/' + encodeURIComponent(lang) + '?redirect=' + encodeURIComponent(redirect));
    });
  });

  document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{
    const message=el.getAttribute('data-confirm')||'Are you sure?';
    if(!window.confirm(message)) e.preventDefault();
  }));
  document.querySelectorAll('[data-dismiss]').forEach(el=>el.addEventListener('click',()=>el.closest('.alert')?.remove()));
  document.querySelectorAll('[data-autohide]').forEach(el=>setTimeout(()=>el.remove(),5000));
})();
