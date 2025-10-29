<?php
// Front controller for the mini API. In cPanel, point your domain or subdomain to /public.
// Routes exposed: /api/health, /api/payments/verify-receipt, /api/payments/deposit-by-receipt, /api/payments/withdraw
require_once __DIR__ . '/../routes/payments.php';
