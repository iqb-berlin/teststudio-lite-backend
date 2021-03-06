#!/usr/bin/env bash

# db connection
DBConnectionDataFile=/var/www/html/vo_code/DBConnectionData.json

## delete sample config file
checkSum=$(cksum $DBConnectionDataFile | awk -F" " '{print $1}')
if [[ "$checkSum" -eq  "1486529974" ]]; then
  echo "delete sample config file"
  rm $DBConnectionDataFile
fi

## create new one
if [[ ! -f "$DBConnectionDataFile" ]]; then
  echo "create new config file"
  touch $DBConnectionDataFile
  echo "{\"type\": \"pgsql\", \"host\": \"${POSTGRES_HOST}\", \"port\": \"${POSTGRES_PORT}\", \"dbname\": \"${POSTGRES_DB}\", \"user\": \"${POSTGRES_USER}\", \"password\": \"${POSTGRES_PASSWORD}\"}" > $DBConnectionDataFile
fi

# add super user and workspace
cd /var/www/html/create || exit
php init.cli.php --user_name=$SUPERUSER_NAME --user_password=$SUPERUSER_PASSWORD --workspace_name=$WORKSPACE_NAME


# keep container open
apache2-foreground
