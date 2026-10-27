<?php
/**
 * KutPod Plugin - Settings Panel for BrisaCMS Admin
 */

if (!defined('CMS_NAME') && !defined('ROOT_PATH')) {
    exit;
}

require_once __DIR__ . '/kutpod.php';
require_once __DIR__ . '/sync.php';

$csrf = generate_csrf();
$msg = '';
$error = '';
$sync_report = null;

$cfg = kutpod_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Error de seguridad (CSRF inválido). Actualiza la página e inténtalo de nuevo.';
    } else {
        $action = $_POST['action_type'] ?? 'save_settings';

        if ($action === 'sync_now') {
            $sync_report = kutpod_run_sync();
            if (!empty($sync_report['errors'])) {
                $error = implode('<br>', $sync_report['errors']);
            } else {
                $msg = "Sincronización completada con éxito: {$sync_report['created']} episodios creados, {$sync_report['updated']} actualizados.";
            }
        } elseif ($action === 'save_settings') {
            $cfg['connection_mode']         = in_array($_POST['connection_mode'] ?? '', ['local', 'remote']) ? $_POST['connection_mode'] : 'local';
            $cfg['instance_url']            = rtrim(trim($_POST['instance_url'] ?? ''), '/');
            $cfg['local_db_path']           = trim($_POST['local_db_path'] ?? '');
            $cfg['podcast_filter']          = trim($_POST['podcast_filter'] ?? 'all') ?: 'all';
            $cfg['categorize_by_show']      = !empty($_POST['categorize_by_show']);
            $cfg['default_status']          = in_array($_POST['default_status'] ?? '', ['draft', 'published']) ? $_POST['default_status'] : 'draft';
            $cfg['default_category']        = trim($_POST['default_category'] ?? 'Podcast') ?: 'Podcast';
            $cfg['auto_update_notes']       = !empty($_POST['auto_update_notes']);
            $cfg['delete_on_kutpod_delete'] = !empty($_POST['delete_on_kutpod_delete']);
            $cfg['player_position']         = in_array($_POST['player_position'] ?? '', ['top', 'bottom', 'manual']) ? $_POST['player_position'] : 'top';
            $cfg['player_theme']            = in_array($_POST['player_theme'] ?? '', ['auto', 'dark', 'light']) ? $_POST['player_theme'] : 'auto';
            $cfg['enable_timestamps']       = !empty($_POST['enable_timestamps']);
            $cfg['enable_transcripts']      = !empty($_POST['enable_transcripts']);
            $cfg['enable_stats_badge']      = !empty($_POST['enable_stats_badge']);
            $cfg['enable_directory']        = !empty($_POST['enable_directory']);
            $cfg['directory_slug']          = sanitize_filename(trim($_POST['directory_slug'] ?? 'podcasts') ?: 'podcasts');
            $cfg['directory_title']         = trim($_POST['directory_title'] ?? 'Episodios y Podcasts');
            $cfg['directory_desc']          = trim($_POST['directory_desc'] ?? '');
            $cfg['enable_related']          = !empty($_POST['enable_related']);
            $cfg['search_transcripts']      = !empty($_POST['search_transcripts']);

            kutpod_save_config($cfg);
            $msg = 'Configuración guardada correctamente.';
        }
    }
}

// Estadísticas de episodios sincronizados en BrisaCMS
$allArticles = list_content('articles', false, 1, 9999)['items'] ?? [];
$syncedEpisodesCount = 0;
foreach ($allArticles as $art) {
    if (!empty($art['podcast_meta']['is_podcast']) || !empty($art['podcast_meta']['audio_url'])) {
        $syncedEpisodesCount++;
    }
}

$isLocalDbFound = file_exists($cfg['local_db_path']);
$webhookUrl = rtrim(base_url(), '/') . '/api/kutpod/webhook';

