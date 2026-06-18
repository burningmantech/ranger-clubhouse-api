# Black Rock Ranger Secret Clubhouse API Service

[![Build Status](https://github.com/burningmantech/ranger-clubhouse-api/actions/workflows/cicd.yml/badge.svg)](https://github.com/burningmantech/ranger-clubhouse-api/actions/workflows/cicd.yml)

The backend API service for the Black Rock Ranger Secret Clubhouse. It is a
[Laravel 12](https://laravel.com/) application (PHP, [Octane](https://laravel.com/docs/octane),
[Sanctum](https://laravel.com/docs/sanctum)) serving a JSON API. The frontend lives in
[ranger-clubhouse-web](https://github.com/burningmantech/ranger-clubhouse-web).

## Prerequisites

The authoritative versions are in `composer.json` and the `Dockerfile`; the list below may lag behind them.

* [Git](https://git-scm.com/)
* [PHP >= 8.4](https://php.net/) with the extensions listed in `composer.json`
* [Composer >= 2.8](https://getcomposer.org/)
* [MariaDB >= 10.5](https://mariadb.org/)

## Installation

* `git clone https://github.com/burningmantech/ranger-clubhouse-api` this repository
* `cd ranger-clubhouse-api`
* `composer install`
* Copy `.env.clubhouse` to `.env` and set the configuration appropriately
* Create the MySQL/MariaDB database `rangers`. The account `rangers` will need appropriate
  permissions to access it.
* Load the data:
  * Normal path: ask the Ranger Tech Team for a redacted database and import it.
  * From scratch: `php artisan migrate:fresh` loads an empty schema. Note this creates
    no login account, so the redacted database is the usual way to get a working dev setup.

## Running / Development

* `php artisan serve` starts the backend on `http://127.0.0.1:8000`.
* See the [ranger-clubhouse-web](https://github.com/burningmantech/ranger-clubhouse-web) `README`
  for how to start the frontend.

Hitting the server in a browser returns the plain-text message
`Ranger Clubhouse API Server` — this service is an API, so there is no web UI to see here.

## Testing

* `php artisan test --compact` runs the feature test suite (uses the `.env.testing` config).
* `./bin/test_unit` runs the suite against a throwaway MariaDB Docker container.

## Docker

The deployed artifact is a Docker image built from the `Dockerfile`. Helper scripts in `bin/`
wrap the common commands (they set `DOCKER_BUILDKIT=1`, which the Dockerfile requires).

* `./bin/build` — build the image, tagged `ranger-clubhouse-api:dev`.
* `./bin/run` — run the image, publishing container port 80 on host port 8000. It reads
  `.env.docker` if present, otherwise `.env`. Logs go to stdout; Ctrl-C stops it.
* `./bin/shell` — open an `ash` shell in the running container (`docker exec`).

If you change code and want it reflected in the container, rebuild (`./bin/build`) and
re-run (`./bin/run`).
