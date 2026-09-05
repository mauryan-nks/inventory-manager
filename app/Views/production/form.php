<?= $this->include('layouts/header') ?>
<link rel="stylesheet" href="<?=base_url('assets/css/production.css')?>?v=20260903prod2">
<div class="page-head"><div><div class="page-kicker">Factory</div><h1 class="page-title">Add Factory Production</h1><p class="page-subtitle">Keep production entry simple: date, operator, machine, item, category and quantity.</p></div><div class="head-actions"><a class="btn" href="<?=site_url('production')?>">← Production</a></div></div>
<div class="card form-card production-form-card" style="max-width:900px"><form method="post" action="<?=site_url('production/store')?>"><?=csrf_field()?>
<div class="form-grid two">
<div class="field"><label>Production date</label><input type="date" name="production_date" value="<?=esc(old('production_date',date('Y-m-d')))?>" required></div>
<div class="field"><label>Operator name</label><input type="text" name="operator_name" value="<?=esc(old('operator_name'))?>" maxlength="150" placeholder="Enter operator name" required></div>
<div class="field"><label>Machine name</label><input type="text" name="machine_name" value="<?=esc(old('machine_name'))?>" maxlength="150" placeholder="Enter machine name / number" required></div>
<div class="field"><label>Item name</label><select name="product_id" id="production-product" required><option value="">Select item</option><?php foreach($products as $p): ?><option value="<?=$p['id']?>" data-category="<?=$p['category_id']??''?>" <?=((string)old('product_id')===(string)$p['id'])?'selected':''?>><?=esc($p['name'].(!empty($p['code'])?' · '.$p['code']:''))?></option><?php endforeach; ?></select></div>
<div class="field"><label>Category</label><select name="category_id" id="production-category" required><option value="">Select category</option><?php foreach($categories as $c): ?><option value="<?=$c['id']?>" <?=((string)old('category_id')===(string)$c['id'])?'selected':''?>><?=esc($c['name'])?></option><?php endforeach; ?></select><div class="hint">Categories are shared with Product Master. Manage them from Products → Categories.</div></div>
<div class="field"><label>Quantity</label><input type="number" name="quantity" min="1" step="1" value="<?=esc(old('quantity'))?>" placeholder="e.g. 1000" required><div class="hint">Whole-number production quantity.</div></div>
</div>
<div class="alert info" style="margin-top:18px"><strong>Stock effect:</strong> Saving this entry creates a confirmed Inventory IN movement tagged as Factory Production, so the produced quantity immediately becomes available in stock.</div>
<div class="form-actions"><a class="btn" href="<?=site_url('production')?>">Cancel</a><button class="btn success" type="submit">Save Production →</button></div></form></div>
<script>
const productSelect=document.getElementById('production-product'), categorySelect=document.getElementById('production-category');
function syncProductionCategory(){const o=productSelect.options[productSelect.selectedIndex];const id=o?.dataset.category||'';if(id){categorySelect.value=id;}}
productSelect?.addEventListener('change',syncProductionCategory); syncProductionCategory();
</script>
<?= $this->include('layouts/footer') ?>