// Cargar lista de podcasts de KutPod si SQLite está disponible
$availablePodcasts = [];
if ($isLocalDbFound) {
    try {
        $testPdo = new PDO("sqlite:{$cfg['local_db_path']}", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $availablePodcasts = $testPdo->query("SELECT id, slug, title FROM podcasts WHERE hidden = 0 ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

?>

<div style="max-width: 900px; margin: 0 auto;">
  
  <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem;">✓ <?= $msg ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom: 1.5rem;">⚠ <?= $error ?></div>
  <?php endif; ?>

  <!-- Tarjeta de Estado y Sincronización Rápida -->
  <div class="card" style="margin-bottom: 2rem; border-left: 4px solid #ff5f7e;">
    <div class="card-body" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding: 1.5rem;">
      <div>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
          <span style="font-size: 1.4rem;">🎙️</span>
          <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700;">KutPod Studio</h2>
          <?php if ($cfg['connection_mode'] === 'local' && $isLocalDbFound): ?>
            <span class="badge" style="background: rgba(34,197,94,0.15); color: #16a34a; font-size: 0.75rem; padding: 3px 8px; border-radius: 20px; font-weight: 600;">✓ SQLite Conectado</span>
          <?php elseif ($cfg['connection_mode'] === 'remote' && !empty($cfg['instance_url'])): ?>
            <span class="badge" style="background: rgba(59,130,246,0.15); color: #2563eb; font-size: 0.75rem; padding: 3px 8px; border-radius: 20px; font-weight: 600;">🌐 Modo Remoto</span>
          <?php else: ?>
            <span class="badge" style="background: rgba(239,68,68,0.15); color: #dc2626; font-size: 0.75rem; padding: 3px 8px; border-radius: 20px; font-weight: 600;">⚠ Configuración requerida</span>
          <?php endif; ?>
        </div>
        <p style="margin: 0; font-size: 0.9rem; color: var(--muted);">
          <strong><?= $syncedEpisodesCount ?></strong> episodios sincronizados en este blog · Directorio público: 
          <?php if ($cfg['enable_directory']): ?>
            <a href="<?= base_url() ?>/<?= htmlspecialchars($cfg['directory_slug']) ?>" target="_blank" style="color: #ff5f7e; font-weight: 600;">/<?= htmlspecialchars($cfg['directory_slug']) ?> ↗</a>
          <?php else: ?>
            <em>desactivado</em>
          <?php endif; ?>
        </p>
      </div>

      <form method="POST" style="margin: 0;">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action_type" value="sync_now">
        <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; background: #ff5f7e; border-color: #ff5f7e;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
          Sincronizar episodios ahora
        </button>
      </form>
    </div>
  </div>

  <!-- Formulario de Configuración Principal -->
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action_type" value="save_settings">

    <!-- 1. CONEXIÓN Y WEBHOOKS -->
    <div class="card" style="margin-bottom: 2rem;">
      <div class="card-header">
        <span class="card-title" style="display: flex; align-items: center; gap: 8px;">
          🔌 1. Conexión y Webhooks de KutPod
        </span>
      </div>
      <div class="card-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
        
        <div>
          <label style="display: block; font-weight: 600; margin-bottom: 6px;">Modo de Conexión</label>
          <div style="display: flex; gap: 1.5rem;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
              <input type="radio" name="connection_mode" value="local" <?= $cfg['connection_mode'] === 'local' ? 'checked' : '' ?> onchange="toggleConnectionFields()">
              <span><strong>Local (Mismo servidor / SQLite)</strong> — Mayor velocidad y acceso directo a base de datos</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
              <input type="radio" name="connection_mode" value="remote" <?= $cfg['connection_mode'] === 'remote' ? 'checked' : '' ?> onchange="toggleConnectionFields()">
              <span><strong>Remoto (Vía REST API)</strong></span>
            </label>
          </div>
        </div>

        <div id="field_local_db">
          <label style="display: block; font-weight: 600; margin-bottom: 6px;">Ruta a la base de datos SQLite de KutPod</label>
          <input type="text" name="local_db_path" value="<?= htmlspecialchars($cfg['local_db_path']) ?>" class="form-control" style="font-family: monospace;">
          <small style="color: var(--muted); display: block; margin-top: 4px;">
            <?= $isLocalDbFound ? '✓ Archivo encontrado correctamente.' : '⚠ No se encuentra el archivo en esta ruta. Verifica que exista storage/kutpod.db' ?>
          </small>
        </div>

        <div>
          <label style="display: block; font-weight: 600; margin-bottom: 6px;">URL Pública de la Instancia KutPod</label>
          <input type="text" name="instance_url" value="<?= htmlspecialchars($cfg['instance_url']) ?>" class="form-control" placeholder="http://localhost:8000 o https://podcast.tudominio.com">
          <small style="color: var(--muted); display: block; margin-top: 4px;">
            Utilizada por el reproductor `iframe` embebido en los posts y en los enlaces públicos.
          </small>
        </div>

        <div style="background: rgba(0,0,0,0.03); border: 1px dashed var(--border); border-radius: 10px; padding: 1rem;">
          <label style="display: block; font-weight: 600; margin-bottom: 6px;">🔗 URL del Webhook Receptor (para configurar en KutPod)</label>
          <div style="display: flex; gap: 8px;">
            <input type="text" id="webhook_url_input" readonly value="<?= htmlspecialchars($webhookUrl) ?>" class="form-control" style="font-family: monospace; background: var(--card-bg);">
            <button type="button" class="btn btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('webhook_url_input').value); alert('URL del Webhook copiada al portapapeles');">Copiar</button>
          </div>
          <small style="color: var(--muted); display: block; margin-top: 6px;">
            Pega esta URL en los ajustes de KutPod (o en su plugin) para que te notifique en tiempo real cuando se publique un episodio.
          </small>
        </div>

        <div>
          <label style="display: block; font-weight: 600; margin-bottom: 6px;">Token Secreto del Webhook</label>
          <div style="display: flex; gap: 8px;">
            <input type="text" id="webhook_secret_input" name="webhook_secret" value="<?= htmlspecialchars($cfg['webhook_secret']) ?>" class="form-control" style="font-family: monospace;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('webhook_secret_input').value = Array.from(crypto.getRandomValues(new Uint8Array(16))).map(b => b.toString(16).padStart(2,'0')).join('');">Generar nuevo</button>
          </div>
          <small style="color: var(--muted); display: block; margin-top: 4px;">
            Clave compartida para verificar que las llamadas provienen legítimamente de tu KutPod.
          </small>
        </div>

      </div>
    </div>

    <!-- 2. AUTOMATIZACIÓN Y PUBLICACIÓN -->
    <div class="card" style="margin-bottom: 2rem;">
      <div class="card-header">
        <span class="card-title" style="display: flex; align-items: center; gap: 8px;">
          ⚡ 2. Automatización de Publicaciones (Drafting)
        </span>
      </div>
      <div class="card-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
        
        <div>
          <label style="display: block; font-weight: 600; margin-bottom: 6px;">Podcasts a Sincronizar</label>
          <select name="podcast_filter" class="form-control">
            <option value="all" <?= ($cfg['podcast_filter'] ?? 'all') === 'all' ? 'selected' : '' ?>>Todos los Podcasts de KutPod (<?= count($availablePodcasts) ?> programas)</option>
            <?php foreach ($availablePodcasts as $pod): ?>
              <option value="<?= htmlspecialchars($pod['slug']) ?>" <?= ($cfg['podcast_filter'] ?? '') === $pod['slug'] ? 'selected' : '' ?>>
                Solo: <?= htmlspecialchars($pod['title']) ?> (@<?= htmlspecialchars($pod['slug']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small style="color: var(--muted); display: block; margin-top: 4px;">
            Permite sincronizar todos los podcasts de tu instancia KutPod o elegir únicamente uno para este blog.
          </small>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
          <div>
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Estado inicial de nuevos episodios</label>
            <select name="default_status" class="form-control">
              <option value="draft" <?= $cfg['default_status'] === 'draft' ? 'selected' : '' ?>>Borrador (Revisar en el blog antes de publicar)</option>
              <option value="published" <?= $cfg['default_status'] === 'published' ? 'selected' : '' ?>>Publicado inmediatamente</option>
            </select>
            <small style="color: var(--muted); display: block; margin-top: 4px;">
              Recomendado: <em>Borrador</em> para poder añadir notas editoriales antes de lanzar la entrada.
            </small>
          </div>

          <div>
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Categoría fija de respaldo</label>
            <input type="text" name="default_category" value="<?= htmlspecialchars($cfg['default_category']) ?>" class="form-control">
            <small style="color: var(--muted); display: block; margin-top: 4px;">
              Categoría por defecto si no se categoriza por show (ej: <code>Podcast</code>).
            </small>
          </div>
        </div>

        <div>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="categorize_by_show" value="1" <?= !empty($cfg['categorize_by_show']) ? 'checked' : '' ?>>
            <span><strong>Categorizar por nombre de Podcast:</strong> Asigna automáticamente el nombre del show (ej. <em>"SystemInside Podcast"</em> o <em>"FLASH"</em>) como su categoría en BrisaCMS.</span>
          </label>
        </div>

        <div>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="auto_update_notes" value="1" <?= !empty($cfg['auto_update_notes']) ? 'checked' : '' ?>>
            <span><strong>Sincronizar actualizaciones de notas:</strong> Si editas la descripción o notas en KutPod, actualizar automáticamente el contenido en BrisaCMS.</span>
          </label>
        </div>

        <div>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="delete_on_kutpod_delete" value="1" <?= !empty($cfg['delete_on_kutpod_delete']) ? 'checked' : '' ?>>
            <span><strong>Eliminar post si se borra en KutPod:</strong> Si se desmarca, el artículo simplemente se pasará a borrador (más seguro).</span>
          </label>
        </div>

      </div>
    </div>

    <!-- 3. REPRODUCTOR Y EXPERIENCIA DE LECTURA -->
    <div class="card" style="margin-bottom: 2rem;">
      <div class="card-header">
        <span class="card-title" style="display: flex; align-items: center; gap: 8px;">
          🎧 3. Reproductor, Capítulos y Transcripciones
        </span>
      </div>
      <div class="card-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
          <div>
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Ubicación del reproductor en el artículo</label>
            <select name="player_position" class="form-control">
              <option value="top" <?= $cfg['player_position'] === 'top' ? 'selected' : '' ?>>Arriba (Antes del texto del artículo)</option>
              <option value="bottom" <?= $cfg['player_position'] === 'bottom' ? 'selected' : '' ?>>Al final (Después del contenido)</option>
              <option value="manual" <?= $cfg['player_position'] === 'manual' ? 'selected' : '' ?>>Manual (Colocar mediante shortcode [kutpod])</option>
            </select>
          </div>

          <div>
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Tema visual del reproductor incrustado</label>
            <select name="player_theme" class="form-control">
              <option value="auto" <?= $cfg['player_theme'] === 'auto' ? 'selected' : '' ?>>Automático (Se adapta al modo claro/oscuro del usuario)</option>
              <option value="dark" <?= $cfg['player_theme'] === 'dark' ? 'selected' : '' ?>>Oscuro (Dark mode permanente)</option>
              <option value="light" <?= $cfg['player_theme'] === 'light' ? 'selected' : '' ?>>Claro (Light mode permanente)</option>
            </select>
          </div>
        </div>

        <div>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="enable_timestamps" value="1" <?= !empty($cfg['enable_timestamps']) ? 'checked' : '' ?>>
            <span><strong>Timestamps cliqueables (Capítulos interactivos):</strong> Convierte marcas como <code>04:15</code> o <code>[12:30]</code> en botones para saltar en tiempo real en el reproductor.</span>
          </label>
        </div>

        <div>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="enable_stats_badge" value="1" <?= !empty($cfg['enable_stats_badge']) ? 'checked' : '' ?>>
            <span><strong>Mostrar estadísticas del episodio:</strong> Barra con duración, temporada/número y número de escuchas (OP3).</span>
          </label>
        </div>

        <div>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="enable_transcripts" value="1" <?= !empty($cfg['enable_transcripts']) ? 'checked' : '' ?>>
            <span><strong>Mostrar bloque de Transcripción colapsable (SEO):</strong> Renderiza el texto de la transcripción en un acordeón accesible e indexable por buscadores.</span>
          </label>
        </div>

      </div>
    </div>

    <!-- 4. DESCUBRIMIENTO Y NAVEGACIÓN -->
    <div class="card" style="margin-bottom: 2rem;">
      <div class="card-header">
        <span class="card-title" style="display: flex; align-items: center; gap: 8px;">
          🧭 4. Descubrimiento, Directorio y Buscador
        </span>
      </div>
      <div class="card-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
        
        <div>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="enable_directory" value="1" <?= !empty($cfg['enable_directory']) ? 'checked' : '' ?>>
            <span><strong>Activar página de directorio de podcasts:</strong> Crea una sección dedicada para explorar y escuchar todos los episodios.</span>
          </label>
        </div>

        <div style="display: grid; grid-template-columns: 200px 1fr; gap: 1rem;">
          <div>
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Ruta / Slug</label>
            <div style="display: flex; align-items: center; gap: 4px;">
              <span>/</span>
              <input type="text" name="directory_slug" value="<?= htmlspecialchars($cfg['directory_slug']) ?>" class="form-control" placeholder="podcasts">
            </div>
          </div>
          <div>
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Título del Directorio</label>
            <input type="text" name="directory_title" value="<?= htmlspecialchars($cfg['directory_title']) ?>" class="form-control">
          </div>
        </div>

        <div>
          <label style="display: block; font-weight: 600; margin-bottom: 6px;">Descripción del Directorio</label>
          <textarea name="directory_desc" class="form-control" rows="2"><?= htmlspecialchars($cfg['directory_desc']) ?></textarea>
        </div>

        <div>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="enable_related" value="1" <?= !empty($cfg['enable_related']) ? 'checked' : '' ?>>
            <span><strong>Sección de Episodios Relacionados:</strong> Al final de los artículos de texto estándar del blog, recomendar automáticamente episodios afines por categoría o etiquetas.</span>
          </label>
        </div>

        <div>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="search_transcripts" value="1" <?= !empty($cfg['search_transcripts']) ? 'checked' : '' ?>>
            <span><strong>Buscador Global con Transcripciones:</strong> Si un usuario busca palabras clave en el blog, incluir coincidencias dentro del contenido hablado del podcast.</span>
          </label>
        </div>

      </div>
    </div>

    <!-- Botón de Guardar Ajustes -->
    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 3rem;">
      <a href="<?= base_url() ?>/admin/plugins.php" class="btn btn-secondary">Volver a Plugins</a>
      <button type="submit" class="btn btn-primary" style="padding: 0.6rem 2rem;">Guardar Configuración</button>
    </div>

  </form>
</div>

<script>
function toggleConnectionFields() {
  const isLocal = document.querySelector('input[name="connection_mode"]:checked').value === 'local';
  document.getElementById('field_local_db').style.display = isLocal ? 'block' : 'none';
}
toggleConnectionFields();
</script>
