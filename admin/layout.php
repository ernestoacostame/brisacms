<?php
// BrisaCMS - Admin Layout
function admin_header(string $page_title = '', string $active = ''): void {
    $color = theme_color();
    $title = site_title();
    $user  = $_SESSION['username'] ?? 'Admin';
    $c     = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#6366f1';
    $rgb   = hexdec(substr($c,1,2)).','.hexdec(substr($c,3,2)).','.hexdec(substr($c,5,2));

    // Admin theme colors (stored in config)
    $config = cms_config();
    $admin_scheme = $config['admin_scheme'] ?? 'dark';

    $schemes = [
        'dark'     => ['--bg:#0c0c10','--sidebar:#111117','--surface:#17171e','--surface2:#1e1e27','--border:#252530','--border2:#2e2e3d','--text:#e2e2ed','--text2:#9090a8','--muted:#5a5a72'],
        'midnight' => ['--bg:#050a14','--sidebar:#091220','--surface:#0d1a2e','--surface2:#112038','--border:#1a2d45','--border2:#1e3555','--text:#d0e4f7','--text2:#7a99bf','--muted:#3d5a7a'],
        'slate'    => ['--bg:#0f111a','--sidebar:#13151f','--surface:#191c28','--surface2:#1e2130','--border:#272b3d','--border2:#2e3347','--text:#e0e4f0','--text2:#8890b0','--muted:#525a7a'],
        'warm'     => ['--bg:#100e0c','--sidebar:#171310','--surface:#1e1a16','--surface2:#25201a','--border:#302820','--border2:#3a3028','--text:#ede8e0','--text2:#a09080','--muted:#6a5a50'],
        'light'    => ['--bg:#f0f0ec','--sidebar:#ffffff','--surface:#ffffff','--surface2:#f4f4f0','--border:#e0e0da','--border2:#cacac4','--text:#1a1a1a','--text2:#444444','--muted:#888888'],
    ];
    $scheme_vars = implode(';', $schemes[$admin_scheme] ?? $schemes['dark']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ? htmlspecialchars($page_title) . ' — ' : '' ?>Admin · <?= htmlspecialchars($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --accent: <?= $color ?>;
  --accent-rgb: <?= $rgb ?>;
  <?= $scheme_vars ?>;
  --green: #34d399; --red: #f87171; --yellow: #fbbf24;
  --radius: 10px;
  --sidebar-w: 240px;
  --sidebar-w-collapsed: 58px;
}
html, body { height: 100%; }
body { background: var(--bg); color: var(--text); font-family: 'Outfit', sans-serif; font-size: 14px; line-height: 1.5; display: flex; }

/* ── SIDEBAR ── */
.sidebar {
  width: var(--sidebar-w); min-height: 100vh; background: var(--sidebar);
  border-right: 1px solid var(--border); display: flex; flex-direction: column;
  position: fixed; top: 0; left: 0; bottom: 0; z-index: 200;
  transition: width 0.22s cubic-bezier(.4,0,.2,1), transform 0.22s;
  overflow: hidden;
}
body.sidebar-collapsed .sidebar { width: var(--sidebar-w-collapsed); }
body.sidebar-hidden .sidebar { transform: translateX(-100%); }

/* Logo */
.sidebar-logo {
  padding: 0 0.85rem; border-bottom: 1px solid var(--border);
  font-size: 1.2rem; font-weight: 700; letter-spacing: -0.03em; color: var(--text);
  display: flex; align-items: center; justify-content: space-between;
  height: 56px; flex-shrink: 0; white-space: nowrap; overflow: hidden;
}
.sidebar-logo span { color: var(--accent); }
.sidebar-logo small { display: block; font-size: 0.65rem; font-weight: 400; color: var(--muted); }
.logo-text { overflow: hidden; transition: opacity 0.15s, width 0.22s; }
body.sidebar-collapsed .logo-text { opacity: 0; width: 0; }

/* Toggle button */
.sidebar-toggle {
  background: none; border: none; color: var(--muted); cursor: pointer;
  padding: 0.4rem; border-radius: 6px; flex-shrink: 0;
  display: flex; align-items: center; transition: color 0.15s, background 0.15s;
}
.sidebar-toggle:hover { color: var(--text); background: var(--surface2); }

/* New article button in sidebar */
.sidebar-new-btn {
  margin: 0.75rem; flex-shrink: 0;
  display: flex; align-items: center; gap: 0.5rem;
  background: var(--accent); color: #fff; border-radius: 8px;
  padding: 0.6rem 0.85rem; text-decoration: none; font-size: 0.85rem; font-weight: 600;
  transition: opacity 0.15s, padding 0.22s; white-space: nowrap; overflow: hidden;
}
.sidebar-new-btn:hover { opacity: 0.88; }
.sidebar-new-btn .icon { flex-shrink: 0; }
.btn-new-label { overflow: hidden; transition: opacity 0.15s, width 0.22s; }
body.sidebar-collapsed .btn-new-label { opacity: 0; width: 0; }
body.sidebar-collapsed .sidebar-new-btn { padding: 0.6rem; justify-content: center; }

/* Nav */
.sidebar-nav { flex: 1; padding: 0.25rem 0.5rem; overflow-y: auto; overflow-x: hidden; }
.nav-section {
  font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;
  color: var(--muted); padding: 0.65rem 0.65rem 0.25rem;
  white-space: nowrap; overflow: hidden; transition: opacity 0.15s;
}
body.sidebar-collapsed .nav-section { opacity: 0; height: 0; padding: 0; margin: 0; }

.nav-item {
  display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0.65rem;
  border-radius: 7px; color: var(--text2); text-decoration: none; font-size: 0.85rem;
  transition: all 0.15s; margin-bottom: 1px; white-space: nowrap; overflow: hidden;
  position: relative;
}
.nav-item:hover { background: var(--surface2); color: var(--text); }
.nav-item.active { background: rgba(var(--accent-rgb), 0.15); color: var(--accent); font-weight: 500; }
.nav-item .icon { width: 16px; height: 16px; opacity: 0.8; flex-shrink: 0; }
.nav-label { overflow: hidden; transition: opacity 0.15s, width 0.22s; }
body.sidebar-collapsed .nav-label { opacity: 0; width: 0; }

/* Tooltip on collapsed */
body.sidebar-collapsed .nav-item:hover::after {
  content: attr(data-label);
  position: absolute; left: calc(var(--sidebar-w-collapsed) + 8px); top: 50%;
  transform: translateY(-50%);
  background: var(--surface2); color: var(--text); border: 1px solid var(--border2);
  padding: 0.3rem 0.65rem; border-radius: 6px; font-size: 0.8rem; white-space: nowrap;
  pointer-events: none; z-index: 999;
}

/* Footer */
.sidebar-footer {
  padding: 0.65rem 0.5rem; border-top: 1px solid var(--border); flex-shrink: 0;
  overflow: hidden;
}
.user-row {
  display: flex; align-items: center; gap: 0.6rem; padding: 0.4rem 0.5rem;
  border-radius: 7px; overflow: hidden;
}
.user-avatar {
  width: 28px; height: 28px; background: var(--accent); border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.75rem; font-weight: 700; color: #fff; flex-shrink: 0;
}
.user-name { font-size: 0.82rem; font-weight: 500; color: var(--text); overflow: hidden; transition: opacity 0.15s, width 0.22s; white-space: nowrap; }
body.sidebar-collapsed .user-name { opacity: 0; width: 0; }
.logout-btn {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.45rem 0.65rem; border-radius: 7px; background: rgba(248,113,113,0.1);
  color: var(--red); text-decoration: none; font-size: 0.8rem; font-weight: 500;
  transition: background 0.15s; white-space: nowrap; overflow: hidden;
}
.logout-btn:hover { background: rgba(248,113,113,0.2); }
.logout-label { overflow: hidden; transition: opacity 0.15s, width 0.22s; }
body.sidebar-collapsed .logout-label { opacity: 0; width: 0; }

/* ── TOPBAR ── */
.main { margin-left: var(--sidebar-w); flex: 1; min-height: 100vh; display: flex; flex-direction: column; transition: margin-left 0.22s; }
body.sidebar-collapsed .main { margin-left: var(--sidebar-w-collapsed); }
body.sidebar-hidden .main { margin-left: 0; }

.topbar {
  border-bottom: 1px solid var(--border); padding: 0 1.5rem;
  display: flex; align-items: center; justify-content: space-between;
  background: var(--sidebar); position: sticky; top: 0; z-index: 50;
  height: 56px; gap: 1rem;
}
.topbar-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.topbar-title { font-size: 0.95rem; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.topbar-actions { display: flex; align-items: center; gap: 0.65rem; flex-shrink: 0; }

/* Mobile toggle */
.mobile-menu-btn {
  display: none; background: none; border: none; color: var(--text2);
  cursor: pointer; padding: 0.4rem; border-radius: 6px;
}
.mobile-menu-btn:hover { color: var(--text); background: var(--surface2); }

/* Overlay for mobile */
.sidebar-overlay {
  display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
  z-index: 150; backdrop-filter: blur(2px);
}
body.sidebar-open .sidebar-overlay { display: block; }
body.sidebar-open .sidebar { transform: translateX(0) !important; }

/* Scheme switcher in topbar */
.scheme-dots { display: flex; gap: 4px; align-items: center; }
.scheme-dot {
  width: 16px; height: 16px; border-radius: 50%; cursor: pointer;
  border: 2px solid transparent; transition: border-color 0.15s, transform 0.15s;
  text-decoration: none; display: block;
}
.scheme-dot:hover { transform: scale(1.2); }
.scheme-dot.active { border-color: var(--text); }

/* ── MAIN CONTENT ── */
.page-body { padding: 1.5rem; flex: 1; }

/* ── COMPONENTS ── */
.btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 7px; font-family: inherit; font-size: 0.85rem; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { opacity: 0.88; }
.btn-secondary { background: var(--surface2); color: var(--text2); border: 1px solid var(--border2); }
.btn-secondary:hover { color: var(--text); background: var(--border); }
.btn-danger { background: rgba(248,113,113,0.1); color: var(--red); border: 1px solid rgba(248,113,113,0.2); }
.btn-danger:hover { background: rgba(248,113,113,0.2); }
.btn-sm { padding: 0.35rem 0.7rem; font-size: 0.78rem; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); }
.card-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
.card-title { font-size: 0.875rem; font-weight: 600; }
.card-body { padding: 1.25rem; }

.badge { display: inline-flex; align-items: center; padding: 0.2rem 0.55rem; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
.badge-green { background: rgba(52,211,153,0.1); color: var(--green); }
.badge-yellow { background: rgba(251,191,36,0.1); color: var(--yellow); }
.badge-red { background: rgba(248,113,113,0.1); color: var(--red); }
.badge-gray { background: var(--surface2); color: var(--text2); }

.table { width: 100%; border-collapse: collapse; }
.table th { text-align: left; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); padding: 0.65rem 1rem; border-bottom: 1px solid var(--border); white-space: nowrap; }
.table td { padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.875rem; vertical-align: middle; }
.table tr:last-child td { border-bottom: none; }
.table tr:hover td { background: var(--surface2); }
.table a { color: var(--text); text-decoration: none; }
.table a:hover { color: var(--accent); }

/* Responsive table */
@media (max-width: 640px) {
  .table thead { display: none; }
  .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
  .table tr { border: 1px solid var(--border); border-radius: 8px; margin-bottom: 0.75rem; padding: 0.5rem; }
  .table td { border: none; padding: 0.35rem 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
  .table td::before { content: attr(data-label); font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--muted); min-width: 80px; flex-shrink: 0; }
}

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.1rem; }
.stat-label { font-size: 0.72rem; font-weight: 500; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
.stat-value { font-size: 1.9rem; font-weight: 700; margin-top: 0.3rem; letter-spacing: -0.04em; }
.stat-value.accent { color: var(--accent); }

.alert { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1rem; }
.alert-success { background: rgba(52,211,153,0.1); border: 1px solid rgba(52,211,153,0.25); color: var(--green); }
.alert-error { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.25); color: var(--red); }

input[type="text"], input[type="password"], input[type="url"], input[type="email"], input[type="number"], select, textarea {
  background: var(--bg); border: 1px solid var(--border2); border-radius: 7px;
  padding: 0.6rem 0.85rem; color: var(--text); font-family: inherit; font-size: 0.875rem;
  transition: border-color 0.2s; width: 100%;
}
input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent); }
.form-label { display: block; font-size: 0.78rem; font-weight: 500; color: var(--text2); margin-bottom: 0.4rem; }
.form-group { margin-bottom: 1rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 640px) { .form-row { grid-template-columns: 1fr; } }

