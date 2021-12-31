<?php
/*******************************************************************************
    Copyright 2016 Whole Foods Community Co-op.

    This file is a part of Scannie.

    Scannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
    Scannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    in the file LICENSE along with Scannie; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../common/sqlconnect/SQLManager.php');
}
class ProductScanner extends PageLayoutA 
{

    protected $title = "Product Scanner";
    protected $description = "[Product Scanner] is a light-weight, all around product
        scanner for use with iUnfi iPod Touch scanners.";
    protected $ui = false;
    protected $connect = true;
    protected $use_preprocess = TRUE;
    protected $must_authenticate = TRUE;
    protected $enable_linea = true;

    public function preprocess()
    {

        $username = scanLib::getUser();
        $dbc = $this->connect;
        if (!$username) {
            header('location: ../../../auth/Login.php');
        }

        $action = FormLib::get('action');
        echo $action;
        $upc = FormLib::get('upc');
        $upc = scanLib::upcPreparse($upc);

        if ($action == 'mod-narrow') {
            $this->mod_narrow_handler($upc);
            die();
        } elseif ($action == 'mod-in-use') {
            $this->mod_inuse_handler($upc);
            die();
        } elseif ($action == 'mod-edit') {
            $this->mod_edit_handler($upc);
            die();
        } elseif (FormLib::get('delete_mapID', false) !== false) {
            $this->delete_mapID_handler();
            die();
        } elseif (FormLib::get('mapID', false) !== false) {
            $this->mapID_handler();
            die();
        } 

        if (isset($_POST['note'])) {
            $error = $this->notedata_handler($dbc);
            if (!$error) {
                header('location: ProductScanner.php?success=true');
            } else {
                header('location: ProductScanner.php?success=false');
            }
        }

    }

    private function delete_mapID_handler()
    {
        $dbc = $this->connect;
        $mapID = FormLib::get('mapID');

        $prep = $dbc->prepare("DELETE FROM FloorSectionProductMap 
            WHERE floorSectionProductMapID = ?");
        $res = $dbc->execute($prep, array($mapID));

        return false;
    }

    private function mapID_handler()
    {
        $dbc = $this->connect;
        $mapID = FormLib::get('mapID');
        $upc = FormLib::get('upc');
        $floor_section = FormLib::get('floor_section');

        if ($mapID != 'create_new_mapID') {
            $args = array($floor_section, $mapID);
            $prep = $dbc->prepare("UPDATE FloorSectionProductMap 
                SET floorSectionID = ? WHERE floorSectionProductMapID = ?");
            $res = $dbc->execute($prep, $args);
        } else {
            $getmaxP = $dbc->prepare("SELECT MAX(floorSectionProductMapID) + 1 AS maxid
                FROM FloorSectionProductMap");
            $getmaxR = $dbc->execute($getmaxP);
            $row = $dbc->fetchRow($getmaxR);
            $maxid = $row['maxid'];

            $args = array($maxid, $upc, $floor_section);
            $prep = $dbc->prepare("INSERT INTO FloorSectionProductMap
                (floorSectionProductMapID, upc, floorSectionID)
                VALUES (?, ?, ?)");
            $res = $dbc->execute($prep, $args);
        }

        return false;
    }

    private function mod_edit_handler($upc)
    {
        $dbc = $this->connect;
        $table = FormLib::get('table');
        $column = FormLib::get('column');
        $newtext = FormLib::get('newtext');
        $column = preg_replace('/[0-9]+/', '', $column);

        $args = array($newtext, $upc);
        $query = "UPDATE $table SET $column = ? WHERE upc = ?";
        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep, $args);

        return false;
    }

    private function mod_narrow_handler($upc)
    {
        $dbc = $this->connect;
        $args = array($upc);
        $prep = $dbc->prepare("SELECT upc FROM productUser WHERE upc = ? AND narrow = 1");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $narrow = $row['upc'];
        }
        echo $narrow;
        if ($narrow > 0) {
            $prep = $dbc->prepare("UPDATE productUser SET narrow = 0 WHERE upc = ?");
            $res = $dbc->execute($prep, $args);
        } else {
            $prep = $dbc->prepare("UPDATE productUser SET narrow = 1 WHERE upc = ?");
            $res = $dbc->execute($prep, $args);
        }

        return false;
    }

    private function mod_inuse_handler($upc)
    {
        $dbc = $this->connect;
        $store = scanLib::getStoreID();
        $args = array($upc, $store);
        $prep = $dbc->prepare("SELECT inUse FROM products WHERE upc = ? AND store_id = ?;");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $inUse = $row['inUse'];
        }
        echo "\n";
        if ($inUse == 0) {
            $prep = $dbc->prepare("UPDATE products SET inUse = 1 WHERE upc = ? AND store_id = ?");
            $res = $dbc->execute($prep, $args);
            echo "Product now IN-use";
        } else {
            $prep = $dbc->prepare("UPDATE products SET inUse = 0 WHERE upc = ? AND store_id = ?");
            $res = $dbc->execute($prep, $args);
            echo "Product now NOT in-use";
            $args = array($upc, $store);
            $prep = $dbc->prepare("INSERT INTO woodshed_no_replicate.exceptionItems (upc, note, timestamp, storeID)
                VALUES (?, 'Item un-used', NOW(), ?)");
            $res = $dbc->execute($prep, $args);
        }

        return false;
    }

    public function pBar($weekPar,$deptNo,$storeID,$dbc)
    {
        if ($_SESSION['audieDept'] != $deptNo) {
            $args = array($storeID,$deptNo);
            $multiplier = ($storeID == 1) ? 3 : 7;
            $query = "
                SELECT auto_par, auto_par*$multiplier as par, upc, brand, description
                FROM products
                WHERE store_id = ?
                    AND department = ?
                ORDER BY auto_par DESC
                LIMIT 1";
            $prep = $dbc->prepare($query);
            $res = $dbc->execute($prep,$args);
            while ($row = $dbc->fetchRow($res)) {
                $max = $row['par'];
            }
        } else {
            $max = $_SESSION['maxPar'];
        }
        $_SESSION['audieDept'] = $deptNo;
        $_SESSION['maxPar'] = $max;

        $percent = 100*($weekPar/$max);
        $oppo = $max-$percent;
        return <<<HTML
<div align="center" id="pBar" style="height: 1px;" id="auditPar">
    <div class="progress" style="width: 100px; height: 11px;">
        <div class="progress-bar progress-bar-success" role="progressbar" style="width:{$percent}%;"></div>
        <div class="progress-bar progress-bar-default" role="progressbar" style="width:{$oppo}%; "></div>
    </div>
</div>
HTML;
    }

    private function notedata_handler($dbc)
    {
        $ret = '';
        $upc = FormLib::get('upc');
        $note = FormLib::get('note');
        $username = FormLib::get('username');
        $args = array($note,$upc,$username);
        $query = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan
            SET notes = ? WHERE upc = ? AND username = ?;");
        $result = $dbc->execute($query,$args);
        $error = 0;
        if ($dbc->error()) {
            $error = 1;
        }

        if ($dbc->affectedRows()) {
            $error = 0;
        } else {
            $error = 2;

        }

        return false;

    }

    public function body_content()
    {

        $ret = '';
        $MY_ROOTDIR = $this->config->vars['MY_ROOTDIR'];
        $FANNIE_ROOTDIR = $this->config->vars['FANNIE_ROOTDIR'];
        $dbc = scanLib::getConObj('SCANALTDB');
        $p = $dbc->prepare("SELECT * FROM ScannieConfig WHERE session_id = ?");
        $r = $dbc->execute($p, session_id());
        $scannerConfig = array();
        $cols = array('scanBeep', 'auditPar', 'auditCost', 'auditSrp',
            'auditProdInfo', 'auditVendorInfo', 'auditSize', 'auditSignInfo',
            'auditSaleInfo', 'auditLocations', 'socketDevice');
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) {
                $scannerConfig[$col] = $row[$col];
            }
        }
        $beep = $scannerConfig['scanBeep'];
        if ($beep == true) {
            $this->addOnloadCommand("
                WebBarcode.Linea.emitTones(
                    [
                        { 'tone':100, 'duration':100 },
                    ] 
                );
            ");
        }
        foreach ($_SESSION['ScannieConfig']['AuditSettings'] as $setting => $value) {
            if ($value == false) {
                $this->addOnloadCommand("$('#$setting').hide();");
            }
        }
        $dbc = $this->connect;
        $username = scanLib::getUser();
        $response = (isset($_GET['success'])) ? $_GET['success'] : false;
        $newscan = $_POST['success'];
        if ($response && $newscan != 'empty') {
            if ($response == TRUE) {
                $ret .= '<div align="center" id="note-resp" class="alert alert-success" style="posotion: fixed; top: 0; left: 0; ">
                    Saved! <span style="font-size: 14px; font-weight: bold; float: right; cursor: pointer;" onclick="$(\'#note-resp\').hide(); return false;"> &nbsp;x </span>
                    </div>';
            } elseif ($response == FALSE) {
                $ret .= '<div align="center" id="note-resp" class="alert alert-danger">
                    Error Saving <span style="font-size: 14px; font-weight: bold; float: right; cursor: pointer;" onclick="$(\'#note-resp\').hide(); return false;"> &nbsp;x </span>
                    </div>';
            }
        }

        include(__DIR__.'/../../../common/lib/PriceRounder.php');
        $rounder = new PriceRounder();
        $storeID = scanLib::getStoreID();
        $upc = FormLib::get('upc');
        $isSocketDevice = $scannerConfig['socketDevice'];
        if ($isSocketDevice != 0 ) {
            // do something if scanner is socket mobile device
            //&& FormLib::get('isSocketDevice') != 1
            if (strlen($upc) == 12) {
                $upc = substr($upc, 0, -1);
            }
        }
        $upc = scanLib::upcPreparse($upc);
        if ($upc == 0) {
            //return $this->form_content($isSocketDevice);
        }

        $loading = '
            <div class="progress" id="progressBar">
                <div class="progress-bar progress-bar-striped active" role="progressbar"
                    aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width:100%">
                </div>
            </div>
        ';

        $uid = '<span class="userSymbol-plus"><b>'.strtoupper(substr($username,0,1)).'</b></span>';

        $ret .= $uid;
        $ret .= $this->mobile_menu($upc);
        $ret .= $loading;
        $ret .= '<div align="center"><h4 id="heading">AUDIE: THE AUDIT SCANNER</h4></div>';
        $ret .= $this->form_content($isSocketDevice);

        //Gather product SALE information
        $saleQueryArgs = array($storeID,$upc);
        $saleQuery = $dbc->prepare("
            SELECT b.batchName, bl.salePrice, b.batchID
            FROM batches AS b
                LEFT JOIN batchList AS bl ON b.batchID=bl.batchID
                INNER JOIN StoreBatchMap AS sbm ON b.batchID=sbm.batchID
            WHERE curdate() BETWEEN b.startDate AND b.endDate
                AND sbm.storeID = ?
                AND bl.upc = ?
                AND b.batchType BETWEEN 1 AND 12
                AND b.batchType <> 4
                ;");
        $saleQres = $dbc->execute($saleQuery,$saleQueryArgs);
        $batchList = array(  );
        while ($row = $dbc->fetchRow($saleQres)) {
            $batchList['price'][] = $row['salePrice'];
            $batchList['batchID'][] = $row['batchID'];
            $batchList['batchName'][] = $row['batchName'];
        }
        $isOnSale = false;
        if (count($batchList) > 0) {
            $saleButtonClass = 'success';
            $saleStatus = '* On Sale *';
            $isOnSale = 'true';
        } else {
            $saleButtonClass = 'inverse';
            $saleStatus = 'not on sale';
        }
        if ($isOnSale == true) {
            $ret .= "<style>
                background: green;
                background-color: green;
                color: purple;
            </style>";
        }
        $saleInfoStr = '';
        if (isset($batchList['price'])) {
            foreach ($batchList['price'] as $k => $v) {
                $saleInfoStr .= '
                    <span class="sm-label">PRICE: </span>$<span class="text-sale">'.$v.'</span>
                    <span class="sm-label">ID: </span>'.$batchList['batchID'][$k].'<br />
                    <span class="sm-label">BATCH: </span>'.$batchList['batchName'][$k].'
                    <br /><br />
                    <div style="border: 1px solid lightrgba(255,255,255,0.6); width: 20vw"></div>
                    <br />
                ';
            }
        }

        //Gather product information
        $args = array($storeID,$upc);
        $query = $dbc->prepare("
            SELECT
                p.cost,
                p.normal_price,
                p.description,
                p.brand,
                p.default_vendor_id,
                p.inUse,
                p.auto_par,
                v.vendorName,
                vi.vendorDept,
                p.department,
                d.dept_name,
                p.price_rule_id,
                prt.description AS prt,
                vd.margin AS unfiMarg,
                d.margin AS deptMarg,
                pu.description AS signdesc,
                pu.brand AS signbrand,
                v.shippingMarkup,
                v.discountRate,
                fslv.sections AS locations,
                CASE when pu.narrow=1 THEN '<span class=\'badge badge-warning\'>Flagged Narrow</span>' ELSE NULL end as narrow,
                CASE when p.size is not null THEN p.size ELSE vi.size END AS size
            FROM products AS p
                LEFT JOIN productUser AS pu ON p.upc = pu.upc
                LEFT JOIN departments AS d ON p.department=d.dept_no
                LEFT JOIN vendors AS v ON p.default_vendor_id=v.vendorID
                LEFT JOIN vendorItems AS vi
                    ON p.upc = vi.upc
                        AND p.default_vendor_id = vi.vendorID
                LEFT JOIN vendorDepartments AS vd
                    ON vd.vendorID = p.default_vendor_id
                        AND vd.deptID = vi.vendorDept
                LEFT JOIN FloorSectionsListView AS fslv ON p.upc=fslv.upc AND p.store_id=fslv.storeID
                LEFT JOIN PriceRules AS pr ON p.price_rule_id=pr.priceRuleID
                LEFT JOIN PriceRuleTypes AS prt ON pr.priceRuleTypeID=prt.priceRuleTypeID
            WHERE p.store_id = ?
                AND p.upc = ?
            LIMIT 1
        ");
        $result = $dbc->execute($query,$args);
        $multiplier = ($storeID == 1) ? 3 : 7;
        while ($row = $dbc->fetchRow($result)) {
            $cost = $row['cost'];
            $price = $row['normal_price'];
            $prt = $row['prt'];
            $desc = $row['description'];
            $brand = $row['brand'];
            $vendor = '<span class="vid">id['.$row['default_vendor_id'].'] </span>'.$row['vendorName'];
            $vd = $row['default_vendor_id'].' '.$row['vendorName'];
            $dept = $row['department'].' '.$row['dept_name'];
            $deptNo = $row['department'];
            $pid = $row['price_rule_id'];
            $unfiMarg = $row['unfiMarg'];
            $deptMarg = $row['deptMarg'];
            $signDesc = $row['signdesc'];
            $signBrand = $row['signbrand'];
            $inUse = $row['inUse'];
            $narrow = $row['narrow'];
            $markup = $row['shippingMarkup'];
            $discount = $row['discountRate'];
            $locations = $row['locations'];
            $size = $row['size'];
            // Hillside multiplier = 3, Denfeld = 7
            $weekPar = $row['auto_par'] * $multiplier;
            $auto_par = $row['auto_par'];
            $weekPar = round($weekPar, 1);
            $ret .= " <div class=\"margin-top: 15px;\">&nbsp;</div>";
            $ret .= '<input type="hidden" id="auto_par_value" value="'.$weekPar.'"/>';
            //$ret .= $this->pBar($weekPar,$deptNo,$storeID,$dbc);
            $ret .= "
                <div id=\"auditPar\">
                    <table class=\"table table-borderless table-sm small\">
                        <tr><td>PAR</td><td>$weekPar</td><td><i>avg. sold in $multiplier days</i></td>
                            <td><span style=\"font-size: 10px;\">auto_par: ".round($auto_par, 1)."</span></td>
                            </tr>
                    </table>
                </div>
            ";

            $adjcost = $cost;
            if ($markup > 0) $adjcost += $cost * $markup;
            if ($discount > 0) $adjcost -= $cost * $discount;

            if ($row['default_vendor_id'] == 1) {
                $dMargin = $row['unfiMarg'];
            } else {
                $dMargin = $row['deptMarg'];
            }
        }
        if ($dbc->error()) echo $dbc->error();
        $args = array($upc, $username);
        $prep = $dbc->prepare("SELECT notes FROM woodshed_no_replicate.AuditScan WHERE upc = ?
            AND username = ? AND savedAs = 'default'");
        $res = $dbc->execute($prep, $args);
        $notes = "";
        while ($row = $dbc->fetchRow($res)) {
            $notes = $row['notes'];
        }
        if ($er = $dbc->error()) echo $er;

        $margin = ($price - $adjcost) / $price;
        $rSrp = $adjcost / (1 - $dMargin);
        $srp = $rounder->round($rSrp);
        $sMargin = ($srp - $adjcost ) / $srp;

        $sWarn = 'default';
        if ($srp != $price) {
            if ($srp > $price) {

            } else { //$srp < $price
                $peroff = $srp / $price;
                if ($peroff < .05) {
                    $sWarn = '';
                } elseif ($peroff > .15 && $peroff < .30) {
                    $sWarn = 'warning';
                } else {
                    $sWarn = 'danger';
                }
            }
        }

        $passcost = $cost;
        if ($cost != $adjcost) $passcost = $adjcost;
        $data = array('cost'=>$passcost,'price'=>$price,'desc'=>$desc,'brand'=>$brand,'vendor'=>$vd,'upc'=>$upc,
            'dept'=>$dept,'margin'=>$margin,'rsrp'=>$rSrp,'srp'=>$srp,'smarg'=>$sMargin,'warning'=>$sWarn,
            'pid'=>$pid,'dMargin'=>$dMargin,'storeID'=>$storeID,'username'=>$username);
        $ret .= $this->recordData($upc, $username, $storeID);

        $warning = array();
        $margOff = ($margin / $dMargin);
        if ($margOff > 1.05) {
            $warning['margin'] = 'info';
        } elseif ($margOff > 0.95) {
            $warning['margin'] = 'none';
        } elseif ($margOff < 0.95 && $margOff > 0.90) {
            $warning['margin'] = 'warning';
        } else {
            $warning['margin'] = 'danger';
        }

        $priceOff = ($price / $srp);
        if ($priceOff > 1.05) {
            $warning['price'] = 'info';
        } elseif ($priceOff > 0.95) {
            $warning['price'] = 'none';
        } elseif ($priceOff < 0.95 && $priceOff > 0.90) {
            $warning['price'] = 'warning';
        } else {
            $warning['price'] = 'danger';
        }

        if ($pid != 0) {
            $price_rule = '<span style="text-shadow: 0.5px 0.5px tomato; color: orange">*</span>
                <span class="text-tiny">pid</span>';
        } else {
            $price_rule = '';
        }

        if ($adjcost != $cost) {
            $adjCostStr = '<span class="text-tiny">adj cost: </span><span style="color: rgba(255,255,255,0.6); text-shadow: 0px  0px 1px white">'.sprintf('%0.2f',$adjcost).'</span>';
        } else {
            $adjCostStr = '&nbsp;';
        }
        $touchicon = "<img class=\"scanicon-pointer\" src=\"../../../common/src/img/icons/pointer-light.png\"
            style=\"margin-left: 20px; margin-top: -5px;\"/>";
        $ret .= '
            <div align="center">
                <div class="container-fluid" align="center">
                    <div class="row" id="auditCost">
                        <div class="col-4 info" >
                            <div style="float: left; color: rgba(255,255,255,0.6)">cost</div><br />'.$cost.'<br />
                                '.$adjCostStr.'
                        </div>
                        <div class="col-4 info" >
                            <div style="float: left; color: rgba(255,255,255,0.6)">price</div><br />
                                <span class="text-'.$warning['price'].'" style="font-weight: bold; font-size: 18px; text-shadow: 1px 1px darkslategrey">
                                    '.$price.'</span>
                                    '.$price_rule.'<br />&nbsp;
                        </div>
                        <div class="col-4 info" >
                            <div style="float: left; color: rgba(255,255,255,0.6)">margin</div><br /><span class="text-'.$warning['margin'].'">'.sprintf('%0.2f%%',$margin*100).'</span>
                                <br /> <span class="text-tiny">target: </span><span style="color: rgba(255,255,255,0.6); text-shadow: 0px  0px 1px white">'.($dMargin*100).'%</span>
                        </div>
                    </div>
                    <div class="row" id="auditSrp">
                        <div class="col-4 info" >
                            <div style="float: left; color: rgba(255,255,255,0.6)"> raw </div><br />'.sprintf('%0.2f',$rSrp).'
                        </div>
                        <div class="col-4 info" >
                            <div style="float: left; color: rgba(255,255,255,0.6)" class="text-'.$sWarn.'">srp</div><br />'.$srp.'
                        </div>
                        <div class="col-4 info" >
                            <div style="float: left; color: rgba(255,255,255,0.6)">newMarg</div><br />'.sprintf('%0.2f%%',$sMargin*100).'
                        </div>
                    </div>
                    <br />
                    <div id="auditPrtID">
                        <div class="row">
                            <div class="col-12 info" ><span class="sm-label">PRT-ID:</span> <span id="PrtID_v">'.$prt.'</span></div>
                        </div>
                    </div>
                    <div id="auditProdInfo">
                        <div class="row">
                            <div class="col-12 info" ><span class="sm-label">DESC:</span> <span id="description1_v">'.$desc.'</span></div>
                        </div>
                        <div class="row">
                            <div class="col-12 info" ><span class="sm-label">BRAND: </span> <span id="brand1_v">'.$brand.'</span></div>
                        </div>
                        <div class="row">
                            <div class="col-12 info" ><span class="sm-label">DEPT: </span> '.$dept.' </div>
                        </div>
                    </div>
                    <div class="row" id="auditVendorInfo">
                        <div class="col-12 info" ><span class="sm-label">VENDOR: </span> '.$vendor.' </div>
                    </div>
                    <div class="row" id="auditLocations">
                        <div class="col-12 info" ><span class="sm-label">LOCATIONS: </span> <span 
                            onclick="$(\'#floor-section-edit\').show();">'.$locations.$touchicon.'</span></div>
                    </div>
                    <div class="row" id="auditSize">
                        <div class="col-12 info" ><span class="sm-label">SIZE: </span><span id="size_v">'.$size.'</spa></span> </div>
                    </div>
                ';

                if (!$inUse) {
                    $ret .= '
                        <div id="in-use-warning">
                            <div class="col-12 info" ><span class="text-warning" style="font-weight: bold;">
                                THIS PRODUCT IS NOT IN USE
                            </span></div>
                        </div>
                    ';
                }

                $ret .= '
                    <div id="auditSignInfo">
                        <div class="row">
                            <div class="col-12 info" ><span class="sm-label sign-label">SIGN: </span> <span id="description2_v">'.$signDesc.'</span></div>
                        </div>
                        <div class="row">
                            <div class="col-12 info" ><span class="sm-label sign-label">S.BRAND: </span> <span id="brand2_v">'.$signBrand.'</span></div>
                        </div>
                        <div class="row">
                            <div class="col-12 info" >'.$narrow.'</div>
                        </div>
                    </div>
                    <div class="row" id="auditSaleInfo">
                        <div class="col-12 info" >
                                <span class="text-'.$saleButtonClass.'" style="font-weight: bold; ">'.$saleStatus.' </span>
                                <span class="caret text-'.$saleButtonClass.'"></span>
                        </div>
                    </div>
                        <div class="" id="sale-info">
                            <div class="row">
                                <div class="col-12 info">
                                    '.$saleInfoStr.'
                                </div>
                            </div>
                        </div>
                    ';
                if (strlen($notes) > 0) {
                $ret .= '
                    <div>
                        <table class="table table-borderless table-sm small">
                            <tr><td class="alert-danger" style="width: 36px;">NOTE</td>
                                <td style="text-align: center">'.$notes.'</td></tr>
                        </table>
                    </div>
                    ';
                };
                $ret .= '



                    <div class="container-fluid">
                    <br />
                    <div class="row">
                        <!-- <div class="col-4  clear btn btn-warning" onClick="queue('.$storeID.'); return false;">Print</div> -->
                        <div class="col-4 clear">
                            <form method="get" type="hidden">
                            <a href="../ScannerSettings.php" class="btn btn-info" style="width: 100%;">
                                <span class="scanicon scanicon-settings-white"></span> 
                            </a>
                            <input type="hidden" name="note" value="Print Tag" />
                            <input type="hidden" id="upc" name="upc" value="'.$upc.'" />
                        </div>
                        </form>
                        <div class="col-4  clear">
                            <button class="btn btn-danger" onclick="$(\'#notepad\').show();" style="width: 100%;">
                                <span class="scanicon scanicon-pencil-white"></span>
                            </button></div>
                        <div class="col-4  clear "><a class="btn btn-success" style="width: 100%" href="http://'.$MY_ROOTDIR.'/content/Scanning/BatchCheck/SCS.php">B.C.</a></div>
                    </div>
                    <div class="row">
                        <div class="col-4">
                            <a class="btn btn-primary" style="width: 100%; font-size: 10px" href="AuditReport.php ">Report</a>
                        </div>
                        <div class="col-4">
                        </div>
                        <div class="col-4">
                            <a class="btn btn-default" style="background: lightgrey; color: black;" href="http://'.$FANNIE_ROOTDIR.'/modules/plugins2.0/ShelfAudit/SaMenuPage.php">Menu</a>
                        </div>
                    </div>
                </div>
            </div>

            <div id="ajax-resp"></div>
        ';

        //  Get easy re-use notes for this session
        $args = array($username,$storeID);
        $prep = $dbc->prepare("SELECT notes FROM woodshed_no_replicate.AuditScan WHERE username = ? AND storeID = ?
            AND savedAs = 'default'");
        $res = $dbc->execute($prep,$args);
        $notes = array();
        while ($row = $dbc->fetchRow($res)) {
            if (!in_array($row['notes'],$notes)) {
                $notes[] = $row['notes'];
            }
        }

        //  Commonly used NOTES.
        $ret .= '
            <div id="notepad" class="collapse" >
                <div style="position: relative; top: 10%; opacity: 1;">
                    <form method="get" name="notepad" class=" " >
                        <input type="text" name="note" id="note" class="form-control" style="max-width: 90%; "><br />
                        <input type="hidden" name="upc" value="'.$upc.'">
                        <span type="" class="btn btn-danger" id="submit-note">Submit Note</span>
                    </form>
        ';

        asort($notes);
        foreach ($notes as $note) {
            if ($note != NULL) {
                $ret .= '<span class="qmBtn"  onClick="qm(\''.$note.'\'); return false; ">
                    <b>'.$note.'</b></span>';
            }
        }

        $ret .= '
                </div>
            </div>';
        $count = $this->getCount($dbc, $storeID, $username);
        $ret .= '<div class="counter"><span id="counter">'.$count.'</span></div>';

        $ret .= '<br /><br /><br /><br /><br /><br />';
        $this->addOnloadCommand("$('#progressBar').hide();");
        $timestamp = time();
        $this->addScript('productScanner.js?unique='.$timestamp);
        $ret .= "<input type='hidden' id='isOnSale' name='isOnSale' value=$isOnSale/>";
        $hiddenContent = $this->hiddenContent($upc, $narrow, $inUse);

        $ret .= "<input type=\"hidden\" id=\"username\" value=\"$username\"/>
            <input type=\"hidden\" id=\"storeID\" value=\"$storeID\"/> 
        ";

        //$this->addOnloadCommand("window.location.reload();");

        return <<<HTML
<div class="container-fluid">$ret</div>
$hiddenContent
HTML;
    }

    private function getCount($dbc,$storeID,$username)
    {
        $args = array($username,$storeID);
        $prep = $dbc->prepare("SELECT count(*) FROM woodshed_no_replicate.AuditScan
            WHERE username = ? AND storeID = ? AND savedAS = 'default'");
        $res = $dbc->execute($prep,$args);
        $count = $dbc->fetchRow($res);
        return $count[0];
    }

    private function form_content($isSocketDevice)
    {
        $autofocus = ($this->deviceType != 'mobile') ? 'autofocus' : '';
        $upc = FormLib::get('upc');
        if ($isSocketDevice != 0) {
            $upc = substr(FormLib::get('upc'), 0, -1);
        }
        $upc = ScanLib::upcPreparse($upc);
        $ret = '';
        $startupSpace = ($upc == 0) ? "<div style=\"padding-top: 25px;\"></div>" : "";
        $startupBtn = ($upc == 0) ? "<div class=\"form-group\" style=\"padding-top: 25px;\"><button class=\"btn btn-default\">Submit</button></div>" : "";
        $greeting = ($upc == 0) ? "<div style=\"padding-top: 25px;\" align=\"center\">
            <h3>Audie 2.0</h3>
            <h5>The Audit Scanner</h5>
            <h6>by Corey Sather</h6>
            <h6>&copy; Whole Foods Community Co-op 2021</h6>
            </div>" : "";
        $ret .= '
            <div align="center">
                '.$startupSpace.'
                <form method="post" class="" id="my-form" name="main_form">
                    <input class="form-control input-sm info" name="upc" id="upc" value="'.$upc.'" '.$autofocus.' 
                        style="text-align: center; width: 140px; border: none;" pattern="\d*">
                    <input type="hidden" id="sku" name="sku" />
                    <input type="hidden" name="success" value="empty"/>
                    <span id="auto_par" class="sm-label"></span><span id="par_val" class="norm-text"></span>
                    <!-- <button type="submit" class="btn btn-xs"><span class="go-icon"></span></button> -->
                    '.$startupBtn.'
                </form>
            </div>
            '.$greeting.'
        ';

        return $ret;

    }

    private function recordData($upc, $username, $storeID)
    {
        $dbc = scanLib::getConObj('SCANALTDB');
        $args = array($upc, $username, $storeID);
        if ($upc == 0)
            return false;
        $prep = $dbc->prepare("INSERT IGNORE INTO AuditScan (upc, username, storeID, date)
            VALUES (?, ?, ?, NOW())");
        $res = $dbc->execute($prep, $args);
        if ($dbc->error()) {
            return '<div class="alert alert-danger">' . $dbc->error() . '</div>';
        } else {
            return false;
        }
    }

    public function cssContent()
    {
        return <<<HTML
#in-use-warning {
    position: absolute;
    top: 50px;
    left: 45px;
    pointer-events: none;
}
tr, td {
    background: rgba(0, 0, 0, 0.1);
}
.table-borderless td,
.table-borderless th {
        border: 0;
}
.collapsing {
    -webkit-transition: none;
    transition: none;
    display: none;
}
.scanicon-trash {
    position: absolute;
    right: 15px;
}
.sections {
    background: rgba(253, 227, 167, 0.8);
    color: black;
    margin: 10px;
    padding: 10px;
}
#add-floor-section {
    background: rgba(200, 247, 197, 0.8);
    color: black;
    margin: 10px;
    padding: 10px;
}
#floor-section-edit {
    position: fixed; 
    top:0px;
    left:0px;
    background: rgba(255,255,255,0.5);
    margin: 15px;
    width: 90%;
    height: 95%;
    display: none;
}
#floor-section-edit-close {
    position: fixed;
    top:15px;
    right:155px;
    color: black;
    text-shadow: 1px 1px lightgrey;
}
.grey {
    color: grey;
}
.menu-list-space {
    background-color: rgba(0,0,0,0);
    list-style-type: none;
    height: 10px;
}
.menu-exit {
}
#menu-action {
    display: none;
    height: 100vh;
    width: 100vw;
    z-index: 999;
    //background: linear-gradient(135deg, #42a7f4, #0a1528);
    //background-color: linear-gradient(135deg, #42a7f4, #0a1528);
    background: linear-gradient(135deg, slategrey, darkslategrey);
    background-color: linear-gradient(135deg, slategrey, darkslategrey);
    position: fixed;
    top: 0px;
    left: 0px;
}
ul.menu-list {
     padding: 15px;
}
li.menu-list {
    background-color: rgba(255, 255, 255, 0.5);
    list-style-type: none;
    margin-top: 15px;
    padding: 10px;
    color: black;
    font-weight: #CACACA;
    cursor: pointer;
}
.text-xs {
    font-size: 8px;
    padding: 10px;
}
body {
    font-family: Arial, Helvetica, sans-serif;
    background-color: rgba(255,255,255,0.9);
    //background: linear-gradient(135deg, #42a7f4, #0a1528);
    //background-color: linear-gradient(135deg, #42a7f4, #0a1528);
    background: linear-gradient(135deg, slategrey, darkslategrey);
    background-color: linear-gradient(135deg, slategrey, darkslategrey);
    background-repeat: no-repeat;
    background-attachment: fixed;
    color: #cacaca;
}
.btn-mobile {
    position: fixed;
    top: 20px;
    right: 50px;
    padding: 1px;
    height: 25px;
    width: 25px;
    border: rgba(255,255,255,0.3);
    background-color: rgba(255,255,255,0.2);
    box-shadow: 1px 1px rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.4);
}
.btn-action {
    position: fixed;
    top: 15px;
    right: 15px;
    padding: 1px;
    height: 25px;
    width: 25px;
    border: rgba(255,255,255,0.3);
    background-color: rgba(255,255,255,0.2);
    box-shadow: 1px 1px rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.4);
    z-index: 255;
}
.btn-keypad {
    height: 50px;
    width: 50px;
    border: 5px solid white;
    //border-radius: 2px;
    background-color: lightgrey;
    text-align: center;
    cursor: pointer;
}
#progressBar {
    display: none;
}
#heading {
color: rgba(255,255,255,0.6);
font-size: 10px;
}
.info {
opacity: 0.9;
//background: linear-gradient(#7c7c7c,#272822);
background: rgba(39, 40, 34, .05);
border-radius: 2px;
padding: 5px;
}
body {
//background-image: url(\'../common/src/img/lbgrad.png\');
}
.vid {
color: #CACACA;
}
.clear {
opacity: 0.8;
}
#ajax-resp {
position: fixed;
top: 60;
width: 100%;
}
.fixed-resp {
position: fixed;
top: 60;
width: 100%;
}
#notepad {
position: fixed;
top: 0;
left: 0;
height: 100%;
width: 100%;
background: linear-gradient(#d99696, #d64f4f);
opacity: 0.8;
}
.qmBtn {
   background-clip: padding-box;
   padding: 5px;
   padding-top: 10px;
   border-radius: 5px;
   background-color: white;
   border: 3px solid transparent;
   //height: auto;
   //min-height: 50px;
   width: 75px;
   height: 75px;
   float: left;
   font-size: 12px;
   color: grey;
}
/*
#note-resp {
position: fixed;
top: 0;
left: 0;
height: 100%;
width: 100%;
horizonal-align: middle;
font-size: 26px;
}*/
.note-input {
background-color: #fceded;
}
.sm-label {
font-size: 10px;
color: rgba(255,255,255,0.6);
}
.sign-label {
    color: purple;
}
.text-tiny {
    font-size: 8px;
    color: #CACACA;
}
.text-sale {
color: lightgreen;
font-weight: bold;
}
.btn-msg {
width: 150px;
}
.norm-text {
font-size: 12px;
color: black;
}
.counter {
position: absolute;
top: 5;
left: 5;
width: 25;
height: 25;
font-size: 40;
font-weigth: bold;
opacity: 0.5;
}
.userSymbol-plus {
    position: absolute;
    top: 50;
    left: 8;
    padding: 5px;
    opacity: 0.5;
}
#pBar {
    opacity: 0.5;
    position: relative;
    margin-bottom: 0px;
    margin-top: -4px;
    padding: 0px;
    bottom: 27px;
}
HTML;
    }

    private function mobile_menu($upc)
    {
        $ret = '';
        $ret .= '<a href="#" id="btn-action"><button class="btn-action">
            <span class="scanicon scanicon-edit-white"></span> 
        </button></a>';
        $ret .= '
            <div class="modal" tabindex="-1" role="dialog" id="keypad">
            <br /><br /><br /><br /><br />
              <div class="" role="document">
                <div class="" >
                    <h4 class="modal-title"></h4>
                  <div class=""  align="center">

                    <table><form type="hidden" method="get">
                        <input type="hidden" name="upc" id="keypadupc" value="0" />
                        <input type="hidden" name="success" value="empty"/>
                        <div id="modal-text" style="background-color: white; width: 170px; padding: 5px; border-radius: 5px;">&nbsp;</div><br />
                        <thead></thead>
                        <tbody>
                            <tr>
                            <td class="btn-keypad" id="key7">7</td>
                                 <td class="btn-keypad" id="key8">8</td>
                                  <td class="btn-keypad" id="key9">9</td>
                            </tr><tr>
                                <td class="btn-keypad" id="key4">4</td>
                                 <td class="btn-keypad" id="key5">5</td>
                                  <td class="btn-keypad" id="key6">6</td>

                            </tr><tr>
                                <td class="btn-keypad" id="key1">1</td>
                                 <td class="btn-keypad" id="key2">2</td>
                                  <td class="btn-keypad" id="key3">3</td>

                            </tr><tr>
                                <td ></td>
                                 <td class="btn-keypad" id="key0">0</td>
                                  <td ></td>
                            </tr><tr>
                                <td class="btn-keypad btn-info" id="keyCL">CL</td>
                                 <td></td>
                                  <!-- <td><button type="button" class="btn-keypad" data-dismiss="modal" aria-label="Close"><span style="color: white; font-weight: bold">X</span></button></td> -->
                                  <!-- <td><button type="submit" onClick="formSubmitter(); return false;" class="btn-keypad btn-success">GO</button></td> -->
                                  <td onClick="formSubmitter(); return false;" class="btn-keypad btn-success">GO</td>
                            </tr>
                        </tbody>
                    </form></table>

                  </div>

                </div><!-- /.modal-content -->
              </div><!-- /.modal-dialog -->
            </div><!-- /.modal -->
        ';

        return $ret;

    }

    private function getFloorSectionSelect($sections, $cur_section, $mapID, $upc)
    {
        $select = "<select data-mapID=\"$mapID\" data-upc=\"$upc\" class=\"update-section\">";
        $cur = '';
        foreach ($sections as $fsID => $name) {
            $cur = ($cur_section == $fsID) ? 'selected' : '';
            $select .= "<option value=\"$fsID\" $cur>$name</option>"; 
        }
        $select .= "</select>";

        return $select;
    }

    private function hiddenContent($upc, $narrow, $inUse)
    {
        $storeID = scanLib::getStoreID();
        $dbc = $this->connect;

        $prep = $dbc->prepare("SELECT floorSectionID, name FROM FloorSections 
            WHERE storeID = ? ORDER BY name;");
        $res = $dbc->execute($prep, array($storeID));
        $sections_list = array();
        while ($row = $dbc->fetchRow($res)) {
            $fsID = $row['floorSectionID'];
            $name = $row['name'];
            $sections_list[$fsID] = $name;
        }

        $prep = $dbc->prepare("SELECT m.*, f.name, f.floorSectionID
            FROM FloorSectionProductMap AS m
                RIGHT JOIN FloorSections AS f ON m.floorSectionID=f.floorSectionID
                WHERE upc = ?
                    AND f.storeID = ?;");
        $res = $dbc->execute($prep, array($upc, $storeID));
        $sections = "<div style=\"color: black; font-weight: bold; text-shadow: 1px 1px lightgrey\">In Section(s)</div>";
        while ($row = $dbc->fetchRow($res)) {
            $fsID = $row['floorSectionID'];
            $mapID = $row['floorSectionProductMapID'];
            $name = $row['name'];
            $sections .= "<div id=\"sm_$mapID\" class=\"sections\">";
            $sections .= "<span data-mapID=\"$mapID\" class=\"scanicon scanicon-trash btn btn-default\">&nbsp;</span>";
            $sections .= $this->getFloorSectionSelect($sections_list, $fsID, $mapID, $upc);

            $sections .= "</div>";
        }
        $sections .= "<div style=\"color: black; font-weight: bold; text-shadow: 1px 1px lightgrey\">Add Section</div>";
        $sections .= "<div id=\"add-floor-section\">";
        $sections .= $this->getFloorSectionSelect($sections_list, $fsID, 'create_new_mapID', $upc);
        $sections .= "</div>";
        $inUse = ($inUse == 1) ? '(is in use)' : '(not in use)';
        $narrow = (strpos($narrow, 'Flagged') !== false) ? '(is narrow)' : '(is not narrow)';

        return <<<HTML
<div id="menu-action" style="margin-top: -10px; text-align: center; height: 110%;">
    <ul class="menu-list">
        <li class="menu-list" id="mod-narrow">change <b>narrow</b> status $narrow</li>
        <li class="menu-list" id="mod-in-use">change <b>in-use</b> status $inUse</li>
        <li class="menu-list edit-btn" data-table="products" data-column="brand1"><span class="grey">Edit</span> POS-Brand</li>
        <li class="menu-list edit-btn" data-table="products" data-column="description1"><span class="grey">Edit</span> POS-Description</li>
        <li class="menu-list edit-btn" data-table="products" data-column="size"><span class="grey">Edit</span> POS-Size</li>
        <li class="menu-list edit-btn" data-table="productUser" data-column="brand2"><span class="grey">Edit</span> <span class="sign-label">SIGN</span>-Brand</li>
        <li class="menu-list edit-btn" data-table="productUser" data-column="description2"><span class="grey">Edit</span> <span class="sign-label">SIGN</span>-Description</li>
        <li class="menu-list" data-table="productUser" data-column="description">
            <a href="../../">Scannie Menu</a>
        </li>
        <li class="menu-list menu-exit" id="exit-action-menu">Exit Menu</li>
    </ul>
</div>
<div id="floor-section-edit">
    <div id="floor-section-edit-close" onclick="$('#floor-section-edit').hide();">X</div>
    $sections
</div>
HTML;
    }

}
WebDispatch::conditionalExec();
