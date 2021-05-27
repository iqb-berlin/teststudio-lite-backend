run:
	cd docker && docker-compose up

run-detached:
	cd docker && docker-compose up -d

stop:
	cd docker && docker-compose stop

down:
	cd docker && docker-compose down

init:
	wget https://raw.githubusercontent.com/iqb-berlin/iqb-scripts/master/new_version.py

build:
	cd docker && docker-compose build

test:
	echo "There are not tests yet... :-("

data-source:
	scripts/make_data_source.sh $(DB_HOST) $(DB_PORT) $(DB_SCHEMA) $(DB_USER) $(DB_PASSWORD)

new-version-major:
	scripts/new_version.py major

new-version-minor:
	scripts/new_version.py minor

new-version-patch:
	scripts/new_version.py patch
