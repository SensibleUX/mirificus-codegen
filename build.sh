#! /bin/bash
composer install
phpunit CoreTest tests/Core.php
wget -q --auth-no-challenge --http-user=$3 --http-password=$2 http://jenkins.jimsdevbox.com/job/mirificus/build?token=$1