#!/usr/bin/env bash

# Checks code style via PHP CS Fixer only in changed files of the pull request, but everything when pushing the branch.

IFS='
'
if [ "${TRAVIS_PULL_REQUEST}" != "false" ]; then
    CHANGED_PHP_FILES=$(git diff --name-only --diff-filter=ACMRTUXB "${TRAVIS_COMMIT_RANGE}" | grep -E "\.php$")
  else
    CHANGED_PHP_FILES="";
fi;

# If any changed php files are found...
if echo "${CHANGED_PHP_FILES}" | grep -qE "\.php$"; then
  # Check only them.
  EXTRA_ARGS=$(printf -- '--path-mode=intersection\n--\n%s' "${CHANGED_PHP_FILES}");
else
  EXTRA_ARGS='';
fi
echo "EXTRA_ARGS: ${EXTRA_ARGS}"

vendor/bin/php-cs-fixer fix \
                            --config=.php_cs.dist \
                            --ansi \
                            --dry-run \
                            --stop-on-violation \
                            --using-cache=no \
                            --diff ${EXTRA_ARGS}
