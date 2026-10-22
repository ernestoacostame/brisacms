<?php
// Fediverse plugin for BrisaCMS
// This file initializes the ActivityPub system when loaded.

if (!in_array('sqlite', PDO::getAvailableDrivers())) {
    // SQLite PDO driver not enabled in PHP environment
    return;
}

require_once __DIR__ . '/activitypub.php';

// Initialize schema on load
ap_ensure_schema();
