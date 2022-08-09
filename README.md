# Shapla WC Order Import/Export

A simple WooCommerce plugin to export/import orders. Export can be done via start data and end date or order id(s).

### Features

* Export by start and end date.
* Export by order id(s).

### Purpose of the plugin

* Export data form live site, and
* Import data to development/staging site

### Limitations

* It import order blindly without checking anything
* Only export data from default data store for order. If you are using custom order table to store order data it won't
  work.

### Usages

#### Export WooCommerce order data

* Visit to **WordPress Admin** area.
* Navigate to **Tools --> Export**
* Choose **WooCommerce Order (JSON Export)**
* Enter
* order ids separating by comma. e.g. `391,422,513`
* or enter start data and (optionally) end date.

#### Import WooCommerce order data

* Visit to **WordPress Admin** area.
* Navigate to **Tools --> Import**
* Click on **WooCommerce Order (JSON Export) --> Run Importer**
* Click on **Choose file** and select you JSON file.
* Click on **Upload file and import** and done.
