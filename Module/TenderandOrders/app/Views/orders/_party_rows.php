<?php if(!$parties): ?>
<tr><td colspan="8"><div class="empty"><strong>No parties found</strong>Try another filter or add a new party.</div></td></tr>
<?php else: foreach($parties as $p): ?>
<tr>
  <td><span class="badge <?=esc(strtolower($p['party_type']))?>"><?=esc($p['party_type'])?></span></td>
  <td><strong><?=esc($p['name'])?></strong><div class="muted mono"><?=esc($p['code'] ?? 'No code')?></div></td><td><?=esc($p['gstin'] ?? '—')?></td>
  <td><?=esc($p['contact_person'] ?? '—')?></td>
  <td><?=esc($p['phone'] ?? '—')?></td>
  <td><?=esc($p['state'] ?? '—')?><?=!empty($p['pincode'])?' · '.esc($p['pincode']):''?></td>
  <td><strong><?=number_format((int)$p['order_count'])?></strong></td>
  <td class="table-actions"><a class="btn small primary" href="<?=site_url('orders/party/'.$p['id'])?>">Open Orders →</a><a class="btn small" href="<?=site_url('orders/party/'.$p['id'].'/edit')?>">Edit</a></td>
</tr>
<?php endforeach; endif; ?>
