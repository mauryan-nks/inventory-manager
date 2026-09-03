(function(){
const sidebar=document.querySelector('.sidebar'),menus=document.querySelectorAll('[data-menu]'),overlay=document.querySelector('[data-menu-close]'),body=document.body;
const closeMenu=()=>{if(!sidebar)return;sidebar.classList.remove('open');overlay?.classList.remove('show');body.classList.remove('nav-open');menus.forEach(m=>m.setAttribute('aria-expanded','false'));};
const openMenu=()=>{if(!sidebar)return;sidebar.classList.add('open');overlay?.classList.add('show');body.classList.add('nav-open');menus.forEach(m=>m.setAttribute('aria-expanded','true'));};
menus.forEach(m=>m.addEventListener('click',()=>sidebar?.classList.contains('open')?closeMenu():openMenu()));overlay?.addEventListener('click',closeMenu);sidebar?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{if(matchMedia('(max-width:820px)').matches)closeMenu();}));window.addEventListener('resize',()=>{if(innerWidth>820)closeMenu();});document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu();});
document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.getAttribute('data-confirm')||'Are you sure?'))e.preventDefault();}));document.querySelectorAll('[data-dismiss]').forEach(el=>el.addEventListener('click',()=>el.closest('.alert')?.remove()));document.querySelectorAll('[data-autohide]').forEach(el=>setTimeout(()=>el.remove(),5000));
const base=(window.APP_BASE_URL||'/').replace(/\/$/,'/') , productUrl=base+'inventory/products/search';
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const label=p=>[p.code,p.name,p.category_name||'Uncategorized',p.unit].filter(Boolean).join(' · ');
const debounce=(fn,ms)=>{let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),ms);};};

function init(select){
 if(!select||select.dataset.productSearchReady==='1')return;
 select.dataset.productSearchReady='1';
 const wrap=document.createElement('div');wrap.className='product-combobox';
 const button=document.createElement('button');button.type='button';button.className='product-combobox-trigger';button.setAttribute('aria-haspopup','listbox');button.setAttribute('aria-expanded','false');
 const menu=document.createElement('div');menu.className='product-combobox-menu';menu.setAttribute('role','listbox');
 const box=document.createElement('input');box.type='search';box.className='product-combobox-search';box.placeholder='Search product code, name or category…';box.autocomplete='off';box.setAttribute('aria-label','Search product');
 const list=document.createElement('div');list.className='product-combobox-options';
 menu.append(box,list);wrap.append(button,menu);select.parentNode.insertBefore(wrap,select);wrap.appendChild(select);select.classList.add('product-combobox-native');
 const initial=[...select.options].map(o=>({v:o.value,t:o.text,c:o.dataset.category||'',s:o.selected}));let ctrl=null;
 const currentLabel=()=>select.options[select.selectedIndex]?.text||'Select product';
 const sync=()=>{button.textContent=currentLabel();button.classList.toggle('placeholder',!select.value);};
 const close=()=>{wrap.classList.remove('open');button.setAttribute('aria-expanded','false');};
 const open=()=>{document.querySelectorAll('.product-combobox.open').forEach(x=>{if(x!==wrap)x.classList.remove('open');});wrap.classList.add('open');button.setAttribute('aria-expanded','true');renderLocal('');setTimeout(()=>box.focus(),0);};
 const render=items=>{
   if(!items.length){list.innerHTML='<div class="product-combobox-empty">No products found</div>';return;}
   list.innerHTML=items.map(p=>`<div class="product-combobox-option${String(p.id)===String(select.value)?' selected':''}" role="option" data-value="${esc(p.id)}">${esc(label(p))}</div>`).join('');
   list.querySelectorAll('[data-value]').forEach(o=>{o.addEventListener('mousedown',e=>e.preventDefault());o.addEventListener('click',()=>{select.value=o.dataset.value;sync();select.dispatchEvent(new Event('change',{bubbles:true}));close();box.value='';});});
 };
 const renderLocal=q=>{
   q=(q||'').toLowerCase();
   const rows=initial.filter(o=>o.v&&(!q||o.t.toLowerCase().includes(q)||o.c.toLowerCase().includes(q)));
   render(rows.map(o=>({id:o.v,code:'',name:o.t,category_name:'',unit:'',category_id:o.c})));
 };
 const restore=()=>{select.innerHTML=initial.map(o=>`<option value="${esc(o.v)}" data-category="${esc(o.c)}" ${o.s?'selected':''}>${esc(o.t)}</option>`).join('');sync();};
 const search=debounce(async()=>{
   const q=box.value.trim();
   if(!q){renderLocal('');return;}
   if(ctrl)ctrl.abort();ctrl=new AbortController();wrap.classList.add('loading');
   try{
     const r=await fetch(productUrl+'?q='+encodeURIComponent(q)+'&limit=40',{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},signal:ctrl.signal,credentials:'same-origin'});
     if(!r.ok)throw new Error('search '+r.status);
     const d=await r.json();
     if(d&&d.ok)render(d.items||[]);else renderLocal(q);
   }catch(e){if(e.name!=='AbortError')renderLocal(q);}finally{wrap.classList.remove('loading');}
 },250);
 button.addEventListener('click',()=>wrap.classList.contains('open')?close():open());
 box.addEventListener('input',search);
 box.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();box.value='';restore();renderLocal('');close();button.focus();}if(e.key==='Enter'){e.preventDefault();const first=list.querySelector('[data-value]');if(first)first.click();}});
 select.addEventListener('change',sync);
 document.addEventListener('click',e=>{if(!wrap.contains(e.target))close();});
 sync();
}
const initAll=()=>document.querySelectorAll('[data-product-search],[data-product-select]').forEach(init);
initAll();
new MutationObserver(m=>{if(m.some(x=>x.addedNodes.length))initAll();}).observe(document.body,{childList:true,subtree:true});

