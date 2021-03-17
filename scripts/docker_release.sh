set -e

docker build -t iqbberlin/teststudio-lite-backend:latest -f docker/Dockerfile .
docker push iqbberlin/teststudio-lite-backend:latest;

# any parameter causes a tagged version to be released
# TODO create version file and implment parse logic
# if [ $# -gt 0 ]
# then
#   PACKAGE_VERSION=$(cat package.json \
#   | grep version \
#   | head -1 \
#   | awk -F: '{ print $2 }' \
#   | sed 's/[",]//g')
#
#   echo "Releasing Tagged image $PACKAGE_VERSION"
#   docker tag iqbberlin/teststudio-lite-backend:latest iqbberlin/teststudio-lite-backend:$PACKAGE_VERSION;
#   docker push iqbberlin/teststudio-lite-backend:$PACKAGE_VERSION;
# fi
