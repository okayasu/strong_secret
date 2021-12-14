# strong_secret

strong_secret is a web interface to manage secret keys for strongSwan. only support sqlite3 database.

## Require
- strongSwan with vici and sql plugin
- web server with php extension or php-fpm

## Setup
### ready sqlite3 database file
- get schema from [https://wiki.strongswan.org/projects/strongswan/wiki/SQLite] ~
[https://wiki.strongswan.org/projects/strongswan/repository/entry/src/pool/sqlite.sql]
- creaet initial database file
```
$sqlite3 db.sqlite3 < sqlite.sql
```
### configure strongswan.conf to use sqlite3 file.
strongswan.conf
```
charon  {
	plugins {
		sql {
			database = sqlite:///[path to database file]/db.sqlite3
		}
	}
}
```
### modify templaet config.php file