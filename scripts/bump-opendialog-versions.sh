#!/usr/bin/env bash

# $1 = repo url
# $2 = repo name
# $3 = current version

version=`git ls-remote --tags --sort="v:refname" $1 | tail -n1 | sed 's/.*\///; s/\^{}//'`

echo "Latest versions for ${2}:"
git ls-remote --tags --sort="v:refname" $1 | tail -n10 | sed 's/.*\///; s/\^{}//' | uniq

if [ $version ]; then
  echo "Do you want to bump ${2} from current verison ${3} to latest version ${version}"
  select yne in "Yes" "No" "Specify"; do
      case $yne in
          Yes ) break;;
          No ) exit;;
          Specify ) read -p "Enter version of ${2} to use: " version; break;;
      esac
  done
else
  echo "Latest version of ${2} cannot be found, would you like to enter a version?"
      select yn in "Yes" "No"; do
          case $yn in
              Yes ) read -p "Enter version of ${2} to use: " version; break;;
              No ) exit;;
          esac
      done
fi

if [ $2 == "design-system" ]; then
  yarn upgrade @opendialogai/opendialog-design-system-pkg@${version} || echo "Could not upgrade"
else
  composer require opendialogai/${2}:${version} || echo "Could not upgrade"
fi

git add composer.json composer.lock package.json yarn.lock
git commit -m "bumps version of ${2} to ${version} as part of release"