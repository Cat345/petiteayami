#!/bin/bash
custom="no_zip"
if [[ "$1" != "$custom" ]]; then
  read -p "Enter Version : " version
fi
REPO_PATH=$PWD

rm -rf ./.DS_Store;
rm -rf ./README.md;
rm -rf ./woofunnels-ab-tests;
rm ./rm woofunnels-ab-tests-*.zip;

mkdir woofunnels-ab-tests;
grunt
wp i18n make-pot $REPO_PATH $REPO_PATH'/languages/woofunnels-ab-tests.pot' --exclude=".github,.git,node_modules,woofunnels,admin/includes/wfacpkirki,admin/assets,assets"

#cp -R . woofunnels-ab-tests;

rsync -av --exclude='.git' --exclude='.github' --exclude='npm-debug.log' --exclude='.eslintrc.json' --exclude='.babelrc' --exclude='.gitignore' --exclude='.gitmodules' --exclude='node_modules' --exclude='build.sh' --exclude='gruntfile.js' --exclude='package.json' --exclude='package-lock.json' --exclude='.jshintrc' --exclude='phpcs.xml' ./ ./woofunnels-ab-tests

rm -R ./woofunnels-ab-tests/assets/dev;
rm -R ./woofunnels-ab-tests/woofunnels-ab-tests;


if [[ "$1" != "$custom" ]]; then
 zip -r woofunnels-ab-tests-v$version.zip ./woofunnels-ab-tests;

 rm -rf ./woofunnels-ab-tests;
fi