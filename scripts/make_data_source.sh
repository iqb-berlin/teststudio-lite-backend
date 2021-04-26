#!/bin/bash
set -e

DATA_SOURCE=./vo_code/DataSource.json

touch $DATA_SOURCE
echo -e "{
  \"type\": \"pgsql\",
  \"host\": \"${DB_HOST:-localhost}\",
  \"port\": \"${DB_PORT:-5432}\",
  \"dbname\": \"${DB_SCHEMA:-verona_teststudio}\",
  \"user\": \"${DB_USER:-root}\",
  \"password\": \"${DB_PASSWORD:-psqllocal}\"
}" > $DATA_SOURCE
