simple-php-site-monitor
=======================

The main feature that it's so simple, that could be run almost everywhere.

Usage
=======================

Add to cron:  
`*/5 * * * *  /usr/bin/php /path/to/simple-php-site-monitor/monitor.php mail@domain.com, mail2@domain.com http://webhook1.com/do, http://webhook1.com/do2 >> /path/to/simple-php-site-monitor/cron.log`

Params:
 - (0) /path/to/monitor.php
 - (1) (optional) email (comma-separated list)
 - (2) (optional) webhook URL (comma-separated list)
