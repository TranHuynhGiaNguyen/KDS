<?php
require_once __DIR__ . '/includes/auth.php';
require_role(WAREHOUSE_ALLOWED_ROLES);

redirect_to('inventory/daily_stock.php');
