#!/bin/sh -e

exec find . -name "*.php" -print0 | xargs -0 -n1 php -q -l
