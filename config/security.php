<?php

return [
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
    // Operational acknowledgement after rotation of credentials exposed in legacy Git history.
    'secrets_rotated' => (bool) env('DEPLOY_SECRETS_ROTATED', false),
];
