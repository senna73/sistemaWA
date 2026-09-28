<?php

return [
    'accounting_email' => env('RH_ACCOUNTING_EMAIL'),
    'dossier_retention_years' => (int) env('RH_DOSSIER_RETENTION_YEARS', 2),
    // Rotas de contratação seguem ativas; em produção a entrada some da tela do RH.
    'show_hiring' => filter_var(
        env('RH_SHOW_HIRING', env('APP_ENV', 'production') !== 'production'),
        FILTER_VALIDATE_BOOLEAN
    ),
];
