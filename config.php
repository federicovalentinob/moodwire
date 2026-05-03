<?php

// Load .env file
$env = parse_ini_file(__DIR__ . '/.env');
foreach ($env as $k => $v) define($k, $v);
