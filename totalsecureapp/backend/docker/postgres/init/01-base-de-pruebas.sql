-- phpunit.xml corre contra la base `coredt360_testing`, que no existia en un
-- clon nuevo: la suite entera fallaba y parecia codigo roto. Postgres ejecuta
-- esto solo al inicializar un volumen vacio, conectado como POSTGRES_USER, asi
-- que el dueño sale de DB_USERNAME sin repetirlo aqui.
CREATE DATABASE coredt360_testing;
