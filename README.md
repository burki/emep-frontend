Database of Modern Exhibitions (DoME) - Frontend
============================================================

License
-------
    Code for the Front-end of https://exhibitions.univie.ac.at/

    (C) 2017-2025 Department of Art History, University of Vienna
        Daniel Burckhardt


    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    A public copy of the site must not give the impression of being
    operated by the University of Vienna.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

Third Party Code
----------------
This projects builds on numerous third-party projects under a variety of
Open Source Licenses. Please check `composer.json` for these dependencies.

Installation
------------
MySQL

- mysqladmin -u root -p create emep

If you want to create a dedicated user for this table
- mysql -u root -p emep
- CREATE USER 'emep'@'localhost' IDENTIFIED BY 'mysql-password';
- GRANT ALL ON `emep`.* TO 'emep'@'localhost';

Import dump
- mysql -u emep -p < emep.latest.sql

Install dependencies as specified in composer.json
- composer install

Adjust Settings (database user/password and mail-delivery settings)
- cp config/parameters.yaml-dist config/parameters.yaml
- vi config/parameters.yaml


If you get errors due to var not being writable, adjust directory permissions as
described in https://symfony.com/doc/5.x/setup/file_permissions.html
- sudo setfacl -R -m u:www-data:rwX /path/to/var
- sudo setfacl -dR -m u:www-data:rwX /path/to/var

If you get errors due to web/css not being writable, adjust directory permissions as
described in https://symfony.com/doc/5.x/setup/file_permissions.html
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
