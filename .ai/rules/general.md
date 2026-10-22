---
paths:
  - '**'
---

# General

## Run and demo on the sample database dump
Whenever the app is run, migrated, tested by hand or screenshotted, use the sample data in database/backup/database.sql.gz (MySQL dump of the real chart of accounts and journals): import it into a local MySQL/MariaDB database, then run `php artisan migrate` on top of it. Do not use an empty or hand-built database for demos.
