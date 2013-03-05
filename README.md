# Google Addressbook Plugin for Roundcube

This plugin lets you sync your Google Addressbook in readonly mode with Roundcube.

## Requirements
* Roundcube 0.9-beta [http://roundcube.net/download]
* PHP 5.2.x or higher [http://www.php.net/]
* PHP Curl extension [http://www.php.net/manual/en/intro.curl.php]
* PHP JSON extension [http://php.net/manual/en/book.json.php]

## Installation
> cd /path/to/roundcube/plugins/  
> git clone https://github.com/stwa/google-addressbook google_addressbook  
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
This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see www.gnu.org/licenses/.

