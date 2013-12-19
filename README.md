simple-php-site-monitor
=======================

The main feature that it's so simple, that could be run almost everywhere.

Usage
=======================

Add to cron:
`*/5 * * * *  /usr/bin/php /root/simple-php-site-monitor/monitor.php mail@domain.com 25bae55e-8ced-d304-3d76-40d7ebc1f965 79291234567 >> /root/simple-php-site-monitor/cron.log`

Params:
 - (0) /path/to/monitor.php
 - (1) (optional) email
 - (2) (optional) sms.ru api key
 - (3) (optional) sms.ru connected phone number
