<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
logout();
header('Location: ' . base_url() . '/admin/login.php');
exit;
