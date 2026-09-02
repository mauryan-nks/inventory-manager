<?= $this->include('layouts/header') ?>
<?php
$variantRows = $variants ?? [];
if (!$variantRows) $variantRows = [[]];
function variant_attrs_for_form(array $v): array {
    $raw = (string)($v['attributes_json'] ?? '');
    $decoded = $raw !== '' ? json_decode($raw, true) : [];
    if (is_array($decoded) && $decoded) return $decoded;
    if (($v['size_value'] ?? null) !== null && ($v['size_unit'] ?? null)) {
        return ['size' => ['value' => (float)$v['size_value'], 'unit' => strtoupper((string)$v['size_unit'])]];
    }
    return [];
}
?>
<div class="page-head">
  <div>
    <div class="page-kicker">Catalog</div>
    <h1 class="page-title"><?=esc($title)?></h1>
    <p class="page-subtitle">Build flexible variants from size, colour, grade, material, model, capacity, or any attributes you need.</p>
  </div>
  <div class="head-actions"><a class="btn" href="<?=site_url('products')?>">← Back to products</a></div>
</div>

<div class="card form-card">
<form method="post" action="<?= $product ? site_url('products/'.$product['id'].'/update') : site_url('products/store') ?>" id="product-form">
<?=csrf_field()?>
<input type="hidden" name="measurement_type" value="STANDARD">
<input type="hidden" name="variant_schema_json" id="variant-schema-json" value="<?=esc(old('variant_schema_json',$product['variant_schema_json']??''))?>">

<div class="form-grid">
  <div class="field"><label>Product code</label><input name="code" value="<?=esc(old('code',$product['code']??''))?>" required></div>
  <div class="field"><label>Product name</label><input name="name" value="<?=esc(old('name',$product['name']??''))?>" required></div>
  <div class="field"><label>Category</label><select name="category_id"><option value="">No category</option><?php foreach($categories as $c): ?><option value="<?=$c['id']?>" <?=((string)old('category_id',$product['category_id']??'')===(string)$c['id'])?'selected':''?>><?=esc($c['name'])?></option><?php endforeach; ?></select></div>
  <div class="field"><label>Quantity unit</label><input name="unit" value="<?=esc(old('unit',$product['unit']??'pcs'))?>" placeholder="pcs / bag / roll / kg" required><div class="hint">Quantity is the stock count. Size, colour and other attributes only identify the variant.</div></div>
  <div class="field full"><label>Description</label><textarea name="description" placeholder="Optional product notes…"><?=esc(old('description',$product['description']??''))?></textarea></div>
</div>

<div class="section-head" style="margin-top:26px">
  <div><h2 class="section-title">Product variants</h2><div class="section-meta">No JSON needed. Enter normal values below; the application builds the JSON automatically in the background.</div></div>
  <button class="btn small" type="button" id="add-variant">+ Add variant</button>
</div>

<div class="alert info variant-help-banner">
  <strong>Example:</strong> A Steel Rod can have <b>9 MM · 400 pcs</b> and <b>12 MM · 600 pcs</b>. Add <b>size</b> as the attribute, choose <b>MM</b> as its unit, and enter the value. Quantity stays separate.
</div>

<div id="variants-body" class="variant-editor">
<?php foreach($variantRows as $i=>$v): $v=$v?:[]; $attrs=variant_attrs_for_form($v); ?>
<div class="variant-card" data-variant-row>
  <div class="variant-card-head">
    <div><span class="variant-number">Variant <span data-variant-number><?=($i+1)?></span></span><div class="muted">Define this exact combination and its opening stock.</div></div>
    <button class="btn icon remove-variant" type="button" title="Remove variant">×</button>
  </div>
  <input name="variant_id[]" type="hidden" value="<?=esc($v['id']??'')?>">
  <input name="variant_attributes[]" type="hidden" class="variant-json" value="<?=esc(json_encode($attrs,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>">
  <div class="variant-main-grid">
    <div class="field"><label>Variant name <span class="muted">optional label</span></label><input name="variant_name[]" value="<?=esc(old('variant_name.'.$i,$v['variant_name']??''))?>" placeholder="e.g. 9 MM / Red XL" required></div>
    <div class="field"><label>Opening quantity</label><input name="variant_opening[]" type="number" step="1" min="0" value="<?=esc(old('variant_opening.'.$i,$v['opening_quantity']??'0'))?>" required></div>
    <div class="field"><label>Minimum quantity</label><input name="variant_minimum[]" type="number" step="1" min="0" value="<?=esc(old('variant_minimum.'.$i,$v['minimum_quantity']??'0'))?>"></div>
  </div>

  <div class="attributes-box">
    <div class="attributes-head"><div><strong>Variant attributes</strong><div class="muted">Add as many attributes as needed. Examples: size, color, grade, material.</div></div><button class="btn small" type="button" data-add-attribute>+ Add attribute</button></div>
    <div class="attribute-rows" data-attribute-rows></div>
    <div class="attribute-preview"><span class="muted">Variant:</span> <strong data-attribute-preview>Default variant</strong></div>
  </div>
</div>
<?php endforeach; ?>
</div>

<div class="variant-summary">
  <div><span class="muted">Total opening quantity</span><strong id="variant-total">0</strong> <?=esc($product['unit']??'units')?></div>
  <div><span class="muted">Variants</span><strong id="variant-count">0</strong></div>
  <div><span class="muted">Attributes</span><strong id="attribute-count">0</strong></div>
</div>

<div class="form-actions"><a class="btn" href="<?=site_url('products')?>">Cancel</a><button class="btn primary" type="submit"><?= $product?'Update product':'Create product' ?></button></div>
</form>
</div>

<style>
.variant-editor{display:grid;gap:14px}.variant-card{border:1px solid var(--line);border-radius:16px;padding:18px;background:var(--surface,#fff)}
.variant-card-head,.attributes-head{display:flex;align-items:center;justify-content:space-between;gap:15px}.variant-number{font-weight:800;font-size:14px}.variant-card-head{margin-bottom:16px}.variant-main-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px}.attributes-box{margin-top:16px;padding:15px;border:1px solid var(--line);border-radius:13px;background:rgba(100,100,120,.035)}
.attribute-rows{display:grid;gap:9px;margin-top:12px}.attribute-row{display:grid;grid-template-columns:minmax(130px,1fr) minmax(150px,1.5fr) 130px 40px;gap:8px;align-items:end}.attribute-row .field{margin:0}.attribute-preview{margin-top:12px;padding:10px 12px;border-radius:10px;background:var(--surface-2,#f7f7fb);font-size:13px}.variant-help-banner{margin:18px 0}.json-status{font-size:11px;margin-top:4px}.variant-summary{display:flex;gap:30px;flex-wrap:wrap;margin-top:16px;padding:15px 18px;border:1px solid var(--line);border-radius:12px}.variant-summary div{display:flex;gap:7px;align-items:baseline}.variant-summary strong{font-size:17px}.btn.icon{min-width:36px}.attribute-empty{padding:12px;border:1px dashed var(--line);border-radius:10px;color:var(--muted,#777);font-size:13px}
@media(max-width:800px){.variant-main-grid,.attribute-row{grid-template-columns:1fr}.attribute-row .remove-attribute{justify-self:end}.variant-card{padding:13px}}
</style>

<script>
(() => {
  const body = document.getElementById('variants-body');
  const schemaInput = document.getElementById('variant-schema-json');
  const unit = () => document.querySelector('[name="unit"]')?.value || 'units';

  function escapeHtml(v){return String(v ?? '').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
  function labelize(k){return String(k).replace(/[_-]+/g,' ').replace(/\b\w/g,c=>c.toUpperCase());}

  function attrRowHtml(key='', value='', valueUnit='') {
    return `<div class="attribute-row" data-attribute-row>
      <div class="field"><label>Attribute</label><input data-attr-key value="${escapeHtml(key)}" placeholder="size / color / grade"></div>
      <div class="field"><label>Value</label><input data-attr-value value="${escapeHtml(value)}" placeholder="9 / Red / A"></div>
      <div class="field"><label>Unit <span class="muted">optional</span></label><input data-attr-unit value="${escapeHtml(valueUnit)}" placeholder="mm / kg / L"></div>
      <button class="btn icon remove-attribute" type="button" title="Remove attribute">×</button>
    </div>`;
  }

  function normalizeValue(value) {
    const raw = String(value ?? '').trim();
    if (raw === '') return '';
    if (/^-?(?:\d+\.?\d*|\.\d+)$/.test(raw)) return Number(raw);
    return raw;
  }

  function buildJson(row) {
    const out = {};
    row.querySelectorAll('[data-attribute-row]').forEach(ar => {
      const key = ar.querySelector('[data-attr-key]').value.trim();
      const value = ar.querySelector('[data-attr-value]').value.trim();
      const u = ar.querySelector('[data-attr-unit]').value.trim();
      if (!key || value === '') return;
      const val = normalizeValue(value);
      out[key] = u ? {value: val, unit: u} : val;
    });
    return out;
  }

  function prettyJson(obj) {
    const parts=[];
    Object.entries(obj).forEach(([k,v])=>{
      if(v && typeof v==='object' && Object.prototype.hasOwnProperty.call(v,'value')) parts.push(`${labelize(k)}: ${v.value}${v.unit?' '+v.unit:''}`);
      else parts.push(`${labelize(k)}: ${typeof v==='object'?JSON.stringify(v):v}`);
    });
    return parts.join(' · ') || 'Default variant';
  }

  function hydrateRow(row) {
    const hidden = row.querySelector('.variant-json');
    let obj={};
    try { obj=JSON.parse(hidden.value || '{}'); } catch(e) { obj={}; }
    const box=row.querySelector('[data-attribute-rows]');
    box.innerHTML='';
    Object.entries(obj).forEach(([key,val])=>{
      let value=val, u='';
      if(val && typeof val==='object' && Object.prototype.hasOwnProperty.call(val,'value')) { value=val.value; u=val.unit || ''; }
      box.insertAdjacentHTML('beforeend',attrRowHtml(key,value,u));
    });
    if(!box.children.length) box.insertAdjacentHTML('beforeend','<div class="attribute-empty">No attributes yet. Click <b>+ Add attribute</b> to define this variant.</div>');
    bindAttributeRows(row);
    updateRow(row);
  }

  function syncRow(row) {
    const obj=buildJson(row);
    row.querySelector('.variant-json').value=JSON.stringify(obj);
    row.querySelector('[data-attribute-preview]').textContent=prettyJson(obj);
    return obj;
  }

  function bindAttributeRows(row) {
    row.querySelectorAll('[data-attribute-row]').forEach(ar=>{
      ar.querySelectorAll('input').forEach(input=>input.addEventListener('input',()=>{syncRow(row);updateAll();}));
      ar.querySelector('.remove-attribute')?.addEventListener('click',()=>{ar.remove();if(!row.querySelector('[data-attribute-row]')) row.querySelector('[data-attribute-rows]').innerHTML='<div class="attribute-empty">No attributes yet. Click <b>+ Add attribute</b> to define this variant.</div>';syncRow(row);updateAll();});
    });
  }

  function updateRow(row){
    const obj=syncRow(row);
    const preview=row.querySelector('[data-attribute-preview]');
    preview.textContent=prettyJson(obj);
  }

  function renumber(){body.querySelectorAll('[data-variant-row]').forEach((row,i)=>row.querySelector('[data-variant-number]').textContent=i+1);}

  function updateAll(){
    let total=0,count=0,attrCount=0;
    body.querySelectorAll('[data-variant-row]').forEach(row=>{
      count++;
      total += Number(row.querySelector('[name="variant_opening[]"]').value || 0);
      const obj=syncRow(row); attrCount += Object.keys(obj).length;
    });
    document.getElementById('variant-total').textContent=total.toFixed(3).replace(/\.000$/,'');
    document.getElementById('variant-count').textContent=count;
    document.getElementById('attribute-count').textContent=attrCount;
    renumber();
    const keys=new Set();
    body.querySelectorAll('[data-variant-row] .variant-json').forEach(h=>{try{Object.keys(JSON.parse(h.value||'{}')).forEach(k=>keys.add(k));}catch(e){}});
    schemaInput.value=JSON.stringify({attributes:[...keys]});
  }

  function bindVariant(row){
    row.querySelector('.remove-variant')?.addEventListener('click',()=>{if(body.children.length>1){row.remove();updateAll();}});
    row.querySelector('[data-add-attribute]')?.addEventListener('click',()=>{
      const box=row.querySelector('[data-attribute-rows]');
      box.querySelector('.attribute-empty')?.remove();
      box.insertAdjacentHTML('beforeend',attrRowHtml());
      bindAttributeRows(row); updateAll();
      const last=box.lastElementChild?.querySelector('[data-attr-key]'); last?.focus();
    });
    row.querySelectorAll('input').forEach(input=>input.addEventListener('input',updateAll));
    hydrateRow(row);
  }

  body.querySelectorAll('[data-variant-row]').forEach(bindVariant);

  document.getElementById('add-variant').addEventListener('click',()=>{
    const row=document.createElement('div'); row.className='variant-card'; row.setAttribute('data-variant-row','');
    row.innerHTML=`<div class="variant-card-head"><div><span class="variant-number">Variant <span data-variant-number>1</span></span><div class="muted">Define this exact combination and its opening stock.</div></div><button class="btn icon remove-variant" type="button" title="Remove variant">×</button></div>
      <input name="variant_id[]" type="hidden" value=""><input name="variant_attributes[]" type="hidden" class="variant-json" value="{}">
      <div class="variant-main-grid"><div class="field"><label>Variant name <span class="muted">optional label</span></label><input name="variant_name[]" placeholder="e.g. 9 MM / Red XL" required></div><div class="field"><label>Opening quantity</label><input name="variant_opening[]" type="number" step="1" min="0" value="0" required></div><div class="field"><label>Minimum quantity</label><input name="variant_minimum[]" type="number" step="1" min="0" value="0"></div></div>
      <div class="attributes-box"><div class="attributes-head"><div><strong>Variant attributes</strong><div class="muted">Add as many attributes as needed.</div></div><button class="btn small" type="button" data-add-attribute>+ Add attribute</button></div><div class="attribute-rows" data-attribute-rows></div><div class="attribute-preview"><span class="muted">Variant:</span> <strong data-attribute-preview>Default variant</strong></div></div>`;
    body.appendChild(row); bindVariant(row); updateAll();
  });

  document.getElementById('product-form').addEventListener('submit',()=>{updateAll();});
  updateAll();
})();
</script>
<?= $this->include('layouts/footer') ?>
