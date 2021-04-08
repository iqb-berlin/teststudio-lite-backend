#!/bin/bash
set -e

DATA_SOURCE=./vo_code/DataSource.json

#DB_HOST=${POSTGRES_HOST: -localhost}
#DB_PORT=${POSTGRES_PORT: -5432}
#DB_SCHEMA=${POSTGRES_DB: -veraonline_itemdb}
#DB_USER=${POSTGRES_USER: -root}
#DB_PASSWORD=${POSTGRES_PASSWORD: -psqllocal}

touch $DATA_SOURCE
echo -e "{
  \"type\": \"pgsql\",
  \"host\": \"${POSTGRES_HOST:-localhost}\",
  \"port\": \"${POSTGRES_PORT:-5432}\",
  \"dbname\": \"${POSTGRES_DB:-veraonline_itemdb}\",
  \"user\": \"${POSTGRES_USER:-root}\",
  \"password\": \"${POSTGRES_PASSWORD:-psqllocal}\"
}" > $DATA_SOURCE
