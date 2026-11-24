#!/bin/sh

set -eu

TEST_DB="${POSTGRES_TEST_DB:-cmms_test}"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE "$TEST_DB" OWNER "$POSTGRES_USER";
EOSQL
