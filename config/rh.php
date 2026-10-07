<?php

return [
    'accounting_email' => env('RH_ACCOUNTING_EMAIL'),
    'agenda_assignee_email' => env('RH_AGENDA_ASSIGNEE_EMAIL'),
    'dossier_retention_years' => (int) env('RH_DOSSIER_RETENTION_YEARS', 2),
];
