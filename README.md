# PHP Shared Memory Model

This repository reproduces edge cases for the PHP runtime environments that are using [the shared memory model](https://en.wikipedia.org/wiki/Shared_memory). It uses an example [Symfony](https://symfony.com/) project with single test controller.


## Installation

Clone the content of this repository:
```bash
cd ~/Projects
git clone git@github.com:SerheyDolgushev/php-shared-memory-model.git
cd php-shared-memory-model
```

### Swoole

If you are on macOS, please follow the next steps:

1. Install `pcre2`:
    ```bash
    brew install pcre2
    ```
2. Link `pcre2` into PHP, in this example `/opt/homebrew/opt/php@8.3` is the PHP path:
    ```bash
    ln -s /opt/homebrew/opt/pcre2/include/pcre2.h /opt/homebrew/opt/php@8.3/include/php/ext/pcre/
    ```
3. Install `swool` using via PECL:
    ```bash
    pecl install -o -D 'enable-sockets="yes" enable-swoole-curl="yes" enable-brotli="yes" enable-cares="no" enable-mysqlnd="no" enable-swoole-pgsql="no" with-swoole-odbc="no" with-swoole-oracle="no" enable-swoole-sqlite="no" enable-openssl="no"' swoole
    ```

4. Disable swoole extension by default by removing `extension=swoole.so` from `/opt/homebrew/etc/php/8.3/php.ini`

Otherwise, please follow [Swoole Installation guide](https://github.com/swoole/swoole-src?tab=readme-ov-file#%EF%B8%8F-installation).


### RoadRunner

If you are on macOS, the simplest way to install RoadRunner is using [Homebrew](https://brew.sh/) 
```bash
brew install roadrunner
```

Otherwise, please follow [RoadRunner Installation guide](https://docs.roadrunner.dev/general/install).

### FrankenPHP

Follow the official instructions to download [Standalone Binary](https://frankenphp.dev/docs/#standalone-binary), and please save it to `~/frankenphp`.

## Problem

First of all, have a glance at [TestController](https://github.com/SerheyDolgushev/php-shared-memory-model/blob/main/src/Controller/TestController.php). It has the only `testAction` method that returns the date and increased value of the `counter` property.

Start local PHP server:
```bash
php -S 127.0.0.1:8000 public/index.php
```

And send a few requests to the `TestController::testAction`:
```bash
% curl http://127.0.0.1:8000/test
[2024-02-24T08:05:03+00:00] Counter: 1
% curl http://127.0.0.1:8000/test
[2024-02-24T08:05:07+00:00] Counter: 1
% curl http://127.0.0.1:8000/test
[2024-02-24T08:05:10+00:00] Counter: 1
```

As expected, each response returns `1`. Nothing strange here.


### Swoole

Try to perform the same steps by running the Swoole runtime:
```bash
APP_RUNTIME=Runtime\\Swoole\\Runtime \
    php -d extension=swoole.so public/swoole.php
```

And sending the same test requests:
```bash
% curl http://127.0.0.1:8000/test
[2024-02-24T08:07:59+00:00] Counter: 1
% curl http://127.0.0.1:8000/test
[2024-02-24T08:08:02+00:00] Counter: 2
% curl http://127.0.0.1:8000/test
[2024-02-24T08:08:06+00:00] Counter: 3
```

In this case, each response returns the incremented value of the previous response. Which might be unexpected behavior. 

### RoadRunner

Let's reproduce the same test in RoadRunner runtime:
```bash
rr serve -c rr.yaml -o http.pool.num_workers=1
```

And the same test requests:
```bash
% curl http://127.0.0.1:8000/test
[2024-02-24T08:10:45+00:00] Counter: 1
% curl http://127.0.0.1:8000/test
[2024-02-24T08:10:49+00:00] Counter: 2
% curl http://127.0.0.1:8000/test
[2024-02-24T08:10:52+00:00] Counter: 3
```

And the results are similar to the Swoole ones.

### FrankenPHP

Let's use FrankenPHP to test:

```bash
cd ./public
APP_RUNTIME=Runtime\\FrankenPhpSymfony\\Runtime \
    ~/frankenphp php-server -l 127.0.0.1:8000 -w ./index.php,1
```

And again the same test requests:
```bash
% curl http://127.0.0.1:8000/testq
[2024-03-01T08:35:40+00:00] Counter: 1
% curl http://127.0.0.1:8000/test
[2024-03-01T08:35:41+00:00] Counter: 2
% curl http://127.0.0.1:8000/test
[2024-03-01T08:35:42+00:00] Counter: 3
```

## Root cause

The simplified explanation is that PHP runtime uses [Shared-nothing architecture](https://en.wikipedia.org/wiki/Shared-nothing_architecture) by running the garbage collector to clear the memory between the requests. So memory is cleared after the previous request is completed and before the next one starts. But Swoole and RoarRunner runtimes are using [Shared memory model](https://en.wikipedia.org/wiki/Shared_memory) which gives it its power. In this case, the memory is shared between different requests handled by the same worker. Please note, single-worker configuration have been used for all runtimes to simplify showcasing the problem.  You can find more details in [RoarRunner documentation](https://roadrunner.dev/docs/app-server-production/2.x/en#state-and-memory).