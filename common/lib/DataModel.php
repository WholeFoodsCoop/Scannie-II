<?php
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../sqlconnect/SQLManager.php');
}
class DataModel 
{
    protected $connection = null;

    function __construct($dbc)
    {
        $this->connection = $dbc;
    }

    public function setSku($vendorID, $sku=null, $upc=null, $value=null)
    {
        $field = null;
        $args = array();
        $args[] = $value;
        if ($value != null) {
            $field = "sku";
            $args[] = $sku;
        } elseif ($upc > 0) {
            $field = "upc";
            $args[] = $upc;
        }
        $args[] = $vendorID;
        $query = "UPDATE vendorItems SET sku = ? WHERE $field = ? AND vendorID = ?";
        $prep = $this->connection->prepare($query);
        $res = $this->connection->execute($prep, $args);
        if ($er = $this->connection->error()) {
            return $er;
        }

        return true;
    }

    public function setBrand($upc, $brand, $table='products')
    {
        $tableName = null;
        if ($table == 'products') {
            $tableName = 'products';
        } elseif ($table == 'productUser') {
            $tableName = 'productUser';
        }
        if ($tableName == null) {
            return false;
        } else {
            $args = array($brand, $upc);
            $query = "UPDATE $tableName SET brand = ? WHERE upc = ?";
            $prep = $this->connection->prepare($query);
            $res = $this->connection->execute($prep, $args);
            if ($er = $this->connection->error()) {
                return $er;
            }
        }

        return true;
    }

    public function setDescription($upc, $description, $table='products')
    {
        $tableName = null;
        if ($table == 'products') {
            $tableName = 'products';
        } elseif ($table == 'productUser') {
            $tableName = 'productUser';
        }
        if ($tableName == null) {
            return false;
        } else {
            $args = array($description, $upc);
            $query = "UPDATE $tableName SET description = ? WHERE upc = ?";
            $prep = $this->connection->prepare($query);
            $res = $this->connection->execute($prep, $args);
            if ($er = $this->connection->error()) {
                return $er;
            }
        }

        return true;
    }
}
