<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

$csrf  = generate_csrf();
$log   = [];
$done  = false;
$total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    $search  = $_POST['search']  ?? '';
    $replace = $_POST['replace'] ?? '';
    $dry_run = ($_POST['dry_run'] ?? '0') === '1';

    if ($search === '') {
        $log[] = ['error', 'El campo "Buscar" no puede estar vacío.'];
    } else {
        foreach (['articles', 'pages'] as $type) {
            $dir = CONTENT_PATH . '/' . $type;
            if (!is_dir($dir)) continue;

            foreach (glob("$dir/*.json") as $file) {
                $post = json_decode(file_get_contents($file), true);
                if (!$post) continue;

                $changed  = false;
                $changes  = [];

                // Check and replace in content
                if (!empty($post['content']) && str_contains($post['content'], $search)) {
                    $count = substr_count($post['content'], $search);
                    if (!$dry_run) $post['content'] = str_replace($search, $replace, $post['content']);
                    $changes[] = "$count ocurrencia(s) en contenido";
                    $changed   = true;
                }

                // Check and replace in excerpt
                if (!empty($post['excerpt']) && str_contains($post['excerpt'], $search)) {
                    $count = substr_count($post['excerpt'], $search);
                    if (!$dry_run) $post['excerpt'] = str_replace($search, $replace, $post['excerpt']);
                    $changes[] = "$count en resumen";
                    $changed   = true;
                }

                // Check and replace in featured_image URL
                if (!empty($post['featured_image']) && str_contains($post['featured_image'], $search)) {
                    if (!$dry_run) $post['featured_image'] = str_replace($search, $replace, $post['featured_image']);
                    $changes[] = "1 en imagen destacada";
                    $changed   = true;
                }

                if ($changed) {
                    if (!$dry_run) {
                        file_put_contents($file, json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                    $total++;
                    $type_label = $type === 'articles' ? 'Artículo' : 'Página';
                    $log[] = ['ok', "$type_label: «{$post['title']}» — " . implode(', ', $changes)];
                }
            }
        }

        if ($total === 0) {
            $log[] = ['info', 'No se encontró "' . htmlspecialchars($search) . '" en ningún artículo o página.'];
        } else {
            $action = $dry_run ? "Se encontró en" : "Reemplazado en";
            $log[]  = ['done', "$action $total artículo(s)/página(s)."];
        }
        $done = true;
        clear_cache();
    }
}

admin_header('Buscar y Reemplazar', 'tools');
?>
    </div>
  </div>
  <div class="page-body">
    <div style="max-width:640px">

      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><span class="card-title">Buscar y reemplazar en todo el contenido</span></div>
        <div class="card-body">
          <p style="color:var(--text2);font-size:0.875rem;margin-bottom:1.25rem">
            Busca y reemplaza texto en el contenido, resumen e imagen destacada de todos tus artículos y páginas.
            Útil para cambiar URLs de dominio después de una migración.
          </p>

          <!-- Quick presets -->
          <div style="margin-bottom:1.25rem">
            <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);margin-bottom:0.5rem">Acceso rápido</div>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem">
              <?php
              $config   = cms_config();
              $base_url = rtrim($config['base_url'] ?? '', '/');
              $presets  = [
                  ['Dominio antiguo → nuevo', 'https://www.systeminside.net', $base_url],
                  ['HTTP → HTTPS', 'http://' . parse_url($base_url, PHP_URL_HOST), $base_url],
                  ['www → sin www', 'https://www.' . (parse_url($base_url, PHP_URL_HOST) ?? ''), $base_url],
              ];
              foreach ($presets as [$label, $from, $to]):
              ?>
              <button type="button" class="btn btn-secondary btn-sm"
                onclick="document.getElementById('search').value=<?= json_encode($from) ?>;document.getElementById('replace').value=<?= json_encode($to) ?>">
                <?= htmlspecialchars($label) ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>

          <form method="POST" id="sr-form">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">

            <div class="form-group">
              <label class="form-label">Buscar</label>
              <input type="text" name="search" id="search"
                value="<?= htmlspecialchars($_POST['search'] ?? '') ?>"
                placeholder="https://www.systeminside.net" required
                style="font-family:monospace">
            </div>

            <div class="form-group">
              <label class="form-label">Reemplazar por</label>
              <input type="text" name="replace" id="replace"
                value="<?= htmlspecialchars($_POST['replace'] ?? $base_url) ?>"
                placeholder="<?= htmlspecialchars($base_url) ?>"
                style="font-family:monospace">
              <div style="font-size:0.75rem;color:var(--muted);margin-top:0.35rem">
                Deja vacío para eliminar el texto buscado.
              </div>
            </div>

            <div style="background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:0.85rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:0.75rem">
              <input type="checkbox" name="dry_run" value="1" id="dry-run"
                style="width:16px;height:16px;accent-color:var(--accent);flex-shrink:0;margin-top:2px"
                <?= ($_POST['dry_run'] ?? '') === '1' ? 'checked' : '' ?>>
              <div>
                <label for="dry-run" style="font-weight:500;cursor:pointer">Simulación (solo ver, no cambiar)</label>
                <div style="font-size:0.78rem;color:var(--muted);margin-top:2px">
                  Muestra qué artículos se verían afectados sin modificar nada. Recomendado antes del reemplazo real.
                </div>
              </div>
            </div>

            <div style="background:rgba(248,113,113,0.08);border:1px solid rgba(248,113,113,0.2);border-radius:8px;padding:0.75rem;font-size:0.82rem;color:var(--red);margin-bottom:1rem">
              ⚠ Esta operación modifica los archivos directamente y no tiene deshacer. Haz una copia de seguridad de <code>content/</code> antes si no usas simulación.
            </div>

            <div style="display:flex;gap:0.75rem">
              <button type="submit" name="dry_run" value="1" class="btn btn-secondary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Simular
              </button>
              <button type="submit" name="dry_run" value="0" class="btn btn-primary"
                onclick="return confirm('¿Confirmas el reemplazo en todos los artículos? Esta acción no se puede deshacer.')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Reemplazar
              </button>
            </div>
          </form>
        </div>
      </div>

      <?php if (!empty($log)): ?>
      <div class="card">
        <div class="card-header" style="gap:0.75rem">
          <span class="card-title">
            <?= ($_POST['dry_run'] ?? '0') === '1' ? 'Resultado de la simulación' : 'Resultado del reemplazo' ?>
          </span>
          <?php if ($done && $total > 0): ?>
          <span class="badge <?= ($_POST['dry_run'] ?? '0') === '1' ? 'badge-yellow' : 'badge-green' ?>">
            <?= $total ?> archivo<?= $total !== 1 ? 's' : '' ?> <?= ($_POST['dry_run'] ?? '0') === '1' ? 'encontrado' : 'modificado' ?><?= $total !== 1 ? 's' : '' ?>
          </span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <div style="display:flex;flex-direction:column;gap:0.4rem">
            <?php foreach ($log as [$type_log, $msg]): ?>
            <div style="
              font-size:0.85rem;
              padding:0.4rem 0.65rem;
              border-radius:6px;
              color:<?= match($type_log) {
                'ok'    => 'var(--green)',
                'done'  => 'var(--green)',
                'error' => 'var(--red)',
                default => 'var(--text2)',
              } ?>;
              background:<?= match($type_log) {
                'ok'    => 'rgba(52,211,153,0.06)',
                'done'  => 'rgba(52,211,153,0.1)',
                'error' => 'rgba(248,113,113,0.1)',
                default => 'var(--surface2)',
              } ?>">
              <?= htmlspecialchars($msg) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
</body></html>
