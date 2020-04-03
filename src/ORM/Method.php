<?php
declare(strict_types=1);

/**
 * Copyright 2015 - 2020, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2015 - 2020, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\OracleDriver\ORM;

use Cake\Core\App;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use CakeDC\OracleDriver\Database\OracleConnection;
use CakeDC\OracleDriver\Database\Schema\MethodSchema;
use CakeDC\OracleDriver\ORM\Exception\MissingRequestException;

class Method
{
    /**
     * Name of the method as it can be found in the database
     *
     * @var string
     */
    protected $_method;

    /**
     * Connection instance
     *
     * @var \Cake\Datasource\ConnectionInterface
     */
    protected $_connection;

    /**
     * The schema object containing a description of this method fields
     *
     * @var \CakeDC\OracleDriver\Database\Schema\MethodSchema
     */
    protected $_schema;

    /**
     * The request class name for the method.
     *
     * @var string
     */
    protected $_requestClass;

    /**
     * Method constructor.
     *
     * @param array $config Method config options.
     */
    public function __construct(array $config = [])
    {
        if (!empty($config['method'])) {
            $this->setMethod($config['method']);
        }
        if (!empty($config['connection'])) {
            $this->setConnection($config['connection']);
        }
        if (!empty($config['schema'])) {
            $this->setSchema($config['schema']);
        }
        if (!empty($config['requestClass'])) {
            $this->requestClass($config['requestClass']);
        }
        $this->initialize($config);
    }

    /**
     * Returns the database method name or sets a new one
     *
     * @return string
     */
    public function getMethod()
    {
        if ($this->_method === null) {
            $method = namespaceSplit(static::class);
            $method = substr(end($method), 0, -6);
            $this->_method = Inflector::underscore($method);
        }

        return $this->_method;
    }

    /**
     * Returns the database method name or sets a new one
     *
     * @param string $method the new method name
     * @return string
     */
    public function setMethod($method)
    {
        $this->_method = $method;

        return $this;
    }

    /**
     * Sets the connection instance.
     *
     * @param \CakeDC\OracleDriver\Database\OracleConnection $connection The connection instance
     * @return $this
     */
    public function setConnection(OracleConnection $connection)
    {
        $this->_connection = $connection;

        return $this;
    }

    /**
     * Returns the connection instance.
     *
     * @return \CakeDC\OracleDriver\Database\OracleConnection
     */
    public function getConnection(): OracleConnection
    {
        if (!$this->_connection) {
            /** @var \CakeDC\OracleDriver\Database\OracleConnection $connection */
            $connection = ConnectionManager::get(static::defaultConnectionName());
            $this->_connection = $connection;
        }

        return $this->_connection;
    }

    /**
     * Returns the schema method object describing this method's parameters.
     *
     * @return \CakeDC\OracleDriver\Database\Schema\MethodSchema
     */
    public function getSchema(): MethodSchema
    {
        if ($this->_schema === null) {
            $method = $this->getConnection()
                           ->methodSchemaCollection()
                           ->describe($this->getMethod());
            $this->_schema = $this->_initializeSchema($method);
        }

        return $this->_schema;
    }

    /**
     * Sets the schema method object describing this method's parameters.
     *
     * If an \CakeDC\OracleDriver\Database\Schema\MethodSchema is passed, it will be used for
     * this method instead of the default one.
     *
     * If an array is passed, a new \CakeDC\OracleDriver\Database\Schema\MethodSchema will be constructed out of it and used as the schema for this method.
     *
     * @param array|\CakeDC\OracleDriver\Database\Schema\MethodSchema|null $schema New schema to be used for this table
     * @return $this
     */
    public function setSchema($schema)
    {
        if (is_array($schema)) {
            $schema = new MethodSchema($this->getMethod(), $schema);
        }

        $this->_schema = $schema;

        return $this;
    }

    /**
     * Override this function in order to alter the schema used by this method.
     * This function is only called after fetching the schema out of the database.
     * If you wish to provide your own schema to this method without touching the
     * database, you can override schema() or inject the definitions though that
     * method.
     *
     * ### Example:
     *
     * ```
     * protected function _initializeSchema(\CakeDC\OracleDriver\Database\Schema\MethodSchema $method) {
     *  return $method;
     * }
     * ```
     *
     * @param \CakeDC\OracleDriver\Database\Schema\MethodSchema $method The method definition fetched from database.
     * @return \CakeDC\OracleDriver\Database\Schema\MethodSchema the altered schema
     * @api
     */
    protected function _initializeSchema(MethodSchema $method)
    {
        return $method;
    }

    /**
     * Returns the class used to keep request parameters for this method
     *
     * @param string|null $name the name of the class to use
     * @throws \CakeDC\OracleDriver\ORM\Exception\MissingRequestException when the request class cannot be found
     * @return string
     */
    public function requestClass($name = null)
    {
        if ($name === null && !$this->_requestClass) {
            $default = '\CakeDC\OracleDriver\ORM\Request';
            $self = static::class;
            $parts = explode('\\', $self);

            if ($self === self::class || count($parts) < 3) {
                return $this->_requestClass = $default;
            }

            $alias = Inflector::singularize(substr(array_pop($parts), 0, -6));
            $name = implode('\\', array_slice($parts, 0, -1)) . '\Request\\' . $alias;
            if (!class_exists($name)) {
                return $this->_requestClass = $default;
            }
        }

        if ($name !== null) {
            $class = App::className($name, 'Model/Request');
            $this->_requestClass = $class;
        }

        if ($this->_requestClass === '') {
            throw new MissingRequestException([$name]);
        }

        return $this->_requestClass;
    }

    /**
     * Initialize a method instance. Called after the constructor.
     *
     * You can use this method to define associations, attach behaviors
     * define validation and do any other initialization logic you need.
     *
     * ```
     *  public function initialize(array $config)
     *  {
     *      $this->belongsTo('Users');
     *      $this->belongsToMany('Tagging.Tags');
     *      $this->primaryKey('something_else');
     *  }
     * ```
     *
     * @param array $config Configuration options passed to the constructor
     * @return void
     */
    public function initialize(array $config)
    {
    }

    /**
     * Builds new request object for current method.
     *
     * @param array $data Parameters data.
     * @return \CakeDC\OracleDriver\ORM\Request
     */
    public function newRequest($data = null)
    {
        $class = $this->requestClass();
        $request = new $class([], [
            'repository' => $this,
        ]);
        if (is_array($data)) {
            $request->set($data);
        }

        return $request;
    }

    /**
     * Execute request. Request should be initialized.
     *
     * @param \CakeDC\OracleDriver\ORM\RequestInterface $request Request object instance.
     * @return mixed
     */
    public function execute(RequestInterface $request)
    {
        $query = $this->_generateSql();
        $statement = $this->getConnection()->prepareMethod($query);
        $request->attachTo($statement);
        $result = $statement->execute();
        $request->isNew(false);
        // @todo optional autofetch cursors
        // @todo transform output toPHP
        return $result;
    }

    /**
     * Generate query sql.
     *
     * @todo move it into builder class
     *
     * @return string
     */
    protected function _generateSql()
    {
        $query = '';
        if ($this->getSchema()->isFunction() !== null) {
            $query = ':result := ';
        }
        $parameters = $this->getSchema()->parameters();
        $query .= $this->getMethod() . '(';
        $names = [];
        foreach ($parameters as $name) {
            if ($name === ':result') {
                continue;
            }
            $names[] = $name . ' => :' . $name;
        }
        $query .= implode(',', $names);
        $query .= ');';
        $query = 'begin ' . $query . ' end;';

        return $query;
    }

    /**
     * Returns an array that can be used to describe the internal state of this
     * object.
     *
     * @return array
     */
    public function __debugInfo()
    {
        $conn = $this->getConnection();

        return [
            'method' => $this->getMethod(),
            'defaultConnection' => $this->defaultConnectionName(),
            'connectionName' => $conn ? $conn->configName() : null,
        ];
    }

    /**
     * Get the default connection name.
     *
     * This method is used to get the fallback connection name if an
     * instance is created through the MethodRegistry without a connection.
     *
     * @return string
     * @see \CakeDC\OracleDriver\ORM\MethodRegistry::get()
     */
    public static function defaultConnectionName()
    {
        return 'default';
    }
}
