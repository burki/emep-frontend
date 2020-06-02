Database of Modern Exhibitions (DoME) - Frontend
============================================================

Installation
------------
MySQL

- mysqladmin -u root -p create emep

If you want to create a dedicated user for this table
- mysql -u root -p emep
- GRANT ALL ON `emep`.* TO 'emep'@'localhost' IDENTIFIED BY 'mysql-password';

Import dump
- mysql -u emep -p < emep.latest.sql

Install dependencies as specified in composer.json
- composer install

Adjust Settings (database user/password and mail-delivery settings)
- cp config/parameters.yaml-dist config/parameters.yaml
- vi config/parameters.yaml


If you get errors due to var not being writable, adjust directory permissions as
described in https://symfony.com/doc/3.4/setup/file_permissions.html
- sudo setfacl -R -m u:www-data:rwX /path/to/var
- sudo setfacl -dR -m u:www-data:rwX /path/to/var

If you get errors due to web/css not being writable, adjust directory permissions as
described in https://symfony.com/doc/3.4/setup/file_permissions.html
- sudo setfacl -R -m u:www-data:rwX /path/to/web/css
- sudo setfacl -dR -m u:www-data:rwX /path/to/web/css

Adjust your web-server configuration to point to web-folder, e.g.
    Alias /data /path/to/web

For nice URLs, enable Apache and copy/adjust .htaccess-dist
- sudo a2enmod rewrite

You can also use the built-in server from PHP
- cd /path/to/web
- php -S localhost:8000

And then navigate to http://localhost:8000/app.php/