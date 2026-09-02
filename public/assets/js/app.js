(function(){
  const sidebar=document.querySelector('.sidebar');
  const menus=document.querySelectorAll('[data-menu], .mobile-menu-fab');
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
  window.addEventListener('resize',()=>{ if(window.innerWidth>820) closeMenu(); });
  document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeMenu(); });

  // Language selector: the selected language is saved in the session, then the
  // current page is reloaded in that language. Hinglish is a UI dictionary mode.
  const langForm=document.querySelector('[data-language-form]');
  const langSelect=document.querySelector('[data-language-select]');
  langSelect?.addEventListener('change',()=>{
    if(!langForm) return;
    const base=(langForm.getAttribute('data-language-base')||'').replace(/\/$/,'');
    langForm.action=base+'/'+encodeURIComponent(langSelect.value);
    langForm.submit();
  });

  // Lightweight UI translation layer. Exact UI strings only; business data is never translated.
  const locale=body?.dataset.locale||'en';
  const dict=(window.APP_I18N&&window.APP_I18N[locale])||{};
  if(locale!=='en' && Object.keys(dict).length){
    const normalize=v=>String(v??'').replace(/\s+/g,' ').trim();
    const translateValue=value=>{ const key=normalize(value); return key && dict[key] ? dict[key] : value; };
    const translateElement=node=>{
      if(!node || node.nodeType!==Node.ELEMENT_NODE) return;
      if(node.matches('script,style,noscript,[data-i18n-skip]') || node.hasAttribute('data-i18n-done')) return;
      node.childNodes.forEach(child=>{
        if(child.nodeType===Node.TEXT_NODE){
          const raw=child.nodeValue||'', key=normalize(raw);
          if(key && dict[key]) child.nodeValue=raw.replace(key,dict[key]);
        } else if(child.nodeType===Node.ELEMENT_NODE) translateElement(child);
      });
      ['placeholder','title','aria-label'].forEach(attr=>{
        const val=node.getAttribute(attr), translated=translateValue(val);
        if(translated!==val) node.setAttribute(attr,translated);
      });
      node.setAttribute('data-i18n-done','1');
    };
    document.body.querySelectorAll('*').forEach(translateElement);
    const observer=new MutationObserver(mutations=>mutations.forEach(m=>m.addedNodes.forEach(n=>{
      if(n.nodeType===Node.ELEMENT_NODE) translateElement(n);
    })));
    observer.observe(document.body,{childList:true,subtree:true});
    setTimeout(()=>observer.disconnect(),15000);
  }

  document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{
    const message=el.getAttribute('data-confirm')||'Are you sure?';
    if(!window.confirm(message)) e.preventDefault();
  }));
  document.querySelectorAll('[data-dismiss]').forEach(el=>el.addEventListener('click',()=>el.closest('.alert')?.remove()));
  document.querySelectorAll('[data-autohide]').forEach(el=>setTimeout(()=>el.remove(),5000));
})();
