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

data-source:
	scripts/make_data_source.sh

new-version-major:
	scripts/new_version.py major

new-version-minor:
	scripts/new_version.py minor

new-version-patch:
	scripts/new_version.py patch
