<?php

return [
    'openssl_config' => env('GYM_OPENSSL_CONFIG', is_file(dirname(PHP_BINARY).'/extras/ssl/openssl.cnf') ? dirname(PHP_BINARY).'/extras/ssl/openssl.cnf' : null),
    'database_roles' => ['gym_web_reader', 'gym_web_operator'],
];
