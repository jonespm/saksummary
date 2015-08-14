For the Sakai project

Provides a more detailled summary of subversion checkins that links up jira and subversion changes for a specific source directory.

Installation

Minimally requires php5 and php5-svn to run (on Ubuntu). Also improved with memcached and php5-memcached.

This set of instructions for installing php on Ubuntu are good
https://www.digitalocean.com/community/tutorials/how-to-install-linux-nginx-mysql-php-lemp-stack-on-ubuntu-12-04

Also make sure to restart php5-fpm if you install any modules afterward

You may also need to increase the max_execution_time in vim `/etc/php5/fpm/php.ini`

`max_execution_time = 120`

And in Nginx `/etc/nginx/sites-enabled/default`

```
location ~ \.php$ {
. . . 
  fastcgi_read_timeout 120; 
}
```

`service php5-fpm restart`

Rememeber to check the error logs!
