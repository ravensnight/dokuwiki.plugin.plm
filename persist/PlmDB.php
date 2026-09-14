<?php

use dokuwiki\ErrorHandler;
use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\sqlite\SQLiteDB;

class PlmDB {

    /** @var SQLiteDB $_db */
    private $_db;

    public function __construct() {
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

    public function fetchSingle(string $queryString, ?array $params = null) : array {
        $pdo = $this->db()->getPdo();

        $stmt = $pdo->prepare($queryString);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
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
}
