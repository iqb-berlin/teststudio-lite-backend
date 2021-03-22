#!/usr/bin/env bash

# Data Source Configuration File
DataSource=/var/www/html/vo_code/DataSource.json

## create new Data Source Configuration File if none exists
if [[ ! -f "$DataSource" ]]; then
  echo "create new data source"
  touch $DataSource
  echo -e "
{
  \"type\": \"pgsql\",
  \"host\": \"${POSTGRES_HOST}\",
  \"port\": \"${POSTGRES_PORT}\",
  \"dbname\": \"${POSTGRES_DB}\",
  \"user\": \"${POSTGRES_USER}\",
  \"password\": \"${POSTGRES_PASSWORD}\"
}" > $DataSource

## customize existing Data Source Configuration File
else
  sed -i 's/\"host\":.*/\"host\": \"'${POSTGRES_HOST}'\",/g' $DataSource
  sed -i 's/\"port\":.*/\"port\": \"'${POSTGRES_PORT}'\",/g' $DataSource
  sed -i 's/\"dbname\":.*/\"dbname\": \"'${POSTGRES_DB}'\",/g' $DataSource
  sed -i 's/\"user\":.*/\"user\": \"'${POSTGRES_USER}'\",/g' $DataSource
  sed -i 's/\"password\":.*/\"password\": \"'${POSTGRES_PASSWORD}'\"/g' $DataSource
fi

# keep container open
apache2-foreground
