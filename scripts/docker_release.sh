#!/bin/bash

set -e

docker build -t iqbberlin/teststudio-lite-backend:latest -f docker/Dockerfile .
docker push iqbberlin/teststudio-lite-backend:latest;

if [[ $1 == "-t" ]] && [[ $2 != "" ]]
  then
    echo "Releasing Tagged image $2"
    docker tag iqbberlin/teststudio-lite-backend:latest iqbberlin/teststudio-lite-backend:$2;
    docker push iqbberlin/teststudio-lite-backend:$2;
  else
    echo "No tag paramater"
fi
