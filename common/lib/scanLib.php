<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Community Co-op.

    This file is a part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file LICENSE along with CORE-POS; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

/**
*   @class scanLib
*   common methods included in all Scannie pages.
*/
class scanLib
{

    public function dateDistance($date)
    {
        $date = new DateTime($date);
        $today = new DateTime();
        $interval = $today->diff($date);

        return abs($interval->format('%R%a'));
    }

    public static function getConObj($db="SCANDB")
    {
        include(__DIR__.'/../../config.php');
        if (!class_exists('SQLManager')) {
            include_once(__DIR__.'/../sqlconnect/SQLManager.php');
        }
        $dbc = new SQLManager($SCANHOST, 'pdo_mysql', ${$db}, $SCANUSER, $SCANPASS);

        return $dbc;
    }

    public static function getPosDB()
    {
        include(__DIR__.'/../../config.php');
        include(__DIR__.'/../../../git/IS4C/common/SQLManager.php');
        $dbc = new SQLManager($FANNIE_PLUGIN_SETTINGS['SMSHost'], 'pdo_sqlsrv', 'STORESQL', $FANNIE_PLUGIN_SETTINGS['SMSUser'], $FANNIE_PLUGIN_SETTINGS['SMSPassword']);

        return $dbc;
    }

    public function getDbcError($dbc)
    {
        if (!$er = $dbc->error()) {
            return false;
        } else {
            return "<div class='alert alert-danger'>{$er}</div>";
        }
    }

    /*
        @strGetDate: parse a str for date in Y-d-m
        format.
        @param $str string to parse.
        Return $str with past/currnet dates(Y-m-d) encapsulated
        in span.text-danger.
    */
    public function strGetDate($str)
    {
        $curTimeStamp = strtotime(date('Y-m-d'));
        $pattern = "/\d{4}\-\d{2}\-\d{2}/";
        preg_match_all($pattern, $str, $matches);
        foreach ($matches as $array) {
            foreach ($array as $v) {
                $thisTimeStamp = strtotime($v);
                if ($curTimeStamp >= $thisTimeStamp) {
                    $str = str_replace($v,'<span class="text-danger">'.$v.'</span>',$str);
                }
            }
        }
        return $str;
    }

    public function readStdin()
    {
        $this->read_stdin();
        return false;
    }

    public function stdin($msg)
    {
        self::read_stdin($msg);
        return false;
    }

    public function read_stdin($msg)
    {
        /**
        *	@read_stdin()
        *	Read input from command line.
        */
        echo $msg . ': ';
        $fr = fopen("php://stdin","r");
        $input = fgets($fr,128);
        $input = rtrim($input);
        fclose($fr);
        return $input;

    }

    public function check_date_downwards($year,$month,$day)
    {

        /**
        *   @function: check_date_downwards
            @purpose: In a table, take a datetime and return
            stylized table data with warning colors.
                dates < 1 month return with normal <td> color
                dates > 1 > 2 month return yellow
                dates > 2 > 3 months return orange,
                dates > 3 months return red
            @params: The year, month and date to compare
            against the current datetime.
            @returns: Table data contents
            e.g. '<td>'.[DATETIME].'</td>';
        */

        $ret = '';
        $date = $year . '-' . $month . '-' . $day;
        $curY = date('Y');
        $curM = date('m');
        $curD = date('d');
        if (($year == $curY) && ($month <= ($curM - 1)) && ($month >= ($curM-2))) {
            $ret .= "<td style='color:#ffd500'>" . $date . "</td>";
        } elseif (($year == $curY) && ($month < ($curM - 2))) {
            $ret .= "<td style='color:orange'>" . $date . "</td>";
        } elseif (($year < $curY) or ($month < ($curM - 3)) or ($month < $curM && $day < $curD)) {
            $ret .= "<td style='color:red'>" . $date . "</td>";
        } else {
            $ret .= "<td style='color:green'>" . $date . "</td>";
        }

        return $ret;
    }

    public function check_date_downwards_alert($year,$month,$day)
    {

        /**
        *   @function: check_date_downwards_alert
            @purpose: In a table, take a datetime and return
            stylized table data with warning colors.
                dates < 1 month return with normal <td> color
                dates > 1 > 2 month return yellow
                dates > 2 > 3 months return orange,
                dates > 3 months return red
            @params: The year, month and date to compare
            against the current datetime.
            @returns: <td> contents and alert level as array.
            e.g.
                'td' = '<td>'.[DATETIME].'</td>';
                'alert' = 0
        */

        $ret = '';
        $date = $year . '-' . $month . '-' . $day;
        $curY = date('Y');
        $curM = date('m');
        $curD = date('d');
        if (($year == $curY) && ($month <= ($curM - 1)) && ($month >= ($curM-2))) {
            $ret .= "<td style='color:#ffd500'>" . $date . "</td>";
            $data['alert'] = 1;
        } elseif (($year == $curY) && ($month < ($curM - 2))) {
            $ret .= "<td style='color:orange'>" . $date . "</td>";
            $data['alert'] = 2;
        } elseif (($year < $curY) or ($month < ($curM - 3)) or ($month < $curM && $day < $curD)) {
            $ret .= "<td style='color:red'>" . $date . "</td>";
            $data['alert'] = 3;
        } else {
            $ret .= "<td style='color:green'>" . $date . "</td>";
            $data['alert'] = 0;
        }

        $data['td'] = $ret;

        return $data;
    }

    public function dateAdjust($adjDay,$adjMonth,$adjYear)
    {
        /**
        *   Takes the current date and reduce (d,m,y) by values in argument.
        *   Returns the desired date in DATETIME format.
        */

        $curY = date('Y') - $adjYear;
        $curM = date('m') - $adjMonth;
        $curD = date('d') - $adjDay;

        $date = $curY . '-' . $curM . '-' . $curD;

        return $date;
    }

    public function getStoreID()
    {
        $remote_addr = $_SERVER['REMOTE_ADDR'];
        if(substr($remote_addr,0,2) == '10') {
            $store_id = 2;
        } else {
            $store_id = 1;
        }

        return $store_id;
    }

    public function getStoreName($storeID)
    {
        switch ($storeID) {
            case 1:
                return 'Hillside';
            case 2:
                return 'Denfeld';
            case 999:
                return 'UNKNOWN';
        }
    }

    public function StoreSelector($selectName="storeID", $onChange="", $current)
    {
        $data = array();
        $data['html'] = "<select id=\"$selectName\" name=\"$selectName\" class=\"form-control\" onChange=\"$onChange\">";
        $dbc = self::getConObj();

        $prep = $dbc->prepare("SELECT storeID, description FROM Stores");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $storeID = $row['storeID'];
            $desc = $row['description'];
            $data['stores'][$storeID] = $desc;
            $sel = ($storeID == $current) ? ' selected ' : '';
            $data['html'] .= "<option val=\"$storeID\" $sel>$desc</option>";
        }
        $data['html'] .= "</select>";

        return $data;
    }

    public function convert_unix_time($secs) {
        $bit = array(
            'y' => $secs / 31556926 % 12,
            'w' => $secs / 604800 % 52,
            'd' => $secs / 86400 % 7,
            'h' => $secs / 3600 % 24,
            'm' => $secs / 60 % 60,
            's' => $secs % 60
            );

        foreach($bit as $k => $v)
            if($k == 's') {
                $ret[] = $v;
            } else {
                $ret[] = $v . ':';
            }
            if ($v == 0) $ret[] = '0';

        return join('', $ret);
    }

    public function getUser()
    {
        if (!empty($_COOKIE['user_name'])) {
            return $_COOKIE['user_name'];
        } else {
            return false;
        }
    }

    public function isDeviceIpod()
    {
        $device = $_SERVER['HTTP_USER_AGENT'];
        if (strstr($device,'iPod')) {
            return true;
        }
        return false;
    }

    /**
      Zero-padd a UPC to standard length
      @param $upc string upc
      @return standard length upc
    */
    static public function padUPC($upc)
    {
        return self::upcParse($upc);
    }

    public function upcPreparse($str)
    {
        $str = str_pad($str, 13, 0, STR_PAD_LEFT);
        if (substr($str,2,1) == '2') {
            /* UPC is for a re-pack scale item. */
            $str = '002' . substr($str,3,4) . '000000';
        } elseif (1) {

        }
        return $str;
    }

    public static function upcParse($str)
    {
        $rstr = str_replace(" ","",$str);

        $split = array();
        if (strstr($str,"-")) {
            $split = preg_split("/[-]+/",$str);
        }
        $count = count($split);
        foreach ($split as $v) {
            if ($count == 4) {
                $rstr = $split[0].$split[1].$split[2];
            } elseif ($count == 2) {
                $rstr = $split[0].substr($split[1],0,5);
            }
        }

        if (strlen($rstr) != 13) {
            $rstr = str_pad($rstr, 13, 0, STR_PAD_LEFT);
        }

        return $rstr;
    }

    public function scanBarcodeUpc($upc)
    {
        $upc = substr($upc,0,-1);
        $upc = self::upcParse($upc);
        return $upc;
    }

    public function specialBrandStrFix($inStr)
    {
        $outStr = $inStr;
        // ucwords must be called before special case is checked, make sure special case strings are in ucwords format
        $specialCase = array('Mn', 'Wi', 'Tvp', 'Tsp', 'Bbq', 'Wfc', 'R.w.', 'Ncg', 'J.r.', 'Cbd', 'Iq', 'I.v.');
        foreach ($specialCase as $search) {
            if (strpos(strtolower($inStr), strtolower($search)) !== false) {
                $outStr = str_replace($search, strtoupper($search), $inStr) ;
            }
        }

        return $outStr;
    }

    public function getRecentPurchase($dbc, $upc)
    {
        $args = array($upc);
        $prep = $dbc->prepare("SELECT
            sku, internalUPC, brand, description, DATE(receivedDate) AS receivedDate,
            caseSize, receivedTotalCost AS cost,
            unitCost, ROUND(receivedTotalCost/caseSize,3) AS mpcost
            FROM PurchaseOrderItems WHERE internalUPC = ?
                AND unitCost > 0
            ORDER BY receivedDate DESC
            limit 1");
        $result = $dbc->execute($prep,$args);
        $options = array();
        $row = $dbc->fetch_row($result);
        $unitCost = (isset($row['unitCost'])) ? $row['unitCost'] : 0;
        $received = (isset($row['receivedDate'])) ? $row['receivedDate'] : 0;

        return array($unitCost, $received);
    }

    public function getRawSrp($cost, $margin) {
        $srp = $cost / (1 - ($margin * 0.01));

        return $srp;
    }

    public function getRoundSrp($price) {
        $rounder = new pricerounder();
        $ans = $rounder->round($price);

        return $ans;
    }

    public function getScaleData($dbc, $upc)
    {
        $bycount = null;
        $args = array($upc);
        // WHEN bycount = 0 THEN 'Random'
        // WHEN bycount = 1 THEN 'Fixed'
        $prep = $dbc->prepare("SELECT
            CASE
                WHEN weight = 0 THEN 'Random'
                WHEN weight = 1 THEN 'Fixed'
                ELSE 'not in scale'
            END AS bycount
            FROM scaleItems
            WHERE plu = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $value = $row['bycount'];
            $bycount = ($value > -1) ? $value : 5;
        }
        echo $dbc->error();

        return $bycount;
    }
    
    public function getPriceFromDate($dbc, $upc, $date)
    {

        $args = array($upc, $date);
        $prep = $dbc->prepare("SELECT price FROM prodUpdate WHERE upc = ? AND DATE(modified) <= ? ORDER BY modified DESC limit 1;");
        $res = $dbc->execute($prep, $args);
        $price = $dbc->fetchRow($res);
        $price = $price['price'];

        return $price;

    }

    public static function getTextInLastDelimiter($instr, $delimiter)
    {
        $lines = explode("\n", $instr);

        $strs = array();
        $i = 0;
        foreach ($lines as $line) {
            $line = trim($line, $delimiter);
            $offset = 0;
            $string = '';
            while ($pos = strpos($line, $delimiter, $offset)) {
                $str = substr($line, $pos, strlen($line));
                $strs[$i] = str_replace($delimiter, "", $str);
                $offset = $pos+1;
            }
            $i++;
        }

        return $strs;
    }

}