/* Global table pagination: show first 20 rows, then paginate automatically. */
function initTablePagination(){
  document.querySelectorAll('table.table').forEach(table=>{
    if(table.dataset.paginationReady==='1') return;
    const tbody=table.tBodies?.[0];
    if(!tbody) return;
    const rows=[...tbody.querySelectorAll(':scope > tr')].filter(r=>!r.querySelector('.empty'));
    if(rows.length<=20) return;
    table.dataset.paginationReady='1';
    const wrap=table.closest('.table-wrap')||table.parentElement;
    const pager=document.createElement('div');
    pager.className='table-pagination';
    pager.innerHTML='<button type="button" class="btn small" data-page-prev>← Prev</button><span class="table-pagination-info"></span><button type="button" class="btn small" data-page-next>Next →</button>';
    wrap.parentNode.insertBefore(pager,wrap.nextSibling);
    let page=1; const size=20; const pages=Math.ceil(rows.length/size);
    const render=()=>{
      rows.forEach((r,i)=>r.hidden=!(i>=(page-1)*size&&i<page*size));
      pager.querySelector('.table-pagination-info').textContent=`Page ${page} of ${pages} · ${rows.length} records`;
      pager.querySelector('[data-page-prev]').disabled=page===1;
      pager.querySelector('[data-page-next]').disabled=page===pages;
    };
    pager.querySelector('[data-page-prev]').addEventListener('click',()=>{if(page>1){page--;render();}});
    pager.querySelector('[data-page-next]').addEventListener('click',()=>{if(page<pages){page++;render();}});
    render();
  });
}

/* Quantity calculator for Inventory IN/OUT. */
function initQuantityCalculators(){
  document.querySelectorAll('[data-qty]').forEach(input=>{
    if(input.dataset.calculatorReady==='1') return;
    input.dataset.calculatorReady='1';
    const host=document.createElement('div'); host.className='quantity-with-calculator';
    input.parentNode.insertBefore(host,input); host.appendChild(input);
    const btn=document.createElement('button'); btn.type='button'; btn.className='quantity-calculator-btn'; btn.textContent='Calc'; btn.setAttribute('aria-label','Open quantity calculator');
    host.appendChild(btn);
    const pop=document.createElement('div'); pop.className='quantity-calculator'; pop.hidden=true;
    pop.innerHTML=`<div class="quantity-calculator-head"><strong>Quantity calculator</strong><button type="button" class="quantity-calculator-close" aria-label="Close">×</button></div>
      <input class="quantity-calculator-display" type="text" inputmode="decimal" placeholder="e.g. 100+25*2" autocomplete="off">
      <div class="quantity-calculator-result" aria-live="polite">Result: —</div>
      <div class="quantity-calculator-keys">
        <button type="button" data-calc="7">7</button><button type="button" data-calc="8">8</button><button type="button" data-calc="9">9</button><button type="button" data-calc="/">÷</button>
        <button type="button" data-calc="4">4</button><button type="button" data-calc="5">5</button><button type="button" data-calc="6">6</button><button type="button" data-calc="*">×</button>
        <button type="button" data-calc="1">1</button><button type="button" data-calc="2">2</button><button type="button" data-calc="3">3</button><button type="button" data-calc="-">−</button>
        <button type="button" data-calc="0">0</button><button type="button" data-calc=".">.</button><button type="button" data-calc="%">%</button><button type="button" data-calc="+">+</button>
      </div>
      <div class="quantity-calculator-actions"><button type="button" class="btn small ghost" data-calc-clear>Clear</button><button type="button" class="btn small primary" data-calc-use>Use result</button></div>`;
    document.body.appendChild(pop);
    const display=pop.querySelector('.quantity-calculator-display'), result=pop.querySelector('.quantity-calculator-result');
    const safeEval=expr=>{
      let x=String(expr||'').replace(/\s+/g,'');
      if(!x||!/^[0-9+\-*/%.()]+$/.test(x)||/[+\-*/%.]{2,}/.test(x.replace(/\(\-/g,''))) return null;
      x=x.replace(/(\d+(?:\.\d+)?)%/g,'($1/100)');
      try{const v=Function('"use strict";return ('+x+')')(); return Number.isFinite(v)?v:null;}catch(e){return null;}
    };
    const calculate=()=>{const v=safeEval(display.value);result.textContent=v===null?'Result: —':'Result: '+v.toLocaleString(undefined,{maximumFractionDigits:4});return v;};
    pop.querySelectorAll('[data-calc]').forEach(k=>k.addEventListener('click',()=>{display.value+=k.dataset.calc;calculate();display.focus();}));
    display.addEventListener('input',calculate);
    pop.querySelector('[data-calc-clear]').addEventListener('click',()=>{display.value='';result.textContent='Result: —';display.focus();});
    pop.querySelector('[data-calc-use]').addEventListener('click',()=>{const v=calculate();if(v!==null){input.value=String(Math.max(1,Math.round(v)));input.dispatchEvent(new Event('input',{bubbles:true}));close();}});
    const close=()=>{pop.hidden=true;btn.setAttribute('aria-expanded','false');};
    const open=()=>{document.querySelectorAll('.quantity-calculator:not([hidden])').forEach(x=>x.hidden=true);pop.hidden=false;btn.setAttribute('aria-expanded','true');const r=btn.getBoundingClientRect();pop.style.top=(r.bottom+8+scrollY)+'px';pop.style.left=Math.min(Math.max(8,r.left+scrollX),innerWidth-pop.offsetWidth-8)+'px';display.value=input.value||'';calculate();setTimeout(()=>display.focus(),0);};
    btn.setAttribute('aria-expanded','false');btn.addEventListener('click',e=>{e.stopPropagation();pop.hidden?open():close();});
    pop.addEventListener('click',e=>e.stopPropagation()); document.addEventListener('click',close);
  });
}

initTablePagination();
initQuantityCalculators();
new MutationObserver(()=>{initTablePagination();initQuantityCalculators();}).observe(document.body,{childList:true,subtree:true});

})();
