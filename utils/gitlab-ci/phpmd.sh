#!/bin/sh -e

phpmd include text utils/gitlab-ci/phpmd-ruleset.xml
phpmd classes text utils/gitlab-ci/phpmd-ruleset.xml

FILES=$(ls -dm *.php | sed "s/ //g")
phpmd $FILES text utils/gitlab-ci/phpmd-ruleset.xml
