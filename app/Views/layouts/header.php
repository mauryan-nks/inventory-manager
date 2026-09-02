<?php
$authNav = new \App\Services\AuthService();
$currentPath = trim(uri_string(), '/');
$userName = (string) service('session')->get('user_name');
$role = (string) service('session')->get('role');
$settingRows = (new \App\Models\SettingModel())->findAll();
$settingsMap = array_column($settingRows, 'setting_value', 'setting_key');
$appName = trim((string)($settingsMap['app_name'] ?? 'Inventory Manager')) ?: 'Inventory Manager';
$companyLogo = trim((string)($settingsMap['company_logo'] ?? ''));
$logoSrc = '';
if ($companyLogo !== '') {
    $logoSrc = preg_match('~^https?://~i', $companyLogo) ? $companyLogo : base_url(ltrim($companyLogo, '/'));
}
$initial = strtoupper(substr(trim($userName ?: 'U'), 0, 1));
$active = function(array $paths) use ($currentPath): string {
    foreach ($paths as $path) {
        if ($path === '' && $currentPath === '') return 'active';
        if ($path !== '' && ($currentPath === $path || str_starts_with($currentPath, trim($path, '/').'/'))) return 'active';
    }
    return '';
};
$canInventory = $authNav->can('inventory.view') || $authNav->can('inventory.in') || $authNav->can('inventory.out');
$canProducts = $authNav->can('products.view') || $authNav->can('products.create');
$canSecurity = $authNav->can('security.scan') || $authNav->can('security.manual_entry') || $authNav->can('security.history');
$canReports = $authNav->can('reports.stock') || $authNav->can('reports.in') || $authNav->can('reports.out') || $authNav->can('reports.security') || $authNav->can('reports.compare');
?>
<!doctype html>
<html lang="<?=esc(app_locale() === 'hi' ? 'hi' : 'en')?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=esc(app_t($title ?? $appName))?> · <?=esc(app_t($appName))?></title>
<link rel="stylesheet" href="<?=base_url('assets/css/app.css?v='.rawurlencode((string)@filemtime(FCPATH.'assets/css/app.css')))?>">
</head>
<body data-locale="<?=esc(app_locale())?>">
<div class="app-shell">
<button class="mobile-menu-fab" type="button" data-menu aria-label="Open navigation" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
<aside class="sidebar">
  <div class="brand">
    <?php if($logoSrc): ?><img class="brand-logo" width="55px" src="<?=esc($logoSrc)?>" alt="Company logo"><?php else: ?><div class="brand-mark"><?=esc(strtoupper(substr($appName,0,2)))?></div><?php endif; ?>
    <div><strong><?=esc($appName)?></strong><span>Stock & movement control</span></div>
  </div>
  <nav class="nav">
    <a class="<?=$active(['dashboard'])?>" href="<?=site_url('dashboard')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg><?=esc(app_t('Dashboard'))?></a>
    <?php if($canInventory): ?>
    <div class="nav-label"><?=esc(app_t('Inventory'))?></div>
    <?php if($authNav->can('inventory.view')): ?><a class="<?=$active(['inventory'])?>" href="<?=site_url('inventory')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/><path d="M7 4v16"/></svg><?=esc(app_t('Current Stock'))?></a><?php endif; ?>
    <?php if($authNav->can('inventory.in')): ?><a class="<?=$active(['inventory/in'])?>" href="<?=site_url('inventory/in')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 19V5M6 11l6-6 6 6"/></svg><?=esc(app_t('Product IN'))?></a><?php endif; ?>
    <?php if($authNav->can('inventory.out')): ?><a class="<?=$active(['inventory/out'])?>" href="<?=site_url('inventory/out')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M6 13l6 6 6-6"/></svg><?=esc(app_t('Product OUT'))?></a><?php endif; ?>
    <?php if($authNav->can('inventory.view')): ?><a class="<?=$active(['inventory/transactions'])?>" href="<?=site_url('inventory/transactions')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg><?=esc(app_t('Transactions'))?></a><?php endif; ?>
    <?php endif; ?>
    <?php if($canProducts): ?>
    <div class="nav-label"><?=esc(app_t('Catalog'))?></div>
    <a class="<?=$active(['products'])?>" href="<?=site_url('products')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h6M8 16h5"/></svg><?=esc(app_t('Products'))?></a>
    <?php endif; ?>
    <?php if($canSecurity): ?>
    <div class="nav-label"><?=esc(app_t('Gate desk'))?></div>
    <a class="<?=$active(['security'])?>" href="<?=site_url('security')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 11a7 7 0 0 1 14 0v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z"/><path d="M8 11V9a4 4 0 0 1 8 0v2"/></svg><?=esc(app_t('Security Desk'))?></a>
    <?php endif; ?>
    <?php if($canReports): ?>
    <div class="nav-label"><?=esc(app_t('Insights'))?></div>
    <a class="<?=$active(['reports'])?>" href="<?=site_url('reports')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5M4 19h16"/><path d="M8 16v-5M12 16V8M16 16v-3M20 16V6"/></svg><?=esc(app_t('Reports'))?></a>
    <?php if($authNav->can('reports.compare')): ?><a class="<?=$active(['reports/compare'])?>" href="<?=site_url('reports/compare')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 18V6M12 18V3M19 18v-8"/><path d="M3 20h18"/></svg><?=esc(app_t('Compare'))?></a><?php endif; ?>
    <?php endif; ?>
    <?php if($authNav->can('users.view')): ?><a class="<?=$active(['users'])?>" href="<?=site_url('users')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M16 11a3 3 0 1 0 0-6M18 14a4 4 0 0 1 3 4"/></svg><?=esc(app_t('Users & Access'))?></a><?php endif; ?>
    <?php if($authNav->can('audit.view')): ?><a class="<?=$active(['audit'])?>" href="<?=site_url('audit')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg><?=esc(app_t('Audit Log'))?></a><?php endif; ?>
    <?php if($authNav->can('settings.view')): ?><a class="<?=$active(['settings'])?>" href="<?=site_url('settings')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/><path d="M4 12h2m12 0h2M12 4v2m0 12v2m-5.7-2.3 1.4-1.4m8.6 0 1.4 1.4M6.3 6.3l1.4 1.4m8.6 0 1.4-1.4"/></svg><?=esc(app_t('Settings'))?></a><?php endif; ?>
  </nav>
  <div class="sidebar-bottom"><div class="user-chip"><div class="avatar"><?=esc($initial)?></div><div style="min-width:0"><strong><?=esc($userName ?: 'User')?></strong><span><?=esc(ucfirst($role ?: 'member'))?></span><div><a href="<?=site_url('logout')?>"><?=esc(app_t('Sign out'))?></a></div></div></div></div>
