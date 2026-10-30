<?php
/**
 * KutPod Plugin - KutBlog Auto-Publisher
 * To be installed in KutPod: plugins/KutBlog/kutblog.php
 */

if (!defined('KUTPOD_VERSION')) {
    exit;
}

// ── Hooks ───────────────────────────────────────────────────────────────────
kp_add_action('episode_published', 'kp_kutblog_on_published', 10, 5);
kp_add_action('episode_saved', 'kp_kutblog_on_saved', 10, 2);
kp_add_action('episode_deleted', 'kp_kutblog_on_deleted', 10, 1);

function kp_kutblog_is_configured(): bool {
    $url = kp_setting('kutblog_webhook_url', '');
    return !empty($url);
}

function kp_kutblog_send_webhook(string $event, array $episodeData): void {
    if (!kp_kutblog_is_configured()) return;

    $url = kp_setting('kutblog_webhook_url', '');
    $secret = kp_setting('kutblog_secret', '');

    $payload = json_encode([
        'event'     => $event,
        'timestamp' => time(),
        'episode'   => $episodeData
    ], JSON_UNESCAPED_UNICODE);

    $headers = [
        'Content-Type: application/json',
        'User-Agent: KutPod-KutBlog-Publisher/1.0'
    ];
    if ($secret !== '') {
        $headers[] = 'X-KutPod-Secret: ' . $secret;
        $headers[] = 'Authorization: Bearer ' . $secret;
    }

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    @file_get_contents($url, false, $ctx);
}

function kp_kutblog_on_published(int $episodeId, int $podcastId, string $title, string $description, string $episodeUrl): void {
    $ep = kp_one("SELECT * FROM episodes WHERE id = ?", [$episodeId]);
    $p = kp_one("SELECT * FROM podcasts WHERE id = ?", [$podcastId]);
    if (!$ep || !$p) return;

    $base = kp_base_url();
    $rawCover = $ep['cover'] ?: ($p['cover'] ?? '');
    $fullCover = $rawCover;
    if ($fullCover && !preg_match('#^https?://#i', $fullCover)) {
        $fullCover = $base . (strpos($fullCover, '/') === 0 ? '' : '/') . $fullCover;
    }

    $data = [
        'id'            => (int)$ep['id'],
        'guid'          => $ep['guid'],
        'podcast_id'    => (int)$p['id'],
        'podcast_slug'  => $p['slug'],
        'podcast_title' => $p['title'],
        'slug'          => $ep['slug'],
        'title'         => $ep['title'],
        'season'        => (int)($ep['season'] ?? 1),
        'number'        => (int)($ep['number'] ?? 0),
        'notes_md'      => $ep['notes_md'] ?? $description,
        'audio_url'     => $ep['audio_url'] ?? '',
        'cover'         => $fullCover,
        'instance_url'  => $base,
        'duration_secs' => (int)($ep['duration_secs'] ?? 0),
        'published_at'  => $ep['published_at'] ?: date('c'),
        'downloads'     => (int)($ep['downloads'] ?? 0),
        'transcript_url'=> $ep['transcript_url'] ?? '',
        'chapters_url'  => $ep['chapters_url'] ?? ''
    ];

    kp_kutblog_send_webhook('episode.published', $data);
}

function kp_kutblog_on_saved(int $episodeId, array $postData): void {
    $ep = kp_one("SELECT * FROM episodes WHERE id = ?", [$episodeId]);
    if (!$ep || $ep['status'] !== 'published') return;
    $p = kp_one("SELECT * FROM podcasts WHERE id = ?", [$ep['podcast_id']]);
    if (!$p) return;

    $base = kp_base_url();
    $rawCover = $ep['cover'] ?: ($p['cover'] ?? '');
    $fullCover = $rawCover;
    if ($fullCover && !preg_match('#^https?://#i', $fullCover)) {
        $fullCover = $base . (strpos($fullCover, '/') === 0 ? '' : '/') . $fullCover;
    }

    $data = [
        'id'            => (int)$ep['id'],
        'guid'          => $ep['guid'],
        'podcast_id'    => (int)$p['id'],
        'podcast_slug'  => $p['slug'],
        'podcast_title' => $p['title'],
        'slug'          => $ep['slug'],
        'title'         => $ep['title'],
        'season'        => (int)($ep['season'] ?? 1),
        'number'        => (int)($ep['number'] ?? 0),
        'notes_md'      => $ep['notes_md'] ?? '',
        'audio_url'     => $ep['audio_url'] ?? '',
        'cover'         => $fullCover,
        'instance_url'  => $base,
        'duration_secs' => (int)($ep['duration_secs'] ?? 0),
        'published_at'  => $ep['published_at'] ?: date('c'),
        'downloads'     => (int)($ep['downloads'] ?? 0),
        'transcript_url'=> $ep['transcript_url'] ?? '',
        'chapters_url'  => $ep['chapters_url'] ?? ''
    ];

    kp_kutblog_send_webhook('episode.updated', $data);
}

function kp_kutblog_on_deleted(array $ep): void {
    if (empty($ep['slug'])) return;
    kp_kutblog_send_webhook('episode.deleted', [
        'id'   => $ep['id'] ?? 0,
        'slug' => $ep['slug']
    ]);
}
