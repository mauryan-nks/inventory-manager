<?= $this->include('layouts/header') ?>
<?php
$statusFilter = $statusFilter ?? 'active';
$allCount = (int)($allCount ?? count($products));
$activeCount = (int)($activeCount ?? 0);
$inactiveCount = (int)($inactiveCount ?? 0);
?>
<div class="page-head">
  <div>
    <div class="page-kicker">Catalog</div>
    <h1 class="page-title">Products</h1>
    <p class="page-subtitle">Manage products, unlimited size variants and their live stock balances.</p>
  </div>
  <div class="head-actions">
    <?php if($authNav->can('products.view')): ?><a class="btn" href="<?=site_url('products/categories')?>">Manage categories</a><?php endif; ?>
    <?php if($authNav->can('products.create')): ?><a class="btn primary" href="<?=site_url('products/create')?>">+ Add product</a><?php endif; ?>
  </div>
</div>

<div class="kpi-tabs card">
  <a class="kpi-tab <?= $statusFilter==='active'?'active':'' ?>" href="<?=site_url('products')?>?status=active<?= $q!==''?'&q='.urlencode($q):'' ?>"><span>Active products</span><strong><?=number_format($activeCount)?></strong></a>
  <a class="kpi-tab <?= $statusFilter==='inactive'?'active':'' ?>" href="<?=site_url('products')?>?status=inactive<?= $q!==''?'&q='.urlencode($q):'' ?>"><span>Inactive</span><strong><?=number_format($inactiveCount)?></strong></a>
  <a class="kpi-tab <?= $statusFilter==='all'?'active':'' ?>" href="<?=site_url('products')?>?status=all<?= $q!==''?'&q='.urlencode($q):'' ?>"><span>All records</span><strong><?=number_format($allCount)?></strong></a>
</div>

<?php if(session()->getFlashdata('success')): ?><div class="alert success"><?=esc(session()->getFlashdata('success'))?></div><?php endif; ?>
<?php if(session()->getFlashdata('error')): ?><div class="alert error"><?=esc(session()->getFlashdata('error'))?></div><?php endif; ?>

<div class="card">
  <div class="toolbar">
    <form class="search" method="get" action="<?=site_url('products')?>">
      <input type="hidden" name="status" value="<?=esc($statusFilter)?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="6"/><path d="m16 16 5 5"/></svg>
      <input name="q" value="<?=esc($q ?? '')?>" placeholder="Search code, product or category…">
    </form>
    <div class="section-meta"><?=number_format(count($products))?> shown</div>
  </div>
  <div class="table-wrap">
    <table class="table product-table">
      <thead><tr><th>Product</th><th>Category</th><th>Quantity unit</th><th>Stock by variant</th><th>Minimum</th><th>State</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if(!$products): ?>
        <tr><td colspan="7"><div class="empty"><strong>No products found</strong>Try another filter or create a new product.</div></td></tr>
      <?php else: foreach($products as $p):
          $stock=(float)($stockMap[(int)$p['id']]??0); $min=(float)$p['minimum_stock']; $low=$stock <= $min; $isActive=(int)$p['status']===1;
          $variantsFor=$variantMap[(int)$p['id']]??[];
      ?>
        <tr class="<?= $isActive?'':'row-muted' ?>">
          <td><div class="product-cell"><span class="product-dot <?= $isActive?'':'inactive' ?>"></span><div><strong><?=esc($p['name'])?></strong><div class="muted mono"><?=esc($p['code'])?></div></div></div></td>
          <td><?=esc($p['category_name'] ?? 'Uncategorized')?></td>
          <td><span class="unit-pill"><?=esc($p['unit'])?></span></td>
          <td>
            <div class="stock-total"><strong><?=esc(inventory_quantity_label($stock, (string)$p['unit']))?></strong><span class="muted">total</span></div>
            <div class="variant-list">
              <?php foreach($variantsFor as $v): ?>
                <span class="variant-chip <?=((int)($v['status']??1)===1?'':'archived')?>">
                  <span><?=esc(inventory_variant_attributes_label($v['attributes_json'] ?? null, (string)$v['variant_name']))?></span><b><?=esc(inventory_quantity_label((float)$v['current_stock'], (string)$p['unit']))?></b>
                </span>
              <?php endforeach; ?>
            </div>
          </td>
          <td><?=esc(inventory_quantity_label($min, (string)$p['unit']))?></td>
          <td><span class="badge <?=$low?'danger':'success'?>"><?=$low?'Low stock':'Healthy'?></span><div class="muted status-sub"><?= $isActive?'Active':'Inactive' ?></div></td>
          <td class="table-actions">
            <?php if($isActive && $authNav->can('products.edit')): ?><a class="btn small" href="<?=site_url('products/'.$p['id'].'/edit')?>">Edit</a><?php endif; ?>
            <?php if($isActive && $authNav->can('products.delete')): ?>
              <form method="post" action="<?=site_url('products/'.$p['id'].'/delete')?>" onsubmit="return confirm('Deactivate this product? It will stay in history and can be activated again.')"><?=csrf_field()?><button class="btn small ghost" type="submit">Deactivate</button></form>
              <form method="post" action="<?=site_url('products/'.$p['id'].'/hard-delete')?>" onsubmit="return confirm('Permanently delete this product? This is only allowed when it has no transaction history.')"><?=csrf_field()?><button class="btn small danger" type="submit">Delete</button></form>
            <?php endif; ?>
            <?php if(!$isActive && $authNav->can('products.edit')): ?>
              <form method="post" action="<?=site_url('products/'.$p['id'].'/activate')?>"><?=csrf_field()?><button class="btn small success" type="submit">Activate</button></form>
            <?php endif; ?>
            <?php if(!$isActive && $authNav->can('products.delete')): ?>
              <form method="post" action="<?=site_url('products/'.$p['id'].'/hard-delete')?>" onsubmit="return confirm('Permanently delete this product? This is allowed only when it has no transaction history.')"><?=csrf_field()?><button class="btn small danger" type="submit">Delete</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?= $this->include('layouts/footer') ?>