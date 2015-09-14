<?php
namespace DreamFactory\Core\Database\Oci;

/**
 * This is an extension of default PDO class for OCI8 driver only.
 * It provides workaround of the improperly implemented functions of PDO OCI driver.
 */
class PdoAdapter extends \PDO
{
    /**
     * @var resource
     */
    protected $dbh;

    /**
     * @var integer
     */
    protected $executeMode = OCI_COMMIT_ON_SUCCESS;

    public function __construct($dsn, $username, $password, $options)
    {
        $persistent = false;
        $db = substr($dsn, strpos($dsn, 'dbname=') + 7);
        $charset = null;
        $sessionMode = null;

        if (!defined('OCI_NO_AUTO_COMMIT')) {
            define('OCI_NO_AUTO_COMMIT', 0);
        }

        $this->dbh =
            $persistent
                ? @oci_pconnect($username, $password, $db, $charset, $sessionMode)
                : @oci_connect(
                $username,
                $password,
                $db,
                $charset,
                $sessionMode
            );

        if (!$this->dbh) {
            $error = oci_error();
            throw new \Exception($error['message'], $error['code']);
        }
    }

    public function prepare($statement, $driver_options = null)
    {
        return new OCI8Statement($this->dbh, $statement, $this);
    }

    public function beginTransaction()
    {
        $this->executeMode = OCI_NO_AUTO_COMMIT;

        return true;
    }

    public function commit()
    {
        if (!oci_commit($this->dbh)) {
            $error = oci_error($this->dbh);
            throw new \Exception($error['message'], $error['code']);
        }
        $this->executeMode = OCI_COMMIT_ON_SUCCESS;

        return true;
    }

    public function rollBack()
    {
        if (!oci_rollback($this->dbh)) {
            $error = oci_error($this->dbh);
            throw new \Exception($error['message'], $error['code']);
        }
        $this->executeMode = OCI_COMMIT_ON_SUCCESS;

        return true;
    }

    public function inTransaction()
    {
        return (OCI_NO_AUTO_COMMIT === $this->executeMode);
    }

    public function getExecuteMode()
    {
        return $this->executeMode;
    }

    public function getAttribute($attribute)
    {
    }

    public function setAttribute($attribute, $value)
    {
    }

    public function exec($statement)
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function query($statement)
    {
//        $args = func_get_args();
//        $sql = $args[0];
        //$fetchMode = $args[1];
        $stmt = $this->prepare($statement);
        $stmt->execute();

        return $stmt;
    }

    public function lastInsertId($name = null)
    {
        if (empty($name)) {
            return false;
        }

        $sql = 'SELECT ' . $name . '.CURRVAL FROM DUAL';
        $stmt = $this->query($sql);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result === false || !isset($result['CURRVAL'])) {
            throw new \Exception("lastInsertId failed: Query was executed but no result was returned.");
        }

        return (int)$result['CURRVAL'];
    }

    public function errorCode()
    {
        $error = oci_error($this->dbh);
        if ($error !== false) {
            $error = $error['code'];
        }

        return $error;
    }

    public function errorInfo()
    {
        return oci_error($this->dbh);
    }

    public function quote($value, $parameter_type = \PDO::PARAM_STR)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = str_replace("'", "''", $value);

        return "'" . addcslashes($value, "\000\n\r\\\032") . "'";
    }

    /**
     * {@inheritdoc}
     *
     * @throws \UnexpectedValueException if the version string returned by the database server
     *                                   does not contain a parsable version number.
     */
    public function getServerVersion()
    {
        if (!preg_match('/\s+(\d+\.\d+\.\d+\.\d+\.\d+)\s+/', oci_server_version($this->dbh), $version)) {
            throw new \UnexpectedValueException(
                sprintf(
                    'Unexpected database version string "%s". Cannot parse an appropriate version number from it. ',
                    oci_server_version($this->dbh)
                )
            );
        }

        return $version[1];
    }
}

/**
 * The OCI8 implementation of the Statement interface.
 */
class OCI8Statement implements \IteratorAggregate
{
    /**
     * @var resource
     */
    protected $dbh;

    /**
     * @var resource
     */
    protected $sth;

    /**
     * @var PdoAdapter
     */
    protected $conn;

    /**
     * @var string
     */
    protected static $PARAM = ':param';

    /**
     * @var array
     */
    protected static $fetchModeMap = array(
        \PDO::FETCH_BOTH   => OCI_BOTH,
        \PDO::FETCH_ASSOC  => OCI_ASSOC,
        \PDO::FETCH_NUM    => OCI_NUM,
        \PDO::FETCH_COLUMN => OCI_NUM,
        \PDO::FETCH_BOUND  => OCI_ASSOC,
    );

    /**
     * @var integer
     */
    protected $defaultFetchMode = \PDO::FETCH_BOTH;

    /**
     * @var array
     */
    protected $paramMap = array();

    protected $bindTypeMap = array();

    /**
     * Creates a new OCI8Statement that uses the given connection handle and SQL statement.
     *
     * @param resource   $dbh       The connection handle.
     * @param string     $statement The SQL statement.
     * @param PdoAdapter $conn
     */
    public function __construct($dbh, $statement, PdoAdapter $conn)
    {
        list($statement, $paramMap) = self::convertPositionalToNamedPlaceholders($statement);
        $this->sth = oci_parse($dbh, $statement);
        $this->dbh = $dbh;
        $this->paramMap = $paramMap;
        $this->conn = $conn;
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
     * @todo extract into utility class in Doctrine\DBAL\Util namespace
     * @todo review and test for lost spaces. we experienced missing spaces with oci8 in some sql statements.
     *
     * @param string $statement The SQL statement to convert.
     *
     * @return string
     */
    static public function convertPositionalToNamedPlaceholders($statement)
    {
        $count = 1;
        $inLiteral = false; // a valid query never starts with quotes
        $stmtLen = strlen($statement);
        $paramMap = array();
        for ($i = 0; $i < $stmtLen; $i++) {
            if ($statement[$i] == '?' && !$inLiteral) {
                // real positional parameter detected
                $paramMap[$count] = ":param$count";
                $len = strlen($paramMap[$count]);
                $statement = substr_replace($statement, ":param$count", $i, 1);
                $i += $len - 1; // jump ahead
                $stmtLen = strlen($statement); // adjust statement length
                ++$count;
            } elseif ($statement[$i] == "'" || $statement[$i] == '"') {
                $inLiteral = !$inLiteral; // switch state!
            }
        }

        return array($statement, $paramMap);
    }

    public function bindParam(
        $parameter,
        &$variable,
        $data_type = \PDO::PARAM_STR,
        $length = null,
        $driver_options = null
    ){
        $column = isset($this->paramMap[$parameter]) ? $this->paramMap[$parameter] : $parameter;

        if ($data_type == \PDO::PARAM_LOB) {
            $lob = oci_new_descriptor($this->dbh, OCI_D_LOB);
            $lob->writeTemporary($variable, OCI_TEMP_BLOB);

            return oci_bind_by_name($this->sth, $column, $lob, -1, OCI_B_BLOB);
        } elseif ($length !== null) {
            return oci_bind_by_name($this->sth, $column, $variable, $length);
        }

        return oci_bind_by_name($this->sth, $column, $variable);
    }

    public function bindColumn($column, &$param, $type = null, $maxlen = null, $driverdata = null)
    {
        // this defining by name doesn't seem to work when used in this manner,
        // so we will store the types bound here and handle type conversion in fetch
        if (!is_null($type)) {
            $this->bindTypeMap[$column] = $type;
        }

        return oci_define_by_name($this->sth, $column, $param, null);
    }

    public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR)
    {
        return $this->bindParam($parameter, $value, $data_type, null);
    }

