[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)](https://opensource.org/licenses/MIT)

# Teststudio-Lite Backend

This is the backend for the Teststudio-Lite application (formally known as itemdb).

You can find the frontend for this application [here](https://github.com/iqb-berlin/teststudio-lite-frontend).

## Bug Reports

Please file bug reports etc. here [here](https://github.com/iqb-berlin/teststudio-lite-backend/issues).

## Installation

**Warning this application is not in active development currently and therefore employs outdated dependencies. 
We take not any warranties and don't recommend putting your instance on a publicly reachable server.** 

### With Docker (recommended)

You can find Docker files for a complete setup [here](https://github.com/iqb-berlin/testcenter-setup). 


### With Installation Script on Webserver

#### Prerequisites

* Apache2 (other webservers possible, but untested) with
  * mod_rewrite extension
  * header extension
* PHP (tested with 7.3)
* PostgreSQL (tested with 9.3)

#### Installation Steps

*This assumes apache2 as server with a common setup. Maybe you have to adjust paths etc. 
to your server's specific setup.*

- Clone this repository:
```
git clone https://github.com/iqb-berlin/testcenter-iqb-php.git
cd testcenter-iqb-php.git
```

- Make sure, Apache2 accepts .htacess-files (AllowOverride All-setting in your Vhost-config) and required 
extensions are present.

- Make sure, config and data files are not exposed to the outside*. If the .htacess-files is accepted by Apache2 
correctly this would be the case.

- create data and tmp directories:
```
mkdir itemauthoringtools
mkdir itemplayers
mkdir vo_tmp
```

- Ensure that PHP has write access to those
```
sudo chown -R www-data:www-data itemauthoringtools
sudo chown -R www-data:www-data itemplayers
sudo chown -R www-data:www-data vo_tmp
```

- Create a PostgreSQL database.
- Set up database schema (replace `{db_user_name}` and `{database_name}` by your PostgreSQL-credentials)
```
psql -U {db_user_name} {database_name} < create/postgresql.sql
```

- Open `vo_code/DBConnectionData.json` and put in your PostgreSQL-credentials.

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

This project is not under active development currently. Only critical bugfixes will be done.
.
