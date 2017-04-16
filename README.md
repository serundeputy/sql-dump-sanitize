Sanitize and Backup the Database
----

The purpose of this script is to sanitize and backup the database of your Backdrop CMS website.  You can then download the backup and use for local development.

Requirements
---
* Linux server
* Backdrop CMS website
* PHP CLI

Installation and Configuration
---
Clone this directory down to your filesystem. Perhaps to your home directory.
  * `git clone git@github.com:serundeputy/sql-dump-sanitize.git`
  * `cd sql-dump-sanitize`

Copy the `config.ini.example` file to `config.ini`
  * `cp config.ini.exaple config.ini`
  * Set the config variables in `config.ini` to values appropriate for your server and Backdrop database.

Usage
---
#### One time use.
* `php path/to/sql-dump-sanitize.php`
  * This will create a backup of the database written to the `BACKUP_DESTINATION` specified in `config.ini`.
* Options
  * `--quiet` (`-q` alias) suppress standard output (good for running as cron job)
  * `--sanitize` (`-s` alias) sanitize user email addresses for the backup. Sanitized backups are placed in `BACKUP_DESTINATION`/sanitized subdirectory.
  * `--rollover` (`-r` alias) remove stale backups where stale is defined by the `NUM_KEEP` config variable.

#### To run periodically on cron.
* Set a server cron task to run the script on a schedule (daily, weekly, monthly or any and/or combination). For example a weekly on 8.05am Saturday morning; the cron job would look like this:

```bash
# 1. Entry: Minute when the process will be started [0-60]
# 2. Entry: Hour when the process will be started [0-23]
# 3. Entry: Day of the month when the process will be started [1-28/29/30/31]
# 4. Entry: Month of the year when the process will be started [1-12]
# 5. Entry: Weekday when the process will be started [0-6] [0 is Sunday]
5 8 * * 6 {/absolute/path/to/php} {/path/to/script/}sql-dump-sanitize.php --quiet
```

Happy backups ;)
