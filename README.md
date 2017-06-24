# Prestashop [Geizhals](https://geizhals.at/) Integration

This repository includes a php-file which SELECTs all products which are active and available for order from the database of Prestashop.
These products are then written to the geizhals.csv file which is downloaded by [Geizhals](https://geizhals.at/) and used to list the products in their price search machine.

The script is run by simply loading the page. Geizhals updated their database every 10 minutes by downloading the .csv file. Therefore the script should be called often enough. (This can be done with a Linux-Chronjob)