<?php

return [
    'accounting_email' => env('RH_ACCOUNTING_EMAIL'),
    'dossier_retention_years' => (int) env('RH_DOSSIER_RETENTION_YEARS', 2),
];
