run:
	cd docker && docker-compose up

run-detached:
	cd docker && docker-compose up -d

stop:
	cd docker && docker-compose stop

down:
	cd docker && docker-compose down

build:
	cd docker && docker-compose build

docker-release:
	docker/scripts/release.sh
