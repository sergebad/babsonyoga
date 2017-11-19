<?php

// print_r(exec("php artisan queue:work --tries=2"));
// print_r(exec("php artisan queue:listen"));
echo date_default_timezone_set('America/New_York');
echo "test: " . date_default_timezone_get();
phpinfo();
?>
