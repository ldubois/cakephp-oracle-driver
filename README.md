# CakePHP 4 Driver for Oracle Database

[![Downloads](https://poser.pugx.org/cakedc/cakephp-oracle-driver/d/total.png)](https://packagist.org/packages/cakedc/cakephp-oracle-driver)
[![Latest Version](https://poser.pugx.org/cakedc/cakephp-oracle-driver/v/stable.png)](https://packagist.org/packages/cakedc/cakephp-oracle-driver)

Versions and branches
---------------------

| CakePHP | CakeDC Oracle Driver Plugin | Tag   | Notes |
| :-------------: | :------------------------: | :--:  | :---- |
| 4.0             | [5.x](https://github.com/CakeDC/cakephp-oracle-driver/tree/5.x)                  | 5.0.0 | stable
| ^3.8       <4.0 | [4.x](https://github.com/CakeDC/cakephp-oracle-driver/tree/4.x)                  | 4.0.0 | stable |
| ^3.7            | [3.x](https://github.com/CakeDC/cakephp-oracle-driver/tree/3.x)                  | 3.0.0 | stable |


## Installation

You can install this plugin into your CakePHP application using
[Composer](http://getcomposer.org).

The recommended way to install Composer packages is:

```
composer require cakedc/cakephp-oracle-driver
```


Ensure the Oracle Plugin is loaded in your src/Application.php file

```
    /**
     * {@inheritdoc}
     */
    public function bootstrap()
    {
        parent::bootstrap();

        $this->addPlugin(\CakeDC\OracleDriver\Plugin::class, ['bootstrap' => true]);
    }
```


## Requirements

- CakePHP 4.0+
- an Oracle PHP extension
  - OCI8 (PHP extension built with PHP)
  - PDO_OCI (PHP extension built with PHP)

### Notes on extensions

For full support, it is recommended to run the `OCI8` extension if possible.

While `PDO_OCI` might be simpler to set up, there are some limitations (e.g.
it doesn't support the stored code layer, so only the SQL layer will be
available.)

## Datasource configuration

Here is an example datasource configuration:

```php
        <?php
return [
    'Datasources' => [
        'default' => [
            'className' => 'CakeDC\OracleDriver\Database\OracleConnection',
            'driver' => 'CakeDC\OracleDriver\Database\Driver\OracleOCI', # For OCI8
            #'driver' => 'CakeDC\\OracleDriver\\Database\\Driver\\OraclePDO', # For PDO_OCI
            'persistent' => true,           // Database persistent connection between http requests
            'host' => 'oracle11g',          // Database host name or IP address
            //'port' => 'nonstandard_port', // Database port number (default: 1521)
            'username' => 'blogs',          // Database username
            'password' => 'password',       // Database password
            'database' => 'XE',             // Database name (maps to Oracle's `SERVICE_NAME`)
            'sid' => '',                    // Database System ID (maps to Oracle's `SID`)
            'instance' => '',               // Database instance name (maps to Oracle's `INSTANCE_NAME`)
            'pooled' => '',                 // Database pooling (maps to Oracle's `SERVER=POOLED`)
            'flags' => [],                  // Database low level parameters for OCI or PDO connection. Auto-generated by default
            'encoding' => '',               // Database charset (default same as database charset)
            'init' => [],                   // Array of queries executed at connection
            'cacheMetadata' => true,        // Enable cakephp schema caching
            'server_version' => 12,         // Int for Oracle server numeric version ex.: 11,12,12.1,12.2,19
            'autoincrement' => true,        // Enable autoincrement insteadof custom triggers in case of oracle 12+
        ]
    ]
];
```

For Oracle 12g should be used config settings server_version with value 12.
If version is greater or equals to 12 the plugin uses new oracle pagination features.

Additional if autoincrement is set to true, then for primary keys in fixtures used
new orale autoincreament feature.
If Oracle 12g with server_version >= 12.2 the select aliases max length is set to 128 Chars. (30 Chars otherwise)

As you can see, the `className` and `driver` need switched to Oracle-specific
classes. The driver will depend on whether you want to use `PDO` or not. The
`database` name "XE" in this case, is defined as the `SERVICE_NAME` in Oracle's
system-wide `tnsnames.ora` file.

The above array format is translated into Oracle's
[Easy Connection Naming][oracle-ecn]. You can find the logic for this in
`\CakeDC\OracleDriver\Database\Driver\OracleBase::getDSN()`.

[oracle-ecn]: https://docs.oracle.com/cd/B19306_01/network.102/b14212/naming.htm#sthref783

Documentation
-------------

For documentation, as well as tutorials, see the [docs](docs/Home.md) directory of this repository.

Roadmap
-------------
- Provide full compatibility with Phinx based migrations
- Provide compatibility with Oracle database version 12
- Transform data types into native PHP types when returned form the database
@todo copy todos to the roadmap

Support
-------

For bugs and feature requests, please use the [issues](https://github.com/cakedc/cakephp-oracle-driver/issues) section of this repository.

Commercial support is also available, [contact us](http://cakedc.com/contact) for more information.

Contributing
------------

This repository follows the [CakeDC Plugin Standard](http://cakedc.com/plugin-standard). If you'd like to contribute new features, enhancements or bug fixes to the plugin, please read our [Contribution Guidelines](http://cakedc.com/contribution-guidelines) for detailed instructions.

License
-------

Copyright 2016 - 2020 Cake Development Corporation (CakeDC). All rights reserved.

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.

