<?= $this->include('layouts/header') ?>
<div class="page-head"><div><div class="page-kicker">Gate desk</div><h1 class="page-title">Visitor Register</h1><p class="page-subtitle">Capture visitor name, photo, purpose and exact IST entry time. Owner meetings require owner approval before entry.</p></div><div class="head-actions"><a class="btn" href="<?=site_url('security')?>">← Security desk</a></div></div>
<div class="card form-card" style="margin-bottom:18px"><form method="post" action="<?=site_url('security/visitors/store')?>" enctype="multipart/form-data" id="visitor-form"><?=csrf_field()?>
<div class="form-grid three">
<div class="field"><label>Visitor type</label><select name="visitor_type" id="visitor-type"><option value="GENERAL">General / Work visit</option><option value="OWNER_MEETING">Meeting with Owner</option></select><div class="hint">General visitors are checked in directly. Owner meetings wait for approval.</div></div>
<div class="field"><label>Visitor name</label><input name="name" required maxlength="150" placeholder="Full name"></div>
<div class="field"><label>Purpose / Work</label><input name="purpose" required maxlength="255" placeholder="Maintenance, delivery, meeting, etc."></div>
<div class="field" id="owner-field" style="display:none"><label>Owner to meet</label><select name="owner_id"><option value="">Select owner / approver</option><?php foreach($owners as $o): ?><option value="<?=esc($o['id'])?>"><?=esc($o['name'])?><?= $o['role']==='admin'?' (Administrator)':'' ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Visitor photo</label><div style="display:flex;gap:8px;flex-wrap:wrap"><input type="file" name="photo" id="visitor-photo" accept="image/jpeg,image/png,image/webp" capture="user"><button class="btn small" type="button" id="open-camera">Open camera</button></div><div class="hint">Photo is stored with the visitor register. Camera works on supported HTTPS/mobile browsers.</div></div>
</div>
<div id="camera-box" style="display:none;margin-top:16px;max-width:420px"><video id="camera-video" autoplay playsinline muted style="width:100%;border-radius:12px;background:#111;aspect-ratio:4/3;object-fit:cover"></video><canvas id="camera-canvas" style="display:none"></canvas><div style="display:flex;gap:8px;margin-top:8px"><button class="btn primary" type="button" id="capture-photo">Capture photo</button><button class="btn" type="button" id="close-camera">Close camera</button></div></div>
<div id="photo-preview" style="display:none;margin-top:14px"><img id="photo-preview-img" alt="Visitor preview" style="width:90px;height:90px;border-radius:12px;object-fit:cover;border:1px solid #ddd"></div>
<div class="form-actions"><a class="btn" href="<?=site_url('security')?>">Cancel</a><button class="btn primary" type="submit">Register Visitor →</button></div>
</form></div>
<div class="card"><div class="section-head"><div><h2 class="section-title">Visitor history</h2><div class="section-meta">Latest entries first · times are IST (Asia/Kolkata).</div></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Entry time</th><th>Photo</th><th>Name</th><th>Purpose / Work</th><th>Meeting</th><th>Status</th><th>Approved</th></tr></thead><tbody><?php if(!$visitorRows): ?><tr><td colspan="7"><div class="empty"><strong>No visitor records</strong>Visitor entries will appear here.</div></td></tr><?php else: foreach($visitorRows as $v): ?><tr><td class="mono"><?=esc($v['entry_at'])?></td><td><?php if(!empty($v['photo_path'])): ?><img src="<?=site_url('security/visitors/'.$v['id'].'/photo')?>" alt="" style="width:42px;height:42px;border-radius:50%;object-fit:cover"><?php else: ?>—<?php endif; ?></td><td><strong><?=esc($v['name'])?></strong></td><td><?=esc($v['purpose'] ?: '—')?></td><td><?=esc($v['owner_name'] ?: 'General visit')?></td><td><span class="badge <?=$v['status']==='APPROVED'||$v['status']==='CHECKED_IN'?'success':($v['status']==='PENDING'?'warning':'danger')?>"><?=esc($v['status'])?></span><?php if(!empty($v['rejected_reason'])): ?><div class="muted" style="font-size:10px;margin-top:4px"><?=esc($v['rejected_reason'])?></div><?php endif; ?></td><td><?=esc($v['approved_at'] ?: '—')?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
<script>
(function(){
 const type=document.getElementById('visitor-type'), owner=document.getElementById('owner-field'), input=document.getElementById('visitor-photo');
 const box=document.getElementById('camera-box'), video=document.getElementById('camera-video'), canvas=document.getElementById('camera-canvas'), preview=document.getElementById('photo-preview'), previewImg=document.getElementById('photo-preview-img');
 let stream=null;
 function toggle(){owner.style.display=type.value==='OWNER_MEETING'?'block':'none'; owner.querySelector('select').required=type.value==='OWNER_MEETING';}
 type.addEventListener('change',toggle); toggle();
 input.addEventListener('change',function(){if(input.files&&input.files[0]){previewImg.src=URL.createObjectURL(input.files[0]);preview.style.display='block';}});
 document.getElementById('open-camera').addEventListener('click',async function(){
   if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){input.click();return;}
   try{stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false});video.srcObject=stream;box.style.display='block';}catch(e){input.click();}
 });
 document.getElementById('capture-photo').addEventListener('click',function(){
   if(!stream)return; canvas.width=video.videoWidth||640; canvas.height=video.videoHeight||480; canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);
   canvas.toBlob(function(blob){if(!blob)return;const file=new File([blob],'visitor-camera-'+Date.now()+'.jpg',{type:'image/jpeg'});const dt=new DataTransfer();dt.items.add(file);input.files=dt.files;previewImg.src=URL.createObjectURL(blob);preview.style.display='block';closeCamera();},'image/jpeg',0.88);
 });
 function closeCamera(){if(stream){stream.getTracks().forEach(t=>t.stop());stream=null;}video.srcObject=null;box.style.display='none';}
 document.getElementById('close-camera').addEventListener('click',closeCamera);
 window.addEventListener('beforeunload',closeCamera);
})();
</script>
<?= $this->include('layouts/footer') ?>
