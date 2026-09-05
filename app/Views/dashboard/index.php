<?= $this->include('layouts/header') ?>
<?php
$hour = (int)date('G');
$greeting = $hour >= 5 && $hour < 12 ? 'Good morning' : ($hour >= 12 && $hour < 17 ? 'Good afternoon' : ($hour >= 17 && $hour < 21 ? 'Good evening' : 'Good night'));
?>
<div class="page-head"><div><div class="page-kicker">Overview · IST</div><h1 class="page-title"><?=esc($greeting)?>, <?=esc($userName ?: 'there')?>.</h1><p class="page-subtitle">A live view of stock, movements and items that need attention.</p></div><div class="head-actions"><a class="btn" href="<?=site_url('inventory')?>">View stock</a><?php if($canIn): ?><a class="btn success" href="<?=site_url('inventory/in')?>">+ Record IN</a><?php endif; ?><?php if($canOut): ?><a class="btn primary" href="<?=site_url('inventory/out')?>">− Record OUT</a><?php endif; ?></div></div>
<div class="card hero" style="margin-bottom:18px"><div class="card-body"><div class="hero-kicker">Inventory health</div><div class="hero-title">Keep every movement traceable from gate entry to final issue.</div><div class="hero-copy">Use the ledger for stock changes, the security desk for incoming documents, and reports for clean audit-ready reporting.</div><div style="display:flex;align-items:center;gap:24px;margin-top:20px;flex-wrap:wrap"><div><div style="font-size:12px;color:#cfd1e8">Current units on hand</div><div style="font-size:30px;font-weight:800"><?=number_format((int)round((float)$currentUnits))?></div></div><div><div style="font-size:12px;color:#cfd1e8">Low-stock items</div><div style="font-size:30px;font-weight:800"><?=number_format((int)$lowStock)?></div></div></div></div></div>
<div class="grid grid-4" style="margin-bottom:18px">
 <div class="card metric"><div class="metric-top"><div class="metric-label">Active products</div><div class="metric-icon">▦</div></div><div class="metric-value"><?=number_format((int)$productCount)?></div><div class="metric-note">Product master</div></div>
 <div class="card metric"><div class="metric-top"><div class="metric-label">Today's IN</div><div class="metric-icon">↑</div></div><div class="metric-value"><?=number_format((int)$todayIn)?></div><div class="metric-note good">Confirmed receipts</div></div>
 <div class="card metric"><div class="metric-top"><div class="metric-label">Today's OUT</div><div class="metric-icon">↓</div></div><div class="metric-value"><?=number_format((int)$todayOut)?></div><div class="metric-note bad">Confirmed issues</div></div>
 <div class="card metric"><div class="metric-top"><div class="metric-label">Low stock</div><div class="metric-icon">!</div></div><div class="metric-value"><?=number_format((int)$lowStock)?></div><div class="metric-note warn">Needs attention</div></div>
</div>
<div class="card" style="margin-bottom:18px"><div class="section-head"><div><h2 class="section-title">Quick actions</h2><div class="section-meta">Jump into the most common tasks.</div></div></div><div class="card-body"><div class="quick-grid"><?php if($canIn): ?><a class="quick" href="<?=site_url('inventory/in')?>"><div class="quick-icon">↑</div><div><strong>Product IN</strong><span>Receive stock into ledger</span></div></a><?php endif; ?><?php if($canOut): ?><a class="quick" href="<?=site_url('inventory/out')?>"><div class="quick-icon">↓</div><div><strong>Product OUT</strong><span>Issue stock safely</span></div></a><?php endif; ?><?php if($canProducts): ?><a class="quick" href="<?=site_url('products')?>"><div class="quick-icon">▦</div><div><strong>Products</strong><span>Search and maintain catalog</span></div></a><?php endif; ?><?php if($canSecurity): ?><a class="quick" href="<?=site_url('security')?>"><div class="quick-icon">⌁</div><div><strong>Security desk</strong><span>Scan incoming documents</span></div></a><?php endif; ?></div></div></div>
<div class="grid grid-2"><div class="card"><div class="section-head"><div><h2 class="section-title">Recent activity</h2><div class="section-meta">Latest inventory movements</div></div><a class="btn small" href="<?=site_url('inventory/transactions')?>">View all</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Transaction</th><th>Type</th><th>User</th><th>When</th><th>Status</th></tr></thead><tbody><?php if(!$recent): ?><tr><td colspan="5"><div class="empty"><strong>No movements yet</strong>Transactions will appear here after the first confirmed IN or OUT.</div></td></tr><?php else: foreach($recent as $r): ?><tr><td class="mono"><?=esc($r['transaction_no'])?></td><td><span class="badge <?=strtoupper($r['type'])==='IN'?'success':'danger'?>"><?=esc($r['type'])?></span></td><td><?=esc($r['user_name'] ?? 'System')?></td><td><?=esc($r['created_at'])?></td><td><span class="badge <?=esc(strtolower($r['status']))==='confirmed'?'success':'neutral'?>"><?=esc($r['status'])?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
<div class="card"><div class="section-head"><div><h2 class="section-title">Stock watchlist</h2><div class="section-meta">Products at or below minimum</div></div><?php if($canProducts): ?><a class="btn small" href="<?=site_url('products')?>">Manage</a><?php endif; ?></div><div class="card-body"><?php if(!$lowItems): ?><div class="empty" style="padding:35px 10px"><strong>Stock looks healthy</strong>No products are currently below their threshold.</div><?php else: foreach(array_slice($lowItems,0,5) as $r): $pct=(float)$r['minimum_stock']>0?min(100,((float)$r['current_stock']/(float)$r['minimum_stock'])*100):100; ?><div style="margin-bottom:16px"><div class="split"><div><strong><?=esc($r['name'])?></strong><div class="muted" style="font-size:10px"><?=esc($r['code'])?> · <?=esc($r['unit'])?></div></div><span class="badge danger"><?=number_format((int)round((float)$r['current_stock']))?></span></div><div class="progress" style="margin-top:8px"><span style="width:<?=$pct?>%"></span></div></div><?php endforeach; endif; ?></div></div></div>
<?= $this->include('layouts/footer') ?>

<?php if($canVisitorApprove): ?>
<div id="visitor-approval-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.58);align-items:center;justify-content:center;padding:18px">
  <div style="width:min(560px,100%);max-height:90vh;overflow:auto;background:#fff;color:#15151c;border-radius:18px;box-shadow:0 25px 80px rgba(0,0,0,.3);padding:22px">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start"><div><div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;opacity:.6">Security visitor request</div><h2 style="margin:5px 0 0;font-size:22px">Visitor waiting for your approval</h2></div><button type="button" class="btn small" id="visitor-modal-close">×</button></div>
    <div id="visitor-request-list" style="margin-top:16px"></div>
  </div>
</div>
<script>
(function(){
 const modal=document.getElementById('visitor-approval-modal'), list=document.getElementById('visitor-request-list'), close=document.getElementById('visitor-modal-close');
 const pendingUrl=<?=json_encode(site_url('security/visitors/pending'))?>;
 const csrfName=<?=json_encode(csrf_token())?>, csrfHash=<?=json_encode(csrf_hash())?>;
 let requests=[];
 close.addEventListener('click',()=>modal.style.display='none');
 function escHtml(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
 function render(){
   if(!requests.length){modal.style.display='none';return;}
   list.innerHTML=requests.map(v=>`<div style="border:1px solid #e4e4e8;border-radius:14px;padding:13px;margin-bottom:10px;display:flex;gap:12px;align-items:center">${v.photo_path?`<img src="<?=site_url('security/visitors')?>/${v.id}/photo" style="width:68px;height:68px;object-fit:cover;border-radius:12px" alt="">`:''}<div style="flex:1;min-width:0"><strong style="font-size:16px">${escHtml(v.name)}</strong><div style="font-size:12px;margin-top:3px">Purpose: ${escHtml(v.purpose||'—')}</div><div style="font-size:11px;opacity:.65;margin-top:3px">Arrived: ${escHtml(v.entry_at)} IST · Security: ${escHtml(v.guard_name||'—')}</div><div style="display:flex;gap:8px;margin-top:10px"><button class="btn primary small" type="button" data-approve="${v.id}">✓ Approve Entry</button><button class="btn small" type="button" data-reject="${v.id}">Reject</button></div></div></div>`).join('');
   modal.style.display='flex';
 }
 async function load(){try{const r=await fetch(pendingUrl,{headers:{'Accept':'application/json'},cache:'no-store'});const j=await r.json();if(j.ok){requests=j.items||[];render();}}catch(e){}}
 async function act(id,action){
   const body=new URLSearchParams();body.set(csrfName,csrfHash);
   if(action==='reject')body.set('reason','Owner rejected the visitor entry.');
   try{const r=await fetch(`<?=site_url('security/visitors')?>/${id}/${action}`,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body});const j=await r.json();if(j.ok){requests=requests.filter(x=>String(x.id)!==String(id));render();}else alert(j.message||'Action failed.');}catch(e){alert('Could not contact the security desk.');}
 }
 list.addEventListener('click',e=>{const a=e.target.closest('[data-approve]'),r=e.target.closest('[data-reject]');if(a)act(a.dataset.approve,'approve');if(r&&confirm('Reject this visitor entry?'))act(r.dataset.reject,'reject');});
 load();setInterval(load,5000);
})();
</script>
<?php endif; ?>
