<?php

declare(strict_types=1);

define('RSR_TESTING', true);
define('RSR_TEST_KEY', 'file-15-deterministic-test-key-material');

require_once dirname(__DIR__) . '/15-radar-symptom-and-remedy-analysis-foundation/includes/class-rsr-domain.php';
require_once dirname(__DIR__) . '/15-radar-symptom-and-remedy-analysis-foundation/includes/class-rsr-pii-scanner.php';
require_once dirname(__DIR__) . '/15-radar-symptom-and-remedy-analysis-foundation/includes/class-rsr-crypto.php';
