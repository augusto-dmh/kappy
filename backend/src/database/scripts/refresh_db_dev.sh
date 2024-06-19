#!/bin/bash

while true; do
  read -p "Do you wish to refresh the database? (y/n) " yn
  case $yn in
    [Yy]* ) break;;
    [Nn]* ) exit;;
    * ) echo "Please answer 'yes' or 'no'.";;
  esac
done

cd ../../../

npx sequelize-cli db:migrate:undo:all

echo -e "\nCreating tables..."

npx sequelize-cli db:migrate

echo "Inserting mock data..."

npx sequelize-cli db:seed:all

sleep 3
