Curl-Wigilabs-M3
===============

Library for M3

install apt-get install php7.0-xml

For update 
rm -rf vendor/jsngonzalez
composer update jsngonzalez/curl-wigi-m3 

curl -H "Content-Type: application/json" -X POST -d '{"data":{"nombreUsuario":"sitauc@hotmail.com","clave":"xxxxx"}}' https://slim-zironet.c9users.io/login/


vendor/bin/phpunit --bootstrap vendor/autoload.php Test.php
