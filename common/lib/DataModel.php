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

    public function getAuditReportSet($session_id)
    {
        $json = '';
        $args = array($session_id);
        $prep = $this->connection->prepare("SELECT auditReportSet FROM woodshed_no_replicate.ScannieConfig
            WHERE session_id = ?");
        $res = $this->connection->execute($prep, $args);
        while ($row = $this->connection->fetchRow($res)) {
            $json = $row['auditReportSet'];
        }

        return $json;
    }

    public function getAuditReportOpt($session_id)
    {
        $int = 0;
        $args = array($session_id);
        $prep = $this->connection->prepare("SELECT auditReportOpt FROM woodshed_no_replicate.ScannieConfig
            WHERE session_id = ?");
        $res = $this->connection->execute($prep, $args);
        while ($row = $this->connection->fetchRow($res)) {
            $int = $row['auditReportOpt'];
        }

        return $int;
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
            $brand = strtoupper($brand);
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

    public function setSize($upc, $size, $table='products')
    {
        $tableName = null;
        if ($table == 'products') {
            $tableName = 'products';
            $size = strtoupper($size);
        } elseif ($table == 'productUser') {
            $tableName = 'productUser';
        }
        if ($tableName == null) {
            return false;
        } else {
            $args = array($size, $upc);
            $query = "UPDATE $tableName SET size = ? WHERE upc = ?";
            $prep = $this->connection->prepare($query);
            $res = $this->connection->execute($prep, $args);
            if ($er = $this->connection->error()) {
                return $er;
            }
        }

        return true;
    }

    public function setUom($upc, $uom, $table='products')
    {
        $tableName = null;
        if ($table == 'products') {
            $tableName = 'products';
            $uom = strtoupper($uom);
        } elseif ($table == 'productUser') {
            $tableName = 'productUser';
        }
        if ($tableName == null) {
            return false;
        } else {
            $args = array($uom, $upc);
            $query = "UPDATE $tableName SET unitofmeasure = ? WHERE upc = ?";
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
            $description = strtoupper($description);
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

    public function setDept($upc, $dept)
    {
        $args = array($dept, $upc);
        $query = "UPDATE products SET department = ? WHERE upc = ?";
        $prep = $this->connection->prepare($query);
        $res = $this->connection->execute($prep, $args);
        if ($er = $this->connection->error()) {
            return $er;
        }

        return true;
    }

    public function setCost($upc=null, $cost=null, $vendorID=null, $user='')
    {

        if ($upc == null || $cost == null) 
            return false;

        $pinfoA = array($upc);
        $pinfoP = $this->connection->prepare("SELECT * FROM products WHERE upc = ?");
        $pinfoR = $this->connection->execute($pinfoP, $pinfoA);
        $pinfoW = $this->connection->fetchRow($pinfoR);

        if ($cost > 999) {
            echo array("Error" => "Cost limit exceeded");
            return false;
        }
        if ($cost > ($pinfoW['cost'] * 5) && $pinfoW['cost'] > 0) {
            echo array("Error" => "Cost limit exceeded");
            return false;
        }
        if (!is_numeric($cost)) {
            echo array("Error" => "Cost limit exceeded");
            return false;
        }

        $updateA = array('EDIT', $upc, $pinfoW['description'], $pinfoW['normal_price'], $pinfoW['special_price'], 
            $cost, $pinfoW['department'], $pinfoW['tax'], $pinfoW['foodstamp'], $pinfoW['wicable'], $pinfoW['scale'],
            null, $user, $pinfoW['qttyEnforced'], $pinfoW['discount'], $pinfoW['inUse']);
        $updateP = $this->connection->prepare("INSERT INTO prodUpdate (updateType, upc, description, price, salePrice, cost, dept, tax, fs, wic, scale, likeCode, modified, user, forceQty, noDisc, inUse, storeID) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,1);");
        $updateR = $this->connection->execute($updateP, $updateA);
        if ($er = $this->connection->error()) {
            return $er;
        }

        $vendorA = array($cost, $upc, $vendorID);
        $vendorP = $this->connection->prepare("UPDATE vendorItems SET cost = ? WHERE upc = ? AND vendorID = ?");
        $vendorR = $this->connection->execute($vendorP, $vendorA);
        if ($er = $this->connection->error()) {
            return $er;
        }

        $args = array($cost, $upc);
        $query = "UPDATE products SET cost = ? WHERE upc = ?";
        $prep = $this->connection->prepare($query);
        $res = $this->connection->execute($prep, $args);
        if ($er = $this->connection->error()) {
            return $er;
        }

        return true;
    }

    public function setNotes($upc, $storeID, $notes, $username)
    {
        $args = array($notes, $upc, $storeID, $username);
        $query = "UPDATE AuditScan SET notes = ? WHERE upc = ? AND storeID = ? AND username = ? AND savedAs = 'default'";
        $prep = $this->connection->prepare($query);
        $res = $this->connection->execute($prep, $args);
        if ($er = $this->connection->error()) {
            return $er;
        }

        return true;
    }

}
