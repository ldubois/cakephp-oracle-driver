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
namespace CakeDC\OracleDriver\Database\OCI8;

use Iterator;
use PDO;

/**
 * The OCI8 implementation of the Statement interface.
 */
class OCI8Statement extends \PDOStatement implements \IteratorAggregate
{
    /**
     * @var bool
     */

    protected $_returnLobs = true;

    /**
     * @var resource
     */
    protected $_dbh;

    /**
     * @var resource
     */
    protected $_sth;

    /**
     * @var \CakeDC\OracleDriver\Database\OCI8\OCI8Connection
     */
    protected $_conn;

    /**
     * @var string
     */
    protected static $_PARAM = ':param';

    /**
     * @var array
     */
    protected static $fetchModeMap = [
        PDO::FETCH_BOTH => OCI_BOTH,
        PDO::FETCH_ASSOC => OCI_ASSOC,
        PDO::FETCH_NUM => OCI_NUM,
        PDO::FETCH_COLUMN => OCI_NUM,
    ];

    /**
     * Column number for PDO::FETCH_COLUMN fetch mode
     *
     * @var int
     */
    protected $_fetchColumnNumber = 0;

    /**
     * @var int
     */
    protected $_defaultFetchMode = PDO::ATTR_DEFAULT_FETCH_MODE;

    /**
     * @var array
     */
    protected $_paramMap = [];

    /**
     * @var array
     */
    protected $_values = [];

    protected $_fetchMode = PDO::ATTR_DEFAULT_FETCH_MODE;

    protected $_fetchClassName = '\stdClass';

    protected $_fetchIntoObject = null;

    protected $_fetchArguments = [];

    protected $_results = [];

    /**
     * Creates a new OCI8Statement that uses the given connection handle and SQL statement.
     *
     * @param resource $dbh The connection handle.
     * @param string|resource $statement The SQL statement.
     * @param \CakeDC\OracleDriver\Database\OCI8\OCI8Connection $conn OCI connection.
     */
    public function __construct($dbh, $statement, OCI8Connection $conn)
    {
        if (is_resource($statement)) {
            $this->_sth = $statement;
            $paramMap = [];
        } else {
            [$statement, $paramMap] = self::convertPositionalToNamedPlaceholders($statement);
            $this->_sth = oci_parse($dbh, $statement);
        }
        $this->_dbh = $dbh;
        $this->_paramMap = $paramMap;
        $this->_conn = $conn;
    }

    /**
     * Converts positional (?) into named placeholders (:param<num>).
     *
     * Oracle does not support positional parameters, hence this method converts all
     * positional parameters into artificially named parameters. Note that this conversion
     * is not perfect. All question marks (?) in the original statement are treated as
     * placeholders and converted to a named parameter.
     *
     * The algorithm uses a state machine with two possible states: InLiteral and NotInLiteral.
     * Question marks inside literal strings are therefore handled correctly by this method.
     * This comes at a cost, the whole sql statement has to be looped over.
     *
     * @param string $statement The SQL statement to convert.
     *
     * @return string
     */
    public static function convertPositionalToNamedPlaceholders($statement)
    {
        $count = 1;
        $inLiteral = false;
        $stmtLen = strlen($statement);
        $paramMap = [];
        for ($i = 0; $i < $stmtLen; $i++) {
            if ($statement[$i] === '?' && !$inLiteral) {
                $paramMap[$count] = ":param$count";
                $len = strlen($paramMap[$count]);
                $statement = substr_replace($statement, ":param$count", $i, 1);
                $i += $len - 1;
                $stmtLen = strlen($statement);
                ++$count;
            } elseif ($statement[$i] === "'" || $statement[$i] === '"') {
                $inLiteral = !$inLiteral;
            }
        }

        return [$statement, $paramMap];
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null):bool
    {
        $this->_values[$param] = $value;

        return $this->bindParam($param, $this->_values[$param], $type, null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam( $column, &$variable, $type = null, $length = null, $driverData = null): bool
    {
        $column = $this->_paramMap[$column] ?? $column;

        // @todo add additional type passing: as an option we could accept $type as array
        // where $type = ['ociType' => "REAL_OCI_TYPE", 'plsql_type' => 'VARRAY', 'php_type' => 'string']
        // this way we could choose correct type and correct binding function like oci_bind_array_by_name

        if ($type == \PDO::PARAM_STMT) {
            $variable = oci_new_cursor($this->_dbh);

            return oci_bind_by_name($this->_sth, $column, $variable, -1, OCI_B_CURSOR);
        } elseif ($type == \PDO::PARAM_LOB) {
            $lob = oci_new_descriptor($this->_dbh, OCI_D_LOB);
            $lob->writeTemporary($variable, OCI_TEMP_BLOB);

            return oci_bind_by_name($this->_sth, $column, $lob, -1, OCI_B_BLOB);
        } elseif ($length !== null) {
            return oci_bind_by_name($this->_sth, $column, $variable, $length);
        }

        return oci_bind_by_name($this->_sth, $column, $variable);
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor(): bool 
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function __destruct()
    {
        if (is_resource($this->_sth)) {
            oci_free_statement($this->_sth);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount(): int
    {
        return oci_num_fields($this->_sth);
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode(): ?string
    {
        $error = oci_error($this->_sth);
        if ($error !== false) {
            $error = $error['code'];
        } else {
            return '00000';
        }

        return $error;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo(): array
    {
        return oci_error($this->_sth);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null): bool
    {
        if ($params) {
            $hasZeroIndex = array_key_exists(0, $params);
            foreach ($params as $key => $val) {
                if ($hasZeroIndex && is_numeric($key)) {
                    $this->bindValue($key + 1, $val);
                } else {
                    $this->bindValue($key, $val);
                }
            }
        }

        $ret = @oci_execute($this->_sth, $this->_conn->getExecuteMode());
        if (!$ret) {
            throw OCI8Exception::fromErrorInfo($this->errorInfo());
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Iterator
    {
        $data = $this->fetchAll();

        return new \ArrayIterator($data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $toLowercase = ($this->getAttribute(PDO::ATTR_CASE) == PDO::CASE_LOWER);
        $nullToString = ($this->getAttribute(PDO::ATTR_ORACLE_NULLS) == PDO::NULL_TO_STRING);
        $nullEmptyString = ($this->getAttribute(PDO::ATTR_ORACLE_NULLS) == PDO::NULL_EMPTY_STRING);

        $fetchMode = $mode ?: $this->_defaultFetchMode;

        switch ($fetchMode) {
            case PDO::FETCH_BOTH:
                $rs = oci_fetch_array($this->_sth);
                if ($rs === false) {
                    return false;
                }
                if ($toLowercase) {
                    $rs = array_change_key_case($rs);
                }
                if ($this->_returnLobs && is_array($rs)) {
                    foreach ($rs as $field => $value) {
                        if (is_object($value)) {
                            $rs[$field] = $value->load();
                        }
                    }
                }

                return $rs;

            case PDO::FETCH_ASSOC:
                $rs = oci_fetch_assoc($this->_sth);
                if ($rs === false) {
                    return false;
                }
                if ($toLowercase) {
                    $rs = array_change_key_case($rs);
                }
                if ($this->_returnLobs && is_array($rs)) {
                    foreach ($rs as $field => $value) {
                        if (is_object($value)) {
                            $rs[$field] = $value->load();
                        }
                    }
                }

                return $rs;

            case PDO::FETCH_NUM:
                $rs = oci_fetch_row($this->_sth);
                if ($rs === false) {
                    return false;
                }
                if ($this->_returnLobs && is_array($rs)) {
                    foreach ($rs as $field => $value) {
                        if (is_object($value)) {
                            $rs[$field] = $value->load();
                        }
                    }
                }

                return $rs;

            case PDO::FETCH_COLUMN:
                $rs = oci_fetch_row($this->_sth);
                $columnNumber = (int)$this->_fetchColumnNumber;
                if (is_array($rs) && array_key_exists($columnNumber, $rs)) {
                    $value = $rs[$columnNumber];
                    if (is_object($value)) {
                        return $value->load();
                    } else {
                        return $value;
                    }
                } else {
                    return false;
                }
                break;

            case PDO::FETCH_OBJ:
            case PDO::FETCH_INTO:
            case PDO::FETCH_CLASS:
            case PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE:
                $rs = oci_fetch_assoc($this->_sth);
                if ($rs === false) {
                    return false;
                }
                if ($toLowercase) {
                    $rs = array_change_key_case($rs);
                }

                if ($fetchMode === PDO::FETCH_INTO) {
                    if (is_object($this->_fetchIntoObject)) {
                        $object = $this->_fetchIntoObject;
                    } else {
                        return false;
                    }
                } else {
                    if ($fetchMode === PDO::FETCH_OBJ) {
                        $className = '\stdClass';
                        $arguments = [];
                    } else {
                        $className = $this->_fetchClassName;
                        $arguments = $this->_fetchArguments;
                    }

                    if ($arguments) {
                        $reflectionClass = new \ReflectionClass($className);
                        $object = $reflectionClass->newInstanceArgs($arguments);
                    } else {
                        $object = new $className();
                    }
                }

                foreach ($rs as $field => $value) {
                    if (is_null($value) && $nullToString) {
                        $rs[$field] = '';
                    }

                    if (empty($rs[$field]) && $nullEmptyString) {
                        $rs[$field] = null;
                    }

                    $object->$field = $this->_returnLobs && is_object($value) ? $value->load() : $value;
                }

                return $object;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    //fetchAll($fetchMode = null, $className = null, $arguments = null)
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array 
    {
        $fetchArgument =null;
        $this->setFetchMode($mode, $args);

        $this->_results = [];
        while ($row = $this->fetch()) {
            if (is_resource(reset($row))) {
                $stmt = new OCI8Statement($this->_dbh, reset($row), $this->_conn);
                $stmt->execute();
                $stmt->setFetchMode($mode, $args);
                while ($rs = $stmt->fetch()) {
                    $this->_results[] = $rs;
                }
            } else {
                $this->_results[] = $row;
            }
        }

        return $this->_results;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn(int $column = 0): mixed
    {
        $row = oci_fetch_array($this->_sth, OCI_NUM | OCI_RETURN_NULLS | OCI_RETURN_LOBS);

        if ($row === false) {
            return false;
        }

        return $row[$columnIndex] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount():int
    {
        if (is_resource($this->_sth)) {
            return oci_num_rows($this->_sth);
        }

        return 0;
    }

    /**
     * Retrieve a statement attribute
     *
     * @param string $attribute Attribute id.
     * @return mixed The attribute value.
     */
    public function getAttribute(int $name): mixed
    {
        return $this->_conn->getConfig((string)$name);
    }

    /**
     * Set the default fetch mode for this statement
     *
     * @param int|null $fetchMode The fetch mode must be one of the PDO::FETCH_* constants.
     * @param mixed|null $param Column number, class name or object.
     * @param array|null $arguments Constructor arguments.
     * @throws \CakeDC\OracleDriver\Database\OCI8\Oci8Exception
     * @return bool TRUE on success or FALSE on failure.
     */
    public function setFetchMode(int $mode, mixed ...$args)
    {
        $this->_defaultFetchMode = $mode;
        $param = null;
        switch ($mode) {
            case PDO::FETCH_ASSOC:
            case PDO::FETCH_NUM:
            case PDO::FETCH_BOTH:
            case PDO::FETCH_OBJ:
                $this->_fetchMode = $mode;
                $this->_fetchColumnNumber = 0;
                $this->_fetchClassName = '\stdClass';
                $this->_fetchArguments = [];
                $this->_fetchIntoObject = null;
                break;
            case PDO::FETCH_CLASS:
            case PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE:
                $this->_fetchMode = $mode;
                $this->_fetchColumnNumber = 0;
                $this->_fetchClassName = '\stdClass';
                if ($param) {
                    $this->_fetchClassName = $param;
                }
                $this->_fetchArguments = $args;
                $this->_fetchIntoObject = null;
                break;
            case PDO::FETCH_INTO:
                if (!is_object($param)) {
                    throw new OCI8Exception(__('$param must be instance of an object'));
                }
                $this->_fetchMode = $mode;
                $this->_fetchColumnNumber = 0;
                $this->_fetchClassName = '\stdClass';
                $this->_fetchArguments = [];
                $this->_fetchIntoObject = $param;
                break;
            case PDO::FETCH_COLUMN:
                $this->_fetchMode = $mode;
                $this->_fetchColumnNumber = (int)$param;
                $this->_fetchClassName = '\stdClass';
                $this->_fetchArguments = [];
                $this->_fetchIntoObject = null;
                break;
            default:
                throw new OCI8Exception(__('Requested fetch mode is not supported by this implementation'));
        }

        return true;
    }
}
