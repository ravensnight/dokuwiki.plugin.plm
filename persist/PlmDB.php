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

    private function fetchSingle(string $queryString, array $params) : array {
        $pdo = $this->db()->getPdo();

        $stmt = $pdo->prepare($queryString);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    private function fetchAll(string $queryString, array $params): array
    {
        $pdo = $this->db()->getPdo();

        $stmt = $pdo->prepare($queryString);
        $stmt->execute($params);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function findProduct(string $name) : ?Product {

        $result = $this->fetchSingle("SELECT * FROM plm_product WHERE product_id = :product_id", [
            'product_id' => $name
        ]);

        if ($result) {

            /** @var Product */
            $res = new Product(
                $result['id'], $result['product_id']
            );

            $res->description = $result['description'];
            return $res;
        }

        return null;
    }

    /**
     * @return ProductVariant
     */
    public function findVariant(string $name) : ?ProductVariant {

        $result = $this->fetchSingle("SELECT * FROM plm_product_variant WHERE variant_id = :variant_id", [
            'variant_id' => $name
        ]);

        if ($result) {
        
            /** @var ProductVariant */
            $res = new ProductVariant(
                $result['id'], $result['variant_id'], $result['product_id']
            );

            $res->description = $result['description'];

            // ---- add subitems ----
            $subItems = $this->fetchAll("SELECT * FROM plm_product_variant_item WHERE variant_id= :variant_pk ", [
                'variant_pk' => $res->key->id
            ]);

            if ($subItems) {
                $res->subItems = [];
                foreach ($subItems as $row) {
                    /** @var ItemRef */
                    $ref = new ItemRef($row['id'], $row['version_id']);
                    $ref->quantity = $row['quantity'];

                    $res->subItems[] = $ref;
                }
            }

            return $res;
        } 
        
        return null;
    }

    /**
     * @return Part
     */
    public function findPart(string $ipn) : ?Part {

        /** @var string */
        $q = "SELECT p.id, p.ipn, p.description, c.name AS category_name FROM  plm_part p LEFT JOIN  plm_categories c ON p.category_id = c.id WHERE p.ipn = :ipn;";
        $result = $this->fetchSingle($q, [ 'ipn' => $ipn ]);

        if ($result) {

            /** @var Part */
            $res = new Part(
                $result['id'], $result['ipn']
            );

            $res->category = $result['category_name'];
            $res->description = $result['description'];
            return $res;
        }

        return null;
    }

    public function findPartVersion(string $versionId) : ?PartVersion {
        /** @var string */
        $q = "SELECT pv.id, pv.part_id, pv.major, pv.revision, pv.version_id, s.name AS status_name FROM  plm_part_version pv LEFT JOIN  plm_status s ON pv.status_id = s.id WHERE pv.version_id = :version_id;";
        $result = $this->fetchSingle($q, ['version_id' => $versionId]);

        if ($result) {

            /** @var PartVersion */
            $res = new PartVersion(
                $result['id'],
                $result['version_id'],
                $result['part_id']
            );

            $res->major = $result['major'];
            $res->revision = $result['revision'];
            $res->status = $result['status_name'];

            // ---- add subitems ----
            $subItems = $this->fetchAll("SELECT * FROM plm_part_item WHERE parent_version_id = :version_pk ", [
                'version_pk' => $res->key->id
            ]);

            if ($subItems) {
                $res->subItems = [];
                foreach ($subItems as $row) {
                    /** @var ItemRef */
                    $ref = new ItemRef($row['id'], $row['child_version_id']);
                    $ref->quantity = $row['quantity'];
                    $ref->designators = $row['designators'];

                    $res->subItems[] = $ref;
                }
            }

            return $res;
        }

        return null;
    }
}
