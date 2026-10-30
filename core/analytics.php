<?php
// KutBlog - Native Cookie-Free Analytics
// Privacy-first, lightweight, zero-cookie visitor tracking inspired by quireINK

if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/core/config.php';
}

function analytics_dir(): string {
    $dir = CONTENT_PATH . '/analytics';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess', "Deny from all\n");
    }
    return $dir;
}

function analytics_salt(): string {
    $saltFile = analytics_dir() . '/salt.key';
    if (file_exists($saltFile)) {
        $salt = trim((string)@file_get_contents($saltFile));
        if ($salt !== '') return $salt;
    }
    $salt = bin2hex(random_bytes(32));
    @file_put_contents($saltFile, $salt);
    return $salt;
}

/**
 * Filter out search spiders, AI crawlers, preview bots, and scrapers
 */
function analytics_is_bot(string $ua): bool {
    if ($ua === '') return true;
    $pattern = '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|quora|pinterest|vkshare|whatsapp|telegrambot|discord|headless|lighthouse|pagespeed|gtmetrix|monitor|uptime|curl|wget|python-requests|axios|node-fetch|gptbot|oai-searchbot|chatgpt|claudebot|claude-web|anthropic|ccbot|perplexity|bytespider|amazonbot|google-extended|meta-external|scrapy|semrush|ahrefs|dataforseo|inspectiontool|read-aloud|mediapartners|petalbot|yeti/i';
    return (bool)preg_match($pattern, $ua);
}

/**
 * Salted hash of IP + UA. Discards raw IP and UA immediately.
 * Produces an anonymous 16-character identifier.
 */
function analytics_visitor_hash(string $ip, string $ua): string {
    $salt = analytics_salt();
    return substr(hash('sha256', "{$salt}|{$ip}|{$ua}"), 0, 16);
}

/**
 * Check if the request is an internal loopback or private address
 */
function analytics_is_internal_ip(string $ip): bool {
    $ip = trim($ip);
    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') return true;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }
    return false;
}

/**
 * Coarse device and OS parser
 */
function analytics_parse_ua(string $ua, bool $touch = false): array {
    $device = 'desktop';
    if ($touch || preg_match('/mobile|iphone|ipod|android.*mobile/i', $ua)) {
        $device = 'mobile';
    } elseif (preg_match('/ipad|tablet|android(?!.*mobile)/i', $ua)) {
        $device = 'tablet';
    }

    $os = 'Otro';
    if (stripos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) $os = $touch ? 'iPadOS' : 'macOS';
    elseif (stripos($ua, 'iPhone') !== false) $os = 'iOS';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';

    $browser = 'Otro';
    if (stripos($ua, 'Firefox') !== false) $browser = 'Firefox';
    elseif (stripos($ua, 'Edg') !== false) $browser = 'Edge';
    elseif (stripos($ua, 'Opera') !== false || stripos($ua, 'OPR') !== false) $browser = 'Opera';
    elseif (stripos($ua, 'Chrome') !== false && stripos($ua, 'Safari') !== false) $browser = 'Chrome';
    elseif (stripos($ua, 'Safari') !== false) $browser = 'Safari';

    return ['device' => $device, 'os' => $os, 'browser' => $browser];
}

/**
 * Normalizes client IP from reverse proxies / Cloudflare if present
 */
function analytics_client_ip(): string {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ];
    foreach ($candidates as $candidate) {
        if (!$candidate) continue;
        $parts = explode(',', $candidate);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '127.0.0.1';
}

/**
 * Clean path (removes query, hash, clamps length)
 */
function analytics_clean_path(string $path): ?string {
    $path = trim(explode('?', explode('#', $path)[0])[0]);
    if ($path === '') return '/';
    if (!str_starts_with($path, '/')) $path = '/' . $path;
    if (str_starts_with($path, '/admin') || str_starts_with($path, '/api')) return null;
    return substr($path, 0, 255);
}

/**
 * Clean referrer host
 */
function analytics_clean_referrer(string $ref): string {
    $ref = trim($ref);
    if ($ref === '') return '';
    if (str_starts_with($ref, 'http://') || str_starts_with($ref, 'https://')) {
        $host = parse_url($ref, PHP_URL_HOST);
        $ref = $host ? (string)$host : '';
    }
    $ref = strtolower(preg_replace('/[^a-z0-9.-]/i', '', $ref));
    $siteHost = parse_url(base_url(), PHP_URL_HOST);
    if ($ref === $siteHost) return '';
    return substr($ref, 0, 100);
}

/**
 * Record a pageview
 */
function analytics_record_view(string $path, string $ip, string $ua, string $ref = '', bool $touch = false): bool {
    if (function_exists('is_logged_in') && is_logged_in()) return false;
    if (analytics_is_bot($ua)) return false;
    if (analytics_is_internal_ip($ip)) return false;

    $cleanPath = analytics_clean_path($path);
    if ($cleanPath === null) return false;

    $visitor = analytics_visitor_hash($ip, $ua);
    $parsedUa = analytics_parse_ua($ua, $touch);
    $cleanRef = analytics_clean_referrer($ref);

    $record = [
        'type'    => 'view',
        'ts'      => time(),
        'date'    => date('Y-m-d'),
        'path'    => $cleanPath,
        'visitor' => $visitor,
        'ref'     => $cleanRef,
        'device'  => $parsedUa['device'],
        'os'      => $parsedUa['os'],
        'browser' => $parsedUa['browser'],
    ];

    $logFile = analytics_dir() . '/events-' . date('Y-m') . '.jsonl';
    $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    return @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Record scroll depth and dwell time
 */
function analytics_record_scroll(string $path, string $ip, string $ua, int $depth, int $dwell = 0): bool {
    if (function_exists('is_logged_in') && is_logged_in()) return false;
    if (analytics_is_bot($ua)) return false;
    if (analytics_is_internal_ip($ip)) return false;

    $cleanPath = analytics_clean_path($path);
    if ($cleanPath === null) return false;

    $depth = max(0, min(100, $depth));
    $dwell = max(0, min(86400, $dwell));
    $visitor = analytics_visitor_hash($ip, $ua);

    $record = [
        'type'    => 'scroll',
        'ts'      => time(),
        'date'    => date('Y-m-d'),
        'path'    => $cleanPath,
        'visitor' => $visitor,
        'depth'   => $depth,
        'dwell'   => $dwell,
    ];

    $logFile = analytics_dir() . '/events-' . date('Y-m') . '.jsonl';
    $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    return @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Fetch and aggregate analytics stats for a given time window
 */
function analytics_get_stats(string $range = '30d'): array {
    $dir = analytics_dir();
    $now = time();
    $days = match($range) {
        'today' => 1,
        '7d'    => 7,
        'all'   => 3650,
        default => 30,
    };
    $cutoff = $days >= 3650 ? 0 : strtotime("-{$days} days", strtotime('today'));

    $totalViews = 0;
    $visitors = [];
    $dailyViews = [];
    $dailyVisitors = [];
    $pathCounts = [];
    $pathVisitors = [];
    $pathDepths = [];
    $referrers = [];
    $devices = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0];
    $browsers = [];
    $totalDepth = 0;
    $depthCount = 0;
    $totalDwell = 0;
    $dwellCount = 0;

    // Initialize daily chart keys for continuous timeline
    if ($days <= 30) {
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $dailyViews[$d] = 0;
            $dailyVisitors[$d] = [];
        }
    }

    // Determine relevant month log files
    $files = glob($dir . '/events-*.jsonl') ?: [];
    rsort($files);

    foreach ($files as $file) {
        $handle = @fopen($file, 'r');
        if (!$handle) continue;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '' || $line[0] !== '{') continue;
            $ev = json_decode($line, true);
            if (!$ev || !isset($ev['ts'])) continue;

            $ts = (int)$ev['ts'];
            if ($cutoff > 0 && $ts < $cutoff) continue;

            $date = $ev['date'] ?? date('Y-m-d', $ts);
            $type = $ev['type'] ?? 'view';
            $path = $ev['path'] ?? '/';
            $vis  = $ev['visitor'] ?? '';

            if ($type === 'view') {
                $totalViews++;
                $visitors[$vis] = true;

                if (!isset($dailyViews[$date])) $dailyViews[$date] = 0;
                $dailyViews[$date]++;

                if (!isset($dailyVisitors[$date])) $dailyVisitors[$date] = [];
                $dailyVisitors[$date][$vis] = true;

                $pathCounts[$path] = ($pathCounts[$path] ?? 0) + 1;
                if (!isset($pathVisitors[$path])) $pathVisitors[$path] = [];
                $pathVisitors[$path][$vis] = true;

                $ref = $ev['ref'] ?? '';
                if ($ref !== '') {
                    $referrers[$ref] = ($referrers[$ref] ?? 0) + 1;
                }

                $dev = $ev['device'] ?? 'desktop';
                if (isset($devices[$dev])) $devices[$dev]++;
                else $devices['desktop']++;

                $br = $ev['browser'] ?? 'Otro';
                $browsers[$br] = ($browsers[$br] ?? 0) + 1;

            } elseif ($type === 'scroll') {
                $depth = (int)($ev['depth'] ?? 0);
                $dwell = (int)($ev['dwell'] ?? 0);

                if ($depth > 0) {
                    $totalDepth += $depth;
                    $depthCount++;
                    if (!isset($pathDepths[$path])) $pathDepths[$path] = ['sum' => 0, 'count' => 0];
                    $pathDepths[$path]['sum'] += $depth;
                    $pathDepths[$path]['count']++;
                }

                if ($dwell > 0) {
                    $totalDwell += $dwell;
                    $dwellCount++;
                }
            }
        }
        fclose($handle);
    }

    // Top paths sorting
    arsort($pathCounts);
    $topPaths = [];
    $i = 0;
    foreach ($pathCounts as $p => $count) {
        if ($i++ >= 15) break;
        $pAvgDepth = 0;
        if (!empty($pathDepths[$p]['count'])) {
            $pAvgDepth = round($pathDepths[$p]['sum'] / $pathDepths[$p]['count']);
        }
        $topPaths[] = [
            'path'     => $p,
            'views'    => $count,
            'uniques'  => count($pathVisitors[$p] ?? []),
            'avg_depth'=> $pAvgDepth
        ];
    }

    // Top referrers sorting
    arsort($referrers);
    $topReferrers = array_slice($referrers, 0, 10, true);

    // Browsers sorting
    arsort($browsers);

    // Daily series
    ksort($dailyViews);
    $timeline = [];
    foreach ($dailyViews as $d => $vCount) {
        $uCount = isset($dailyVisitors[$d]) ? count($dailyVisitors[$d]) : 0;
        $timeline[] = [
            'date'    => $d,
            'label'   => date('d M', strtotime($d)),
            'views'   => $vCount,
            'uniques' => $uCount
        ];
    }

    return [
        'range'         => $range,
        'total_views'   => $totalViews,
        'unique_visitors' => count($visitors),
        'avg_depth'     => $depthCount > 0 ? round($totalDepth / $depthCount) : 0,
        'avg_dwell'     => $dwellCount > 0 ? round($totalDwell / $dwellCount) : 0,
        'timeline'      => $timeline,
        'top_paths'     => $topPaths,
        'top_referrers' => $topReferrers,
        'devices'       => $devices,
        'browsers'      => $browsers,
    ];
}

/**
 * Injects lightweight anonymous tracker script into theme footer
 */
function analytics_render_tracker(): void {
    if (function_exists('is_logged_in') && is_logged_in()) {
        return; // Don't track logged-in administrators
    }
    $apiTrackUrl = base_url() . '/api/track.php';
    ?>
<!-- KutBlog Anonymous Tracking (Cookie-Free) -->
<script>
(function(){
  try {
    var path = location.pathname;
    if (path.indexOf('/admin') === 0 || path.indexOf('/api') === 0) return;
    var trackUrl = <?= json_encode($apiTrackUrl) ?>;
    var ref = (document.referrer && new URL(document.referrer, location.href).host !== location.host) ? document.referrer : '';
    var touch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

    // 1. Send pageview beacon immediately
    var viewPayload = JSON.stringify({ path: path, referrer: ref, touch: touch });
    if (navigator.sendBeacon) {
      navigator.sendBeacon(trackUrl, new Blob([viewPayload], { type: 'application/json' }));
    } else {
      fetch(trackUrl, { method: 'POST', body: viewPayload, headers: { 'Content-Type': 'application/json' }, keepalive: true }).catch(function(){});
    }

    // 2. Measure engaged dwell and scroll depth
    var maxDepth = 0;
    var dwellSec = 0;
    var activeTimer = null;
    var isEngaged = true;
    var sent = false;

    function calcDepth() {
      var doc = document.documentElement;
      var scrollable = doc.scrollHeight - doc.clientHeight;
      if (scrollable <= 0) return 100;
      var pct = Math.round((window.scrollY / scrollable) * 100);
      return Math.max(0, Math.min(100, pct));
    }

    function onActivity() {
      isEngaged = true;
      var d = calcDepth();
      if (d > maxDepth) maxDepth = d;
    }

    window.addEventListener('scroll', onActivity, { passive: true });
    window.addEventListener('mousemove', onActivity, { passive: true });
    window.addEventListener('keydown', onActivity, { passive: true });

    // Engaged timer: tick every second if tab visible and active in last 60s
    var idleCount = 0;
    setInterval(function(){
      if (document.hidden) return;
      if (idleCount < 60) {
        dwellSec++;
        idleCount++;
      }
    }, 1000);

    function onUserTouch() { idleCount = 0; }
    window.addEventListener('scroll', onUserTouch, { passive: true });
    window.addEventListener('click', onUserTouch, { passive: true });

    function sendLeave() {
      if (sent) return;
      sent = true;
      var d = Math.max(maxDepth, calcDepth());
      var scrollPayload = JSON.stringify({ path: path, depth: d, dwell: dwellSec });
      if (navigator.sendBeacon) {
        navigator.sendBeacon(trackUrl, new Blob([scrollPayload], { type: 'application/json' }));
      } else {
        fetch(trackUrl, { method: 'POST', body: scrollPayload, headers: { 'Content-Type': 'application/json' }, keepalive: true }).catch(function(){});
      }
    }

    document.addEventListener('visibilitychange', function(){
      if (document.visibilityState === 'hidden') sendLeave();
    });
    window.addEventListener('pagehide', sendLeave);
  } catch(e) {}
})();
</script>
    <?php
}