.pagination { display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap; }
.page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; height: 30px; padding: 0 4px; border-radius: 6px; font-size: 0.8rem; text-decoration: none; color: var(--text2); background: var(--surface); border: 1px solid var(--border); transition: all 0.15s; }
.page-btn:hover, .page-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* ── LIGHT SCHEME overrides ── */
body.scheme-light {
  --green: #059669; --red: #dc2626; --yellow: #d97706;
}
body.scheme-light .sidebar { border-right-color: var(--border); box-shadow: 2px 0 8px rgba(0,0,0,0.06); }
body.scheme-light .topbar  { box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
body.scheme-light input[type="text"],
body.scheme-light input[type="password"],
body.scheme-light input[type="url"],
body.scheme-light input[type="email"],
body.scheme-light input[type="number"],
body.scheme-light select,
body.scheme-light textarea {
  background: #fff; color: #1a1a1a; border-color: var(--border2);
}
body.scheme-light .card  { box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
body.scheme-light .table tr:hover td { background: #f8f8f4; }
body.scheme-light .alert-success { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
body.scheme-light .alert-error   { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
body.scheme-light .badge-green { background: #d1fae5; color: #065f46; }
body.scheme-light .badge-yellow { background: #fef3c7; color: #92400e; }
body.scheme-light .badge-gray  { background: #f3f4f6; color: #6b7280; }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); width: var(--sidebar-w) !important; }
  body.sidebar-open .sidebar { transform: translateX(0); }
  .main { margin-left: 0 !important; }
  .mobile-menu-btn { display: flex; }
  .page-body { padding: 1rem; }
  .topbar { padding: 0 1rem; }

  /* Table: card layout on mobile — NO phantom first column */
  .table { display: block; }
  .table thead { display: none; }
  .table tbody { display: block; }
  .table tr { display: flex; flex-wrap: wrap; gap: 0.4rem; padding: 0.75rem; border-bottom: 1px solid var(--border); align-items: center; }
  .table td { display: inline-flex; align-items: center; border: none; padding: 0; font-size: 0.82rem; }
  .table td:first-child { width: 100%; font-weight: 500; }
  .table td:last-child { margin-left: auto; }

  /* Stats grid: 2 columns on mobile */
  .stats-grid { grid-template-columns: 1fr 1fr; }

  /* Card header: wrap on mobile */
  .card-header { flex-wrap: wrap; }

  /* Form rows: single column */
  .form-row { grid-template-columns: 1fr !important; }

  /* Settings sections: full width */
  .settings-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
  .sidebar-overlay { display: none !important; }
}
</style>
</head>
<body class="scheme-<?= $admin_scheme ?>" id="admin-body">
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text">
      <div>Brisa<span>CMS</span></div>
      <small><?= htmlspecialchars(mb_strimwidth($title, 0, 22, '…')) ?></small>
    </div>
    <button class="sidebar-toggle" id="sidebar-toggle" title="<?= __("nav_collapse") ?>" aria-label="<?= __("nav_collapse") ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
  </div>

  <!-- New article quick button -->
  <a href="<?= base_url() ?>/admin/editor.php?type=articles" class="sidebar-new-btn" data-label="Nuevo artículo">
    <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    <span class="btn-new-label"><?= __("nav_new_article") ?></span>
  </a>

  <nav class="sidebar-nav">
    <div class="nav-section">Contenido</div>
    <a href="<?= base_url() ?>/admin/" class="nav-item <?= $active === 'dashboard' ? 'active' : '' ?>" data-label="Panel">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      <span class="nav-label"><?= __("nav_dashboard") ?></span>
    </a>
    <a href="<?= base_url() ?>/admin/articles.php" class="nav-item <?= $active === 'articles' ? 'active' : '' ?>" data-label="Artículos">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <span class="nav-label"><?= __("nav_articles") ?></span>
    </a>
    <a href="<?= base_url() ?>/admin/pages.php" class="nav-item <?= $active === 'pages' ? 'active' : '' ?>" data-label="Páginas">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
      <span class="nav-label"><?= __("nav_pages") ?></span>
    </a>
    <a href="<?= base_url() ?>/admin/media.php" class="nav-item <?= $active === 'media' ? 'active' : '' ?>" data-label="Media">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      <span class="nav-label"><?= __("nav_media") ?></span>
    </a>

    <div class="nav-section">Herramientas</div>
    <a href="<?= base_url() ?>/admin/import.php" class="nav-item <?= $active === 'import' ? 'active' : '' ?>" data-label="Importar WP">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <span class="nav-label"><?= __("nav_import_wp") ?></span>
    </a>
    <a href="<?= base_url() ?>/admin/import_images.php" class="nav-item <?= $active === 'import_images' ? 'active' : '' ?>" data-label="Importar imágenes">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
      <span class="nav-label"><?= __("nav_import_images") ?></span>
    </a>
    <a href="<?= base_url() ?>/admin/search_replace.php" class="nav-item <?= $active === 'tools' ? 'active' : '' ?>" data-label="Buscar y reemplazar">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <span class="nav-label"><?= __("nav_search_replace") ?></span>
    </a>
    <a href="<?= base_url() ?>/admin/export.php" class="nav-item <?= $active === 'export' ? 'active' : '' ?>" data-label="Exportar">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      <span class="nav-label"><?= __("nav_export") ?></span>
    </a>

    <div class="nav-section">Configuración</div>
    <a href="<?= base_url() ?>/admin/settings.php" class="nav-item <?= $active === 'settings' ? 'active' : '' ?>" data-label="Ajustes">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span class="nav-label"><?= __("nav_settings") ?></span>
    </a>
    <a href="<?= base_url() ?>/admin/themes.php" class="nav-item <?= $active === 'themes' ? 'active' : '' ?>" data-label="Temas">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><line x1="2" y1="12" x2="22" y2="12"/></svg>
      <span class="nav-label"><?= __("nav_themes") ?></span>
    </a>

    <div class="nav-section">Sitio</div>
    <a href="<?= base_url() ?>/" target="_blank" class="nav-item" data-label="Ver sitio">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      <span class="nav-label"><?= __("nav_view_site") ?></span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-row">
      <div class="user-avatar"><?= strtoupper(substr($user, 0, 1)) ?></div>
      <span class="user-name"><?= htmlspecialchars($user) ?></span>
    </div>
    <a href="<?= base_url() ?>/admin/logout.php" class="logout-btn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span class="logout-label"><?= __("nav_sign_out") ?></span>
    </a>
  </div>
</aside>

<div class="main" id="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="Menú">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="topbar-title"><?= htmlspecialchars($page_title) ?></div>
    </div>
    <div class="topbar-actions">
      <!-- Language switcher -->
      <?php $cur_lang = detect_admin_lang(); ?>
      <div style="display:flex;gap:2px;align-items:center" title="Idioma del panel / Panel language">
        <a href="?lang=es" style="font-size:0.72rem;font-weight:600;padding:0.2rem 0.4rem;border-radius:4px;text-decoration:none;
          background:<?= $cur_lang==='es' ? 'rgba(var(--accent-rgb),0.2)' : 'var(--surface2)' ?>;
          color:<?= $cur_lang==='es' ? 'var(--accent)' : 'var(--muted)' ?>;
          border:1px solid <?= $cur_lang==='es' ? 'rgba(var(--accent-rgb),0.4)' : 'var(--border)' ?>">ES</a>
        <a href="?lang=en" style="font-size:0.72rem;font-weight:600;padding:0.2rem 0.4rem;border-radius:4px;text-decoration:none;
          background:<?= $cur_lang==='en' ? 'rgba(var(--accent-rgb),0.2)' : 'var(--surface2)' ?>;
          color:<?= $cur_lang==='en' ? 'var(--accent)' : 'var(--muted)' ?>;
          border:1px solid <?= $cur_lang==='en' ? 'rgba(var(--accent-rgb),0.4)' : 'var(--border)' ?>">EN</a>
      </div>

<?php } ?>
<script>
// Run immediately — body already exists at this point
(function() {
  const COLLAPSED_KEY = 'brisa_sidebar_collapsed';

  // Restore collapsed state before paint to avoid flicker
  if (localStorage.getItem(COLLAPSED_KEY) === '1') {
    document.getElementById('admin-body').classList.add('sidebar-collapsed');
  }

  document.addEventListener('DOMContentLoaded', function() {
    const body          = document.getElementById('admin-body');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileBtn     = document.getElementById('mobile-menu-btn');
    const overlay       = document.getElementById('sidebar-overlay');

    // ── Desktop: collapse/expand ──────────────────────────────────────────
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', function() {
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem(COLLAPSED_KEY,
          body.classList.contains('sidebar-collapsed') ? '1' : '0');
      });
    }

    // ── Mobile: open/close drawer ─────────────────────────────────────────
    if (mobileBtn) {
      mobileBtn.addEventListener('click', function() {
        body.classList.toggle('sidebar-open');
      });
    }
    if (overlay) {
      overlay.addEventListener('click', function() {
        body.classList.remove('sidebar-open');
      });
    }

  });
})();
</script>
