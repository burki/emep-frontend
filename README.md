Exhibitions of Modern European Painting 1905-1915 - Frontend
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


If you get errors due to var not being writable, adjust directory dermissions as
described in https://symfony.com/doc/3.3/setup/file_permissions.html
- sudo setfacl -R -m u:www-data:rwX /path/to/var
- sudo setfacl -dR -m u:www-data:rwX /path/to/var

Adjust your web-server configuration to point to web-folder, e.g.
    Alias /data /path/to/web

For nice URLs, enable Apache and copy/adjust .htaccess-dist
- sudo a2enmod rewrite
