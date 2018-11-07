# ApiHouse - Black Rock Ranger Secret Clubhouse API Backend

## Prerequisites

You will need the following things properly installed on your computer.
(See the neohouse repository README file for instructions on how to bring up the frontend)

* [Git](https://git-scm.com/)
* [PHP >= 7.1.3](https://php.net)
* [MySQL >= 5.6](https://www.mysql.com/downloads/)

## Installation

* `git clone https://github.com/burningmantech/ranger-clubhouse-api` this repository
* `cd ranger-clubhouse-api`
* `composer install`
* Copy .env.clubhouse to .env and set the configuration appropriately
* `php artisan migrate` (needed to have database auditing)

## Running / Development

* `php -S localhost:8000 -t public`
* See the ranger-clubhouse-web README for instructions on how to start the frontend

## Random Notes

- autoloaded packages and throwning PHP exception may require an explicit path
  e.g., new Google_Client -> new \Google_Client
  throw new \InvalidArgumentException("blah")

## What happened to the Classic Clubhouse config() function?

Laravel also uses config() for configuration variables, however, the values
are in namespaces. In Classic Clubhouse, to read PhotoSource the call would
be `config("PhotoSource")` in ApiHouse the call is config('clubhouse.PhotoSource')

The defaults live in config/clubhouse.php
