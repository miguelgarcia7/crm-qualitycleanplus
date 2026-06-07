<?php

/*
|--------------------------------------------------------------------------
| Surface domains (ADR-0024)
|--------------------------------------------------------------------------
| Two domains, three surfaces, in one codebase. Read from env so local Herd
| `.test` hosts and production `.com` hosts both work without code changes.
|
|   main     → marketing ("/") + back office ("/admin")
|   qcminute → QC Minute ("/") + tablet clock-in ("/device/*")
|
| Local (Herd): DOMAIN_MAIN=qcpminute.test, DOMAIN_QCMINUTE=qcminute.test
*/

return [
    'main' => env('DOMAIN_MAIN', 'qualitycleanplus.com'),
    'qcminute' => env('DOMAIN_QCMINUTE', 'qcpstaffing.com'),
];