    public function execute(array $input_parameters = null)
    {
        // clear bound column types
        $this->bindTypeMap = array();

        if ($input_parameters) {
            $hasZeroIndex = array_key_exists(0, $input_parameters);
            foreach ($input_parameters as $key => $val) {
                if ($hasZeroIndex && is_numeric($key)) {
                    $this->bindValue($key + 1, $val);
                } else {
                    $this->bindValue($key, $val);
                }
            }
        }

        $ret = @oci_execute($this->sth, $this->conn->getExecuteMode());
        if (!$ret) {
            $error = oci_error($this->sth);
            throw new \Exception($error['message'], $error['code']);
        }

        return $ret;
    }

    public function errorCode()
    {
        $error = oci_error($this->sth);
        if ($error !== false) {
            $error = $error['code'];
        }

        return $error;
    }

    public function errorInfo()
    {
        return oci_error($this->sth);
    }

    public function setAttribute($attribute, $value)
    {
    }

    public function getAttribute($attribute)
    {
    }

    public function columnCount()
    {
        return oci_num_fields($this->sth);
    }

    public function getColumnMeta($column)
    {
    }

    public function setFetchMode($mode)
    {
        $this->defaultFetchMode = $mode;

        return true;
    }

    public function nextRowset()
    {
    }

    public function closeCursor()
    {
        return oci_free_statement($this->sth);
    }

    public function fetch($fetch_style = null, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        $fetchMode = $fetch_style ?: $this->defaultFetchMode;
        // binding doesn't currently seem to work here
//        if (PDO::FETCH_BOUND === $fetchMode)
//        {
//            return oci_fetch($this->sth);
//        }

        if (!isset(self::$fetchModeMap[$fetchMode])) {
            throw new \InvalidArgumentException("Invalid fetch style: " . $fetchMode);
        }

        $row = oci_fetch_array($this->sth, self::$fetchModeMap[$fetchMode] | OCI_RETURN_NULLS | OCI_RETURN_LOBS);

        // must do type conversion here from previously bound types
        if ((\PDO::FETCH_BOUND === $fetchMode) && !empty($this->bindTypeMap)) {
            foreach ($this->bindTypeMap as $column => $binding) {
                if (!is_null($binding) && isset($row[$column])) {
                    switch ($binding) {
                        case \PDO::PARAM_BOOL:
                            $row[$column] = (!!$row[$column]);
                            break;
                        case \PDO::PARAM_INT:
                            $row[$column] = intval($row[$column]);
                            break;
                        case \PDO::PARAM_STR:
                            $row[$column] = strval($row[$column]);
                            break;
                    }
                }
            }
        }

        return $row;
    }

    public function fetchColumn($column_number = 0)
    {
        $row = oci_fetch_array($this->sth, OCI_NUM | OCI_RETURN_NULLS | OCI_RETURN_LOBS);

        return isset($row[$column_number]) ? $row[$column_number] : false;
    }

    public function fetchAll($fetch_style = null, $fetch_argument = null, array $ctor_args = null)
    {
        $fetchMode = $fetch_style ?: $this->defaultFetchMode;
        if (!isset(self::$fetchModeMap[$fetchMode])) {
            throw new \InvalidArgumentException("Invalid fetch style: " . $fetchMode);
        }

        $result = array();
        if (self::$fetchModeMap[$fetchMode] === OCI_BOTH) {
            while ($row = $this->fetch($fetchMode)) {
                $result[] = $row;
            }
        } else {
            $fetchStructure = OCI_FETCHSTATEMENT_BY_ROW;
            if ($fetchMode == \PDO::FETCH_COLUMN) {
                $fetchStructure = OCI_FETCHSTATEMENT_BY_COLUMN;
            }

            oci_fetch_all(
                $this->sth,
                $result,
                0,
                -1,
                self::$fetchModeMap[$fetchMode] | OCI_RETURN_NULLS | $fetchStructure | OCI_RETURN_LOBS
            );

            if ($fetchMode == \PDO::FETCH_COLUMN) {
                $result = $result[0];
            }
        }

        return $result;
    }

    public function fetchObject($class_name = "stdClass", array $ctor_args = null)
    {
        return oci_fetch_object($this->sth);
    }

    public function rowCount()
    {
        return oci_num_rows($this->sth);
    }

    public function getIterator()
    {
        $data = $this->fetchAll();

        return new \ArrayIterator($data);
    }
}
