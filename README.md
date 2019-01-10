# Black Rock Ranger Secret Clubhouse API Service

[![Travis CI](https://travis-ci.com/burningmantech/ranger-clubhouse-api.svg?branch=master)](https://travis-ci.com/burningmantech/ranger-clubhouse-api)

## Prerequisites

You will need the following things properly installed on your computer.

* [Git](https://git-scm.com/)
* [PHP >= 7.1.3](https://php.net/)
* [MySQL >= 5.6](https://www.mysql.com/downloads/)

## Installation

* `git clone https://github.com/burningmantech/ranger-clubhouse-api` this repository
* `cd ranger-clubhouse-api`
* `composer install`
* Copy `.env.clubhouse` to `.env` and set the configuration appropriately
* `php artisan migrate` (needed to have database auditing)

## Running / Development

* `php -S localhost:8000 -t public`
* See the [ranger-clubhouse-web](https://github.com/burningmantech/ranger-clubhouse-web) `README` for instructions on how to start the frontend

## Developing with Docker

If you have Docker on your system, you can use it to do your development.
One advantage to doing so is that you will be using the same dependencies (Linux, PHP, Nginx) and config as the deployed service, and another is that you don't have to install any of these dependencies onto your system to achieve this.

### Build a Docker Image

A Docker image is the built artifact that gets used by Docker to create a container running the application.
Build it thusly:

```console
$ ./bin/build
Sending build context to Docker daemon  3.073MB
Step 1/28 : FROM composer:1.7.3 as composer
...
Successfully built 5ea3a4141689
Successfully tagged ranger-clubhouse-api:dev
```

### Run the application in a Docker container

Having built the application into a Docker image, you can now instantiate it within a Docker container:

```console
$ ./bin/run
2019-01-10 18:34:12,843 INFO Included extra file "/etc/supervisor.d/php-fpm-nginx.ini" during parsing
2019-01-10 18:34:12,843 INFO Set uid to user 0 succeeded
2019-01-10 18:34:12,864 INFO RPC interface 'supervisor' initialized
2019-01-10 18:34:12,864 CRIT Server 'unix_http_server' running without any HTTP authentication checking
2019-01-10 18:34:12,864 INFO supervisord started with pid 1
2019-01-10 18:34:13,868 INFO spawned: 'php-fpm' with pid 9
2019-01-10 18:34:13,870 INFO spawned: 'nginx' with pid 10
2019-01-10 18:34:14,951 INFO success: php-fpm entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
2019-01-10 18:34:14,951 INFO success: nginx entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
```

At this point, you can see that `php-fpm` was started in the container, and the `nginx` server has started.
Take your web browser to `http://localhost:8000` and you should see a plain text message: "Ranger Clubhouse API Server".
Success!
(This application provides an API, so there's no exciting web content to see here.)

The above command runs the server in the foreground and all logs will go to standard output (right there in your terminal).
Closing this window or killing the process will stop the server.

### Shell access to the container

If you feel the need to get a shell into the container running the application, that is doable.
In a separate terminal window, run `./bin/shell`:

```console
$ ./bin/shell
/var/www/application # ps -ef
PID   USER     TIME  COMMAND
    1 root      0:00 {supervisord} /usr/bin/python2 /usr/bin/supervisord --nodaemon -c /etc/supervisord.conf
    9 root      0:00 php-fpm: master process (/usr/local/etc/php-fpm.conf)
   10 root      0:00 nginx: master process nginx -g daemon off;
   11 nginx     0:00 nginx: worker process
   12 nginx     0:00 nginx: worker process
   13 nginx     0:00 nginx: worker process
   14 nginx     0:00 nginx: worker process
   15 www-data  0:00 php-fpm: pool www
   16 www-data  0:00 php-fpm: pool www
   17 root      0:00 ash
   24 root      0:00 ps -ef
/var/www/application #
```

You need to have the application running for this to work.
If you are debugging the Docker image and want a shell without a running application:

```console
$ docker run -it --rm ranger-clubhouse-api:dev ash
/var/www/application #
```

### Changing code

If you are changing code and want to see that reflected in the running application, make your changes, then stop the app, run `./bin/build` to create a new image with your changes, and then run `./bin/run` to start the server back up.

## Random Notes

- autoloaded packages and throwning PHP exception may require an explicit path
  e.g., new Google_Client -> new \Google_Client
  throw new \InvalidArgumentException("blah")

## What happened to the Classic Clubhouse config() function?

Laravel also uses `config()` for configuration variables, however, the values are in namespaces.
In Classic Clubhouse, to read PhotoSource the call would be `config("PhotoSource")` in ApiHouse the call is `config('clubhouse.PhotoSource')`

The defaults live in `config/clubhouse.php`
