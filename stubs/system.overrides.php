<?php

/**
* DB class
*/
class DB
{
    /**
     * Constructor
     *
     * @param string $adaptor
     * @param string $hostname
     * @param string $username
     * @param string $password
     * @param string $database
     * @param int $port
     */
    public function __construct($adaptor, $hostname, $username, $password, $database, $port = \NULL)
    {
    }

    /**
     * @param string $sql
     * @return object
     */
    public function query($sql)
    {
    }
    
    /**
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
    }

    /**
     * @return int
     */
    public function countAffected()
    {
    }

    /**
     * @return int
     */
    public function getLastId()
    {
    }

    /**
     * @return bool
     */
    public function connected()
    {
    }
}

final class Registry
{
    /**
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
    }
}