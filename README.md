# Google Addressbook Plugin for Roundcube

This plugin lets you sync your Google Addressbook in readonly mode with Roundcube.

## Requirements
* Roundcube 0.9-beta [http://roundcube.net/download]
* PHP 5.2.x or higher [http://www.php.net/]
* PHP Curl extension [http://www.php.net/manual/en/intro.curl.php]
* PHP JSON extension [http://php.net/manual/en/book.json.php]

## Installation
> cd /path/to/roundcube/plugins/
> git clone git@git.cannycode.de:opensource/google-addressbook.git google_addressbook
> cd google_addressbook/
> curl "http://google-api-php-client.googlecode.com/files/google-api-php-client-0.6.0.tar.gz" -O
> tar -xvf  google-api-php-client-0.6.0.tar.gz
> echo "$rcmail_config['plugins'][] = 'google_addressbook';" >> ../../config/main.inc.php

*Do not forget to create the database table using the SQL from SQL/*

## Todo
* Login autosync too slow while waiting for contacts to load
* Add possibility to revoke tokens

## Contact
Author: Stefan Wagner (stw@cannycode.de)

Bug reports through github:
https://github.com/stwa/google-addressbook/issues

## License
No license information yet.

