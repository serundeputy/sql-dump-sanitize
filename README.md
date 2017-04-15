Sanitize and Backup the Database
----

The purpose of this script is to sanitize and backup the database of your Backdrop CMS website.  You can then download the backup and use for local development.

Requirements
---
You need to install Drush and the Backdrop Drush Extension to use this script as it needs to bootstrap Backdrop to do the sanitization.

* Drush: https://github.com/drush-ops/drush
* Backdrop Drush Extension: https://github.com/backdrop-contrib/drush

Usage
---
#### One time use.
* `drush scr --user=1 sql-dump-sanitize.php`

#### To run periodically on cron.
* Set a server cron task to run the script on a schedule (daily, weekly, monthly or any and/or combination). For example a weekly on 8.05am Saturday morning; the cron job would look like this:

```bash
# 1. Entry: Minute when the process will be started [0-60]
# 2. Entry: Hour when the process will be started [0-23]
# 3. Entry: Day of the month when the process will be started [1-28/29/30/31]
# 4. Entry: Month of the year when the process will be started [1-12]
# 5. Entry: Weekday when the process will be started [0-6] [0 is Sunday]
5 8 * * 6 {/absolute/path/to/drush} --user=1 sql-dump-sanitize.php
```

Happy backups ;)
