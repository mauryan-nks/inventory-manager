<?php
$company = $company ?? [];
$logo = trim((string)($company['company_logo'] ?? ''));
$logoSrc = $logo !== '' ? (preg_match('~^https?://~i', $logo) ? $logo : base_url(ltrim($logo, '/'))) : '';
$companyName = trim((string)($company['company_name'] ?? ($company['app_name'] ?? 'Inventory Manager'))) ?: 'Inventory Manager';
$companyAddress = trim((string)($company['company_address'] ?? ''));
$companyPhone = trim((string)($company['company_phone'] ?? ''));
$companyEmail = trim((string)($company['company_email'] ?? ''));
$companyTax = trim((string)($company['company_tax'] ?? ''));
$items = $items ?? [];

function challanAttrLabel($json): string {
    $a = json_decode((string)$json, true);
    if (!is_array($a)) return '';
    $parts = [];
    foreach ($a as $k => $v) {
        $label = ucwords(str_replace(['_', '-'], ' ', (string)$k));
        if (is_array($v) && array_key_exists('value', $v)) {
            $parts[] = $label . ': ' . $v['value'] . (!empty($v['unit']) ? ' ' . $v['unit'] : '');
        } elseif (is_scalar($v)) {
            $parts[] = $label . ': ' . $v;
        }
    }
    return implode(' · ', $parts);
}

function challanQty($value): string {
    return rtrim(rtrim(number_format((float)$value, 3, '.', ''), '0'), '.');
}

