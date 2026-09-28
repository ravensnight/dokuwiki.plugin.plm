<?php

use dokuwiki\ErrorHandler;
use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\sqlite\SQLiteDB;

class PlmDB {

    /** @var SQLiteDB $_db */
    private $_db;

    /** @var PlmDB $_db */
    private static $_plm = null;

    private function __construct() {
    }

    public static function get() : PlmDB {
        if (PlmDB::$_plm === null) {
            PlmDB::$_plm = new PlmDB();
        }

        return PlmDB::$_plm;
    }

    private function db() : SQLiteDB {
        if ($this->_db !== null) {
            return $this->_db;
        }

        return $this->init();
    }

    private function init() : SQLiteDB {

        if (plugin_isdisabled('sqlite')) {
            throw new Exception('Plugin sqlite is disabled or not installed.');
        }

        $this->_db = new SQLiteDB('plmdb', DOKU_PLUGIN . 'plm/db');
        return $this->_db;
    }

    public function querySingle(string $queryString, ?array $params = null) : array {

        try {
            $pdo = $this->db()->getPdo();

            $stmt = $pdo->prepare($queryString);
            $stmt->execute($params);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result === false) {
                return [];
            }
        } catch (Exception $e) {
            throw new Exception('Query contained an error: ' . $queryString . '. Parameters: ' . json_encode($params) . 'Error: ' . $e->getMessage());
        }


        return $result;
    }

    public function fetchAll(string $queryString, ?array $params = null): array
    {
        $pdo = $this->db()->getPdo();

        $stmt = $pdo->prepare($queryString);
        $stmt->execute($params);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * Get the last inserted ID from the database for a specific table
     */
    public function lastInsertedId(string $tableName): int
    {
        $pdo = $this->db()->getPdo();
        return $pdo->lastInsertId($tableName);
    }
}
