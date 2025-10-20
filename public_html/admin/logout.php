<?php
require_once __DIR__ . '/config/session.php';

logoutAdmin();

header('Location: /admin/login.php');
exit;
