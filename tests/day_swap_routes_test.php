<?php
$source = file_get_contents(__DIR__ . '/../api/day_swap_api.php');
foreach (['employees','holidays','my_requests','pending'] as $action) {
    if (!str_contains($source, '$action' . " === '" . $action . "'")) throw new RuntimeException('Missing day-swap route: ' . $action);
}
echo "PASS day-swap read routes retained
";
