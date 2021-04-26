[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)](https://opensource.org/licenses/MIT)
![GitHub tag (latest SemVer)](https://img.shields.io/github/v/tag/iqb-berlin/teststudio-lite-backend?style=flat-square)

# Teststudio-Lite Backend

This is the backend for the IQB-Teststudio-Lite application. You can find the frontend for this application [here](https://github.com/iqb-berlin/teststudio-lite-frontend).

You can find a complete setup with front- and backend [here](https://github.com/iqb-berlin/teststudio-lite-setup).

## Bug Reports

Please file bug reports etc. here [here](https://github.com/iqb-berlin/teststudio-lite-backend/issues).

## Installation

### With Docker (recommended)

All necessary commands for running the application can be found in the Makefile on the root directory. It is recommended to use those Makefile-targets. If you don't want to use `make`, you may manually execute the commands in this file.

A production-ready container can be downloaded [here](https://hub.docker.com/repository/docker/iqbberlin/teststudio-lite-backend).

###### Start and Stop the server
This setup is for local development. You can build and start the image(s) with the following commands.
```
make run
make stop
```

To run docker in the background you may use
```
make run-detached
```

### With Installation Script on Webserver

#### Prerequisites

* Apache2 (other webservers possible, but untested) with
  * mod_rewrite extension
  * header extension
* PHP (tested with 7.4)
* PostgreSQL (tested with 9.3)

#### Installation Steps

*This assumes apache2 as server with a common setup. Maybe you have to adjust paths etc.
to your server's specific setup.*

- Clone this repository:

- Make sure, Apache2 accepts .htacess-files (AllowOverride All-setting in your Vhost-config) and required
extensions are present.

- Make sure, config and data files are not exposed to the outside*. If the .htacess-files is accepted by Apache2
correctly this would be the case.

- create data and tmp directories:
```
mkdir verona-modules
mkdir config
mkdir vo_tmp
```

- Ensure that PHP has write access to those
```
sudo chown -R www-data:www-data verona-modules
sudo chown -R www-data:www-data config
sudo chown -R www-data:www-data vo_tmp
```

- Install PostgreSQL and setup basic db information like db host name, db port, db user, and db user password (cited below as `{my_host}`, `{my_port}`, `
  {my_db_schema}`, `{my_db_user}`, and `{my_db_password}`).
- Import 'IQB Teststudio' SQL dump into your database schema (replace `{my_db_user}` and `{my_db_schema}` by your PostgreSQL basic configuration):
```
psql -U {my_db_user} {my_db_schema} < create/IQB_Teststudio_Database_CreatePG.sql
```

- Create a db connection file for the php backend with `make data-source [DB_HOST={my_host}] [DB_PORT={my_port}] [DB_SCHEMA={my_db_schema}] [DB_USER={my_db_user}] {DB_PASSWORD={my_db_password}}` and replace 
  `{my_host}`, `{my_port}`, `{my_db_schema}`, `{my_db_user}`, and `{my_db_password}` with your own db configuration data or `make data-source` with default 
  connection information, which is equivalent to:
```
make data-source DB_HOST=localhost DB_PORT=5432 DB_SCHEMA=verona_teststudio DB_USER=root DB_PASSWORD=psqllocal
```

- Run init-script to create first super-user and workspace (replace `{user_name}`, `user_password}`
and `{workspace_name}` by whatever you want as credentials.)
```
cd create
php init.cli.php --user_name={user_name} --user_password={user_password} --workspace_name={workspace_name}
```

- You still need the [frontend](https://github.com/iqb-berlin/teststudio-lite-frontend) and
some [unit-players and -editors](https://github.com/iqb-berlin/verona-player-dan) to get
started. You can import them with the frontend.


## Development

To create a new tag in git and tagged docker images on Docker Hub, you the Makefile targets:
```
new-version-major/minor/patch
```
