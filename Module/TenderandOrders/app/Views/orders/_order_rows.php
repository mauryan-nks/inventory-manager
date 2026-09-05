<?php if(!$orders): ?><tr><td colspan="9"><div class="empty"><strong>No orders found</strong>Create an order or change the filter.</div></td></tr><?php else: foreach($orders as $o): ?>
<tr>
<td><strong class="mono"><?=esc($o['order_no'])?></strong><div class="muted"><?=esc($o['order_date'])?></div></td>
<td><span class="badge <?=strtolower($o['status'])?>"><?=esc($o['status'])?></span></td>
<td><?=esc($o['delivery_start_date'] ?: '—')?></td><td><?=esc($o['delivery_end_date'] ?: '—')?></td>
<td><strong><?=number_format($o['ordered_qty'],3)?></strong></td><td><?=number_format($o['delivered_qty'],3)?></td><td><strong><?=number_format($o['remaining_qty'],3)?></strong></td>
<td class="table-actions"><a class="btn small primary" href="<?=site_url('orders/'.$o['id'])?>">View</a><a class="btn small" href="<?=site_url('orders/'.$o['id'].'/edit')?>">Edit</a><a class="btn small" href="<?=site_url('orders/'.$o['id'].'/files')?>">Files</a><button class="btn small danger" type="button" data-delete-order="<?=$o['id']?>" data-order-no="<?=esc($o['order_no'])?>">Delete</button></td>
</tr>
<?php endforeach; endif; ?>
