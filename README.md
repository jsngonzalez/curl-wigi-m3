Curl-Wigilabs-M3
===============

Library for M3

install apt-get install php7.0-xml

For update 
rm -rf vendor/jsngonzalez
composer update jsngonzalez/curl-wigi-m3 

curl -H "Content-Type: application/json" -X POST -d '{"data":{"nombreUsuario":"sitauc@hotmail.com","clave":"xxxxx"}}' https://slim-zironet.c9users.io/login/


vendor/bin/phpunit --bootstrap vendor/autoload.php Test.php


sudo ab -t 10 -c 10 https://miclaroapp.com.co/api/v1/core/web/banners.json?tab=1

sudo ab -t 10 -c 10 -H "Content-Type: application/json" -X POST -d '{"data":{"nombreUsuario":"kriszthian20@gmail.com","clave":"Cr12345@"}}' https://slim-zironet.c9users.io/M3/General/loginUsuario/


ab -p pos_data.txt -T application/json -H 'Content-Type: application/json' -c 10 -n 2000 https://slim-zironet.c9users.io/M3/General/loginUsuario/

ab -p pos_data.txt -T application/json -H 'Content-Type: application/json' -c 10 -n 2000 https://www.miclaroapp.com.co/M3/login/Servicio1/



ab -p pos_data.txt -T application/json -H 'Content-Type: application/json' -c 10 -n 2000 https://miclaroapp.com.co/api/index.php/v1/soap/LoginUsuario.json