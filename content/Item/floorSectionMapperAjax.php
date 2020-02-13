<?php
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('scanLib')) {
    include_once(__DIR__.'/../../common/lib/scanLib.php');
}
if (!class_exists('FormLib')) {
    include_once(__DIR__.'/../../common/lib/FormLib.php');
}
class ajaxRequest
{
    static public function processShiftClick()
    {
        $dbc = scanlib::getConObj();
        $floorSectionID = FormLib::get("floorSectionID");
        $upc = FormLib::get("upc");

        $args = array($upc, $floorSectionID);
        $prep = $dbc->prepare("SELECT upc FROM FloorSectionProductMap WHERE upc = ? AND floorSectionID = ?");
        $res = $dbc->execute($prep, $args);
        $numRows= $dbc->numRows($res);
        if ($numRows == 0) {
            $args = array($upc, $floorSectionID);
            $prep = $dbc->prepare("INSERT INTO FloorSectionProductMap (upc, floorSectionID) VALUES (?, ?)");
            $res = $dbc->execute($prep, $args);
        } else {
            $args = array($upc, $floorSectionID);
            $prep = $dbc->prepare("DELETE FROM FloorSectionProductMap WHERE upc = ? AND floorSectionID = ?");
            $res = $dbc->execute($prep, $args);
        }
        echo "shift + click";

        return false;
    }

    static public function processClick()
    {
        $dbc = scanlib::getConObj();
        $floorSectionID = FormLib::get("floorSectionID");
        $upc = FormLib::get("upc");
        $storeID = FormLib::get("storeID");

        //get floor sections for this storeID
        $fsIDs = array();
        $args = array($storeID);
        $prep = $dbc->prepare("SELECT FloorSectionID FROM FloorSections WHERE storeID = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $fsIDs[] = $row['FloorSectionID'];
        }

        //delete all floor sections for this upc for this store
        list($inClause, $args) = $dbc->safeInClause($fsIDs);
        $args[] = $upc;
        $prep = $dbc->prepare("DELETE FROM FloorSectionProductMap 
            WHERE FloorSectionID IN ($inClause) AND upc = ?");
        $res = $dbc->execute($prep, $args);

        $args = array($upc, $floorSectionID);
        $prep = $dbc->prepare("INSERT INTO FloorSectionProductMap (upc, floorSectionID) VALUES (?, ?)");
        $res = $dbc->execute($prep, $args);
        echo "left click";

        return false;
    }

    static public function getRequest()
    {
        $shift = FormLib::get('shift');
        if ($shift == '1') {
            self::processShiftClick();
        } elseif($shift == '0') {
            self::processClick();
        } else {
        }

        return false;
    }
}
ajaxRequest::getRequest();
