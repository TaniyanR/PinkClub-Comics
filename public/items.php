<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

header('Location: ' . public_url('catalog.php?type=comic'), true, 301);
exit;
