#! /bin/sh
DB_USER=${DB_USER:-root}

mysql -u $DB_USER -e "DROP DATABASE IF EXISTS afyadata_test;"
mysql -u $DB_USER -e "CREATE DATABASE afyadata_test;"

CI_ENV=testing php index.php migration latest

cd application/tests/

phpunit --coverage-text

eval "cd ../..; exit $?"
