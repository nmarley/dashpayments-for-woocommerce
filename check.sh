#! /usr/local/bin/bash
for F in {*php,includes/*php,includes/*/*php}; do
  php -l "$F"
done
