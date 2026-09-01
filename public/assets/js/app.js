
(function(){
  const sidebar=document.querySelector('.sidebar');
  const menu=document.querySelector('[data-menu]');
  if(menu && sidebar) menu.addEventListener('click',()=>sidebar.classList.toggle('open'));
  document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{
    const message=el.getAttribute('data-confirm')||'Are you sure?';
    if(!window.confirm(message)) e.preventDefault();
  }));
  document.querySelectorAll('[data-dismiss]').forEach(el=>el.addEventListener('click',()=>el.closest('.alert')?.remove()));
  document.querySelectorAll('[data-autohide]').forEach(el=>setTimeout(()=>el.remove(),5000));
})();
