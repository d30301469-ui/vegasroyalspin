<?php

declare(strict_types=1);

// Legacy direct MegaPayz page is intentionally disabled.
// All payment operations must go through backend-owned /api/v2 endpoints.
header('Location: /profile/deposit-withdraw?openDepositPanel=1', true, 302);
exit;