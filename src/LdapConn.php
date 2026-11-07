<?php

namespace PHPMaker2024\FIMS;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Auth\BindException;
use LdapRecord\Query\Builder;

/**
 * LDAP connection class
 */
class LdapConn
{
    private bool $Auth = false;
    public Connection $Conn;
    public string $User = "";

    /**
     * Constructor
     *
     * @param array $config Configuration (see https://ldaprecord.com/docs/core/v2/configuration)
     * @return void
     */
    public function __construct(public array $Config)
    {
        $config = $this->Config;
        $config["username"] = null;
        $config["password"] = null;
        $this->Conn = new Connection($config); // Connect Anonymously first
        Container::addConnection($this->Conn); // Add the connection into LdapRecord\Container
    }

    /**
     * Bind an user
     *
     * @param string $user
     * @param string $password
     * @return bool
     */
    public function bind(&$user, &$password)
    {
        $this->User = $user;
        $username = isset($this->Config["username"]) ? str_replace("{username}", $user, $this->Config["username"]) : $user;
        try {
            $this->Auth = false; // Reset first
            $this->Conn->auth()->bind($username, $password);
            $this->Auth = $this->ldapValidated($user, $password);
        } catch (BindException $e) {
            $error = $e->getDetailedError();
            LogError($error->getErrorCode() . ": " . $error->getErrorMessage());
            if (Config("DEBUG") && ($message = $error->getDiagnosticMessage())) {
                Log($message);
            }
        }
        return $this->Auth;
    }

    /**
     * Is authenticated
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return $this->Auth;
    }

    /**
     * Get connection
     *
     * @return LdapRecord\Connection
     */
    public function getConnection()
    {
        return $this->Conn;
    }

    /**
     * Is connected
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->Conn ? $this->Conn->isConnected() : false;
    }

    /**
     * Get query builder
     *
     * @return LdapRecord\Query\Builder
     */
    public function query()
    {
        return $this->isConnected() ? $this->Conn->query() : null;
    }

    /**
     * Search
     *
     * @param string $baseDn Base DN
     * @param string $filter Filter
     * @param array $attributes Attributes
     * @return array
     */
    public function search($baseDn, $filter, $attributes = [])
    {
        if ($this->isConnected()) {
            $query = $baseDn
                ? (new Builder($this->Conn))->setCache($this->Conn->cache)->setBaseDn($baseDn)
                : $this->Conn->query();
            return $query->rawFilter($filter)->get($attributes);
        }
        return false;
    }

    /**
     * Close/Disconnect
     *
     * @return void
     */
    public function close()
    {
        if ($this->isConnected()) {
            $this->Conn->disconnect();
        }
    }

    // LDAP Validated event
    public function ldapValidated(&$usr, &$pwd)
    {
        // Do something (if any) after binding an user successfully
        return true; // Return true/false to validate the user
    }
}
