<?= $this->include('layouts/header') ?>
<div class="page-head"><div><div class="page-kicker">Incoming document</div><h1 class="page-title">Scan / upload paper</h1><p class="page-subtitle">Capture the original document, then review OCR suggestions before anything changes stock.</p></div><div class="head-actions"><a class="btn" href="<?=site_url('security')?>">← Security desk</a></div></div>
<div class="card form-card"><div class="stepper"><div class="step active"><i>1</i> Capture</div><span class="arrow">→</span><div class="step"><i>2</i> Read</div><span class="arrow">→</span><div class="step"><i>3</i> Verify</div><span class="arrow">→</span><div class="step"><i>4</i> Post IN</div></div><div class="scan-drop"><div class="scan-icon">⌁</div><h2 class="section-title" style="font-size:17px">Upload a clear paper, invoice or challan</h2><p class="section-meta">PDF, JPG, PNG or WEBP · up to 10 MB. OCR is optional and never posts stock automatically.</p><form method="post" enctype="multipart/form-data" action="<?=site_url('security/upload')?>" style="margin-top:18px" id="ocr-upload-form">
<?=csrf_field()?>
<input type="hidden" name="client_ocr_text" id="client-ocr-text">
<input type="hidden" name="client_ocr_language" id="client-ocr-language" value="<?=esc(app_locale()==='hi'?'hin':'eng')?>">
<div class="field"><label>Document</label><input type="file" name="document" id="ocr-document" accept="image/jpeg,image/png,image/webp,application/pdf" capture="environment" required><div class="section-meta" style="margin-top:7px">Images can be read directly in your browser, so OCR still works even when PHP-FPM cannot execute Tesseract.</div></div>
<div id="ocr-progress" class="alert info" style="display:none;margin-top:12px"><strong>Reading document…</strong><span id="ocr-progress-text">Preparing OCR.</span></div>
<div class="form-actions" style="border:0"><button class="btn primary" id="ocr-submit" type="submit">Upload & read document →</button></div>
</form></div><div class="alert <?=(!empty($ocrReady) ? 'success' : 'warning')?>" style="margin-top:18px"><strong>OCR engine:</strong> <?=!empty($ocrReady)?'Ready — Tesseract is available to PHP.':'Not available to PHP yet.'?> <?php if(empty($ocrReady)): ?>Install <code>tesseract-ocr</code> and make sure PHP-FPM can execute it. For scanned PDFs also install <code>poppler-utils</code>.<?php endif; ?></div>
<div class="alert info" style="margin-top:18px"><strong>Verification rule:</strong> extracted reference, supplier, vehicle and quantities are suggestions. A guard must review them before the IN transaction is created.</div></div>
<div class="alert info" style="margin-top:18px"><strong>OCR mode:</strong> <span id="ocr-mode-label"><?=app_locale()==='hi'?'Pure Hindi':'English / Hinglish (Roman text)'?></span>. If browser OCR cannot load, the server OCR attempt remains available.</div>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script>
(function(){
  const form=document.getElementById('ocr-upload-form');
  const input=document.getElementById('ocr-document');
  const hidden=document.getElementById('client-ocr-text');
  const langHidden=document.getElementById('client-ocr-language');
  const progress=document.getElementById('ocr-progress');
  const progressText=document.getElementById('ocr-progress-text');
  const submit=document.getElementById('ocr-submit');
  const locale=document.body?.dataset.locale||'en';
  const ocrLang=locale==='hi'?'hin':'eng';
  langHidden.value=ocrLang;
  let readyText='';
  let running=null;
  function setProgress(text){ if(!progress) return; progress.style.display='block'; progressText.textContent=text; }
  async function browserOcr(file){
    if(!window.Tesseract || !file || !file.type.startsWith('image/')) return '';
    setProgress('Preparing '+(ocrLang==='hin'?'Hindi':'English')+' OCR…');
    const worker=await Tesseract.createWorker(ocrLang, 1, {
      workerPath:'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/worker.min.js',
      corePath:'https://cdn.jsdelivr.net/npm/tesseract.js-core@5/tesseract-core.wasm.js',
      langPath:'https://tessdata.projectnaptha.com/4.0.0',
      logger:m=>{
        if(m.status) setProgress(m.status.replace(/^./,c=>c.toUpperCase())+(typeof m.progress==='number'?' '+Math.round(m.progress*100)+'%':''));
      }
    });
    try{
      const result=await worker.recognize(file);
      return (result?.data?.text||'').trim();
    } finally { await worker.terminate(); }
  }
  input?.addEventListener('change',()=>{ readyText=''; hidden.value=''; if(input.files?.[0]) setProgress('Document selected. OCR will run when you submit.'); });
  form?.addEventListener('submit',async e=>{
    const file=input?.files?.[0];
    if(!file || !file.type.startsWith('image/') || hidden.value.trim() || running) return;
    e.preventDefault();
    submit.disabled=true;
    submit.textContent='Reading OCR…';
    try{
      running=browserOcr(file);
      readyText=await running;
      running=null;
      if(readyText.length>=5){ hidden.value=readyText; setProgress('OCR complete. Uploading original document…'); }
      else setProgress('No readable text found in browser OCR. Uploading so server OCR can try.');
      form.submit();
    }catch(err){
      running=null;
      setProgress('Browser OCR could not start. Uploading so server OCR can try.');
      form.submit();
    }
  });
})();
</script>
<?= $this->include('layouts/footer') ?>