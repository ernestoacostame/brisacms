<?php
// Fediverse plugin for BrisaCMS
// This file initializes the ActivityPub system when loaded.

require_once __DIR__ . '/activitypub.php';

// Initialize schema on load
ap_ensure_schema();
