# File 15 tests

`static-regression.php` provides repository-level regression gates that do not require a WordPress runtime. GitHub Actions also lints every PHP file on PHP 7.4 and PHP 8.3 and checks JavaScript syntax.

These gates do not replace WordPress staging tests. Role behavior, REST requests, database upgrades, page-collision handling, privacy headers, File 06/07/09/20 integration, responsive rendering, and rollback still require the staging acceptance checklist.