</aside>
<div class="sidebar-overlay" data-menu-close aria-hidden="true"></div>
<div class="main">
<header class="topbar"><div class="crumb"><strong><?=esc(app_t($title ?? 'Dashboard'))?></strong><span> · Inventory control</span></div><div class="top-actions"><form class="language-picker" method="post" action="<?=site_url('language')?>" data-language-form>
<?=csrf_field()?><input type="hidden" name="redirect" value="/<?=esc(trim(uri_string(), '/'))?>">
<label for="language-select" class="sr-only">Language</label><select id="language-select" name="_language" data-language-select aria-label="Language">
<option value="en" <?=app_locale()==='en'?'selected':''?>>English</option><option value="hi" <?=app_locale()==='hi'?'selected':''?>>हिंदी</option><option value="hinglish" <?=app_locale()==='hinglish'?'selected':''?>>Hinglish</option></select></form><div class="top-user"><div class="avatar"><?=esc($initial)?></div><div><strong style="font-size:12px;display:block"><?=esc($userName ?: 'User')?></strong><span class="muted" style="font-size:10px"><?=esc(ucfirst($role ?: 'member'))?></span></div></div></div></header>
<script>window.APP_I18N = <?=json_encode(app_translations(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;</script>
<main class="content">
<?php if(session()->getFlashdata('success')): ?><div class="alert success" data-autohide><?=esc(session()->getFlashdata('success'))?></div><?php endif; ?>
<?php if(session()->getFlashdata('error')): ?><div class="alert error"><?=esc(session()->getFlashdata('error'))?></div><?php endif; ?>