function challanCopy(string $label, array $transaction, array $items, string $logoSrc, string $companyName, string $companyAddress, string $companyPhone, string $companyEmail, string $companyTax): void { ?>
<section class="challan-copy">
  <div class="challan-header">
    <div class="challan-brand">
      <?php if($logoSrc): ?><img src="<?=esc($logoSrc)?>" alt="Company logo"><?php else: ?><div class="logo-fallback"><?=esc(strtoupper(substr($companyName, 0, 2)))?></div><?php endif; ?>
      <div class="company-details">
        <div class="challan-company"><?=esc($companyName)?></div>
        <?php if($companyAddress): ?><div><?=nl2br(esc($companyAddress))?></div><?php endif; ?>
        <?php if($companyPhone || $companyEmail): ?><div><?=esc(trim($companyPhone . ($companyPhone && $companyEmail ? ' · ' : '') . $companyEmail))?></div><?php endif; ?>
        <?php if($companyTax): ?><div>GST / Tax: <?=esc($companyTax)?></div><?php endif; ?>
      </div>
    </div>
    <div class="copy-label"><?=esc($label)?></div>
  </div>

  <div class="challan-title">DELIVERY CHALLAN</div>

  <div class="challan-meta">
    <div><b>Challan No.</b><span><?=esc($transaction['transaction_no'])?></span></div>
    <div><b>Date & Time</b><span><?=esc($transaction['created_at'])?></span></div>
    <div><b>Reference No.</b><span><?=esc($transaction['reference_no'] ?: '—')?></span></div>
    <div><b>Vehicle No.</b><span><?=esc($transaction['vehicle_no'] ?: '—')?></span></div>
  </div>
  <div class="party"><b>Issued To / Destination</b><span><?=esc($transaction['party_name'] ?: '—')?></span></div>

  <table class="challan-table">
    <thead><tr><th>#</th><th>Product</th><th>Variant / Size</th><th>Quantity</th><th>Unit</th></tr></thead>
    <tbody>
    <?php foreach($items as $i => $item): ?>
      <tr>
        <td><?=($i + 1)?></td>
        <td><strong><?=esc($item['name'])?></strong><small><?=esc($item['code'])?></small></td>
        <td><?=esc($item['variant_name'] ?: challanAttrLabel($item['variant_attributes_json'] ?? '') ?: 'Default')?></td>
        <td class="qty"><?=esc(challanQty($item['quantity']))?></td>
        <td><?=esc($item['unit'])?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="challan-bottom">
    <div class="remarks"><b>Purpose / Remarks</b><div><?=nl2br(esc($transaction['remarks'] ?: '—'))?></div></div>
    <div class="signatures">
      <div><span class="sig-title">Issued By</span><i></i><small><?=esc($transaction['user_name'] ?? '')?></small></div>
      <div><span class="sig-title">Received By</span><i></i><small>Signature / Name</small></div>
    </div>
  </div>
</section>
<?php }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Challan <?=esc($transaction['transaction_no'])?></title>
<style>
*{box-sizing:border-box}
@page{size:A4 portrait;margin:0}
html,body{margin:0;padding:0;background:#e9edf3;color:#18202a;font-family:Arial,Helvetica,sans-serif}
body{font-size:8.5px;line-height:1.25}
.screen-bar{height:58px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 18px;background:#fff;border-bottom:1px solid #d9dee7;position:sticky;top:0;z-index:5}
.screen-title{font-weight:800;font-size:14px}.screen-sub{color:#7a8493;font-size:11px;margin-top:2px}
.screen-actions{display:flex;gap:8px}.screen-actions button,.screen-actions a{border:1px solid #d2d7df;background:#fff;color:#18202a;padding:9px 14px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700;cursor:pointer}.screen-actions .primary{background:#635bdb;color:#fff;border-color:#635bdb}
.sheet{width:210mm;height:297mm;margin:18px auto;background:#fff;padding:5mm;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 8px 35px rgba(15,23,42,.14)}
.challan-copy{height:138mm;flex:none;padding:4mm 4.5mm;border:1px solid #bfc6d0;overflow:hidden}
.challan-copy:first-child{border-bottom:0}
.challan-copy:last-child{border-top:0}
.challan-header{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;border-bottom:1.5px solid #18202a;padding-bottom:5px}
.challan-brand{display:flex;gap:8px;align-items:center;min-width:0}.challan-brand img,.logo-fallback{width:38px;height:38px;object-fit:contain;flex:none}.challan-brand img{border-radius:6px}.logo-fallback{display:grid;place-items:center;background:#eceafa;color:#635bdb;border-radius:8px;font-weight:800;font-size:13px}
.company-details{min-width:0;font-size:7px;color:#5f6875;line-height:1.2}.challan-company{font-size:14px;color:#18202a;font-weight:800;line-height:1.05;margin-bottom:2px}.copy-label{font-size:9px;font-weight:800;border:1px solid #18202a;padding:4px 7px;white-space:nowrap}
.challan-title{text-align:center;font-size:13px;font-weight:800;letter-spacing:1.5px;margin:5px 0}
.challan-meta{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #c8ced6}.challan-meta div{padding:4px 5px;border-right:1px solid #c8ced6;min-width:0}.challan-meta div:last-child{border-right:0}.challan-meta b,.party b{display:block;font-size:6.5px;text-transform:uppercase;color:#697382;margin-bottom:1px}.challan-meta span{font-weight:700;font-size:7.5px;overflow-wrap:anywhere}
.party{margin:4px 0;padding:4px 5px;border:1px solid #c8ced6;display:flex;gap:7px;align-items:baseline}.party b{margin:0;white-space:nowrap}.party span{font-size:8px;font-weight:700}
.challan-table{width:100%;border-collapse:collapse;margin-top:4px;table-layout:fixed}.challan-table th{background:#f0f2f5;text-align:left;font-size:6.5px;text-transform:uppercase;letter-spacing:.3px}.challan-table th,.challan-table td{border:1px solid #c8ced6;padding:3.5px 4px;vertical-align:middle}.challan-table th:nth-child(1){width:24px}.challan-table th:nth-child(2){width:28%}.challan-table th:nth-child(3){width:35%}.challan-table th:nth-child(4){width:16%}.challan-table th:nth-child(5){width:9%}.challan-table td:first-child{text-align:center}.challan-table .qty{font-weight:800;text-align:right}.challan-table small{display:block;color:#697382;font-size:6.5px;margin-top:1px}
.challan-bottom{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:5px}.remarks{border:1px solid #c8ced6;padding:4px;min-height:30px;font-size:7px}.remarks b{font-size:7px}.remarks div{margin-top:2px}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:10px;text-align:center;color:#697382;font-size:6.5px}.signatures i{display:block;border-bottom:1px solid #555;margin:16px 0 2px}.signatures .sig-title{font-size:7px}.signatures small{font-size:6.5px}
.cut-line{height:11mm;flex:none;display:flex;align-items:center;justify-content:center;position:relative;color:#667080;font-size:7px;letter-spacing:1.8px}.cut-line:before{content:'';position:absolute;left:0;right:0;border-top:1px dashed #7a8493}.cut-line span{position:relative;background:#fff;padding:0 10px;font-weight:700}
@media(max-width:900px){.sheet{transform-origin:top center;transform:scale(.9);margin-bottom:-30px}.screen-bar{position:static}}
@media print{
  html,body{background:#fff;width:210mm;height:297mm}
  .screen-bar{display:none!important}
  .sheet{width:210mm;height:297mm;margin:0;padding:5mm;box-shadow:none;overflow:hidden}
  .challan-copy{height:138mm}
  .cut-line{height:11mm}
}
</style>
</head>
<body>
<div class="screen-bar">
  <div><div class="screen-title">Delivery Challan</div><div class="screen-sub"><?=esc($transaction['transaction_no'])?> · Original + Customer Copy · A4</div></div>
  <div class="screen-actions"><button class="primary" type="button" onclick="window.print()">🖨 Print Challan</button><a href="<?=site_url('inventory/transactions/'.(int)$transaction['id'])?>">← Back</a></div>
</div>
<div class="sheet">
  <?php challanCopy('ORIGINAL',$transaction,$items,$logoSrc,$companyName,$companyAddress,$companyPhone,$companyEmail,$companyTax); ?>
  <div class="cut-line"><span>✂ CUT / TEAR HERE</span></div>
  <?php challanCopy('CUSTOMER COPY',$transaction,$items,$logoSrc,$companyName,$companyAddress,$companyPhone,$companyEmail,$companyTax); ?>
</div>
</body>
</html>
