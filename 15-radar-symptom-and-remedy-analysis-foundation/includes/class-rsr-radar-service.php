<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

require_once __DIR__ . '/trait-rsr-radar-public.php';
require_once __DIR__ . '/trait-rsr-radar-governance.php';
require_once __DIR__ . '/trait-rsr-radar-retrieval.php';

final class RSR_Radar_Service
{
    use RSR_Radar_Public_Trait;
    use RSR_Radar_Governance_Trait;
    use RSR_Radar_Retrieval_Trait;
}
