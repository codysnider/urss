#!/bin/sh

set -e

phpmd include,classes text utils/gitlab-ci/phpmd-ruleset.xml

FILES=$(ls -dm *.php | tr -d " "| tr -d "\n")
phpmd $FILES text utils/gitlab-ci/phpmd-ruleset.xml
