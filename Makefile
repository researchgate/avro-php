# no buildin rules and variables
MAKEFLAGS =+ -rR --warn-undefined-variables

.PHONY: composer-install composer-update examples docker run

COMPOSER ?= bin/composer.phar
COMPOSER_VERSION ?= 1.8.3
PHP ?= bin/php
PHP_VERSION ?= 7.2
XDEBUG_VERSION ?= 2.7.0RC1

export

docker:
	docker build \
	  --build-arg PHP_VERSION=$(PHP_VERSION) \
	  --build-arg XDEBUG_VERSION=$(XDEBUG_VERSION) \
	  -t avro-php:$(PHP_VERSION) \
	  -f Dockerfile \
	  .

composer-install:
	PHP_VERSION=$(PHP_VERSION) $(PHP) $(COMPOSER) install --no-interaction --no-progress --no-suggest --no-scripts

composer-update:
	PHP_VERSION=$(PHP_VERSION) $(PHP) $(COMPOSER) update --no-interaction --no-progress --no-suggest --no-scripts

phpunit:
	@mkdir -p build/tmp build/share/test/schemas build/build/interop/data
	@chmod -R a+w build
	PHP_VERSION=$(PHP_VERSION) $(PHP) vendor/bin/phpunit --coverage-text test/AllTests.php

run:
	PHP_VERSION=$(PHP_VERSION) $(PHP) $(ARGS)

examples:
	PHP_VERSION=$(PHP_VERSION) $(PHP) examples/*

install-phars:
	curl https://getcomposer.org/download/$(COMPOSER_VERSION)/composer.phar -o bin/composer.phar -LR -z bin/composer.phar
	chmod a+x bin/composer.phar

install: install-phars docker composer-install

clean:
	rm -r build/*
