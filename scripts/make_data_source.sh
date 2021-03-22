#!/bin/bash
set -e

DATASOURCE=../vo_code/DataSource.json

POSTGRES_HOST="localhost"
POSTGRES_PORT="5432"
POSTGRES_DB="veraonline_itemdb"
POSTGRES_USER="root"
POSTGRES_PASSWORD="psqllocal"

touch $DATASOURCE
echo -e "{\n\t\"type\": \"pgsql\",\n\t\"host\": \"$POSTGRES_HOST\",\n\t\"port\": \"$POSTGRES_PORT\",\n\t\"dbname\": \"$POSTGRES_DB\",\n\t\"user\": \"$POSTGRES_USER\",\n\t\"password\": \"$POSTGRES_PASSWORD\"\n}" > $DATASOURCE
