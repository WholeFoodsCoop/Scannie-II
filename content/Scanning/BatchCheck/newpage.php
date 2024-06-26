<?php if (!class_exists('PageLayoutA')) { include(__DIR__.'/../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../common/sqlconnect/SQLManager.php');
}

class newpage extends PageLayoutA 
{
    
    public $title = "Batch Check";
    public $ui = TRUE;
    protected $connect = true;
    protected $options = array(
        0 => 'Unchecked',
        1 => 'Good',
        2 => 'Miss',
        4 => 'Add',
        5 => 'Shelf-Tag',
        6 => 'Cap-Signs',
        9 => 'Disco/Supplies Last',
        11 => 'Edited',
        97 => 'Notes',
        98 => 'DNC',
        99 => 'Main Menu',
    );
    protected $batchColors = array( 
        0 => 'white',
        1 => 'green',
        2 => 'orange',
        11 => 'red',
        12 => 'red'
    );
    protected $batches = array();
    protected $storeBatchMap = array();
    protected $storeID = 1;
    protected $upcs; 

    public function preprocess()
    {
        $session = FormLib::get('session');
        if (FormLib::get('queue') == 99) {
            header("location: newMenu.php?session=$session");
        }
        $this->displayFunction = $this->getView();
        $this->__routes[] = 'get<test>';
        $this->__routes[] = 'get<login>';

        return parent::preprocess();
    }

    public function getLoginView()
    {
        session_unset();
        $dbc = $this->connect;
        $FANNIE_ROOTDIR = $this->config->vars['FANNIE_ROOTDIR'];
        $storeID = scanLib::getStoreID();
        $sessions = ''; 
        $args = array($storeID);
        $prep = $dbc->prepare("SELECT session FROM woodshed_no_replicate.batchCheckQueues WHERE storeID = ? GROUP BY session;");
        $res = $dbc->execute($prep,$args);
        while ($row = $dbc->fetchRow($res)) {
            $s = $row['session'];
            $sessions .= "<option value='$s'>$s</option>"; 
        }

        return <<<HTML
<div align="center">
    <form name="login" id="login" method="post" action="SCS.php">
        <div class="row">
            <div class="col-lg-4"></div>
            <div class="col-lg-4">
                <h3>Batch Check Sign-In</h3>
                <p>Please select a Session & Store ID or
                    create a new Session</p>
                <div class="form-group">
                    <select class="form-control" name="resumeSession" id="session">
                        <option value="0">Resume a Session</option>
                        $sessions
                    </select>
                    - or - 
                    <input class="form-control" name="newSession" type="text" placeholder="Name a New Session">
                </div>
                <div class="form-group">
                    <input type="hidden" name="storeID" value="$storeID">
                </div>
                <div class="form-group">
                    <button type="submit" name="loginSubmit" value="1" class="btn btn-default">Submit</button>
                </div>
            </form>    
            <form action="http://$FANNIE_ROOTDIR/item/ProdLocationEditor.php" method="get">
                <div class="form-group">
                    <input type="hidden" name="start" value="CURRENT">
                    <input type="hidden" name="end" value="CURRENT">
                    <input type="hidden" name="store_id" value="$storeID">
                </div>
            </form>
            </div>
            <div class="col-lg-4"></div>
        </div>
</div>
HTML;

    }

    private function getQueueCount()
    {
        $dbc = $this->connect;
        $storeID = scanLib::getStoreID();
        $session = FormLib::get('session', false);
        $args = array($session, $storeID);
        $prep = $dbc->prepare(" 
            SELECT 
                SUM(CASE WHEN inQueue = 1 THEN 1 ELSE 0 END) AS Good,
                SUM(CASE WHEN inQueue = 2 THEN 1 ELSE 0 END) AS Miss,
                SUM(CASE WHEN inQueue = 4 THEN 1 ELSE 0 END) AS Addd,
                SUM(CASE WHEN inQueue = 5 THEN 1 ELSE 0 END) AS Shelf,
                SUM(CASE WHEN inQueue IN (6,7,8) THEN 1 ELSE 0 END) AS Cap,
                SUM(CASE WHEN inQueue IN (9,10) THEN 1 ELSE 0 END) AS Disco,
                SUM(CASE WHEN inQueue = 98 THEN 1 ELSE 0 END) AS dnc,
                SUM(CASE WHEN inQueue = 11 THEN 1 ELSE 0 END) AS edited
            FROM woodshed_no_replicate.batchCheckQueues 
            WHERE session = ?
                AND storeID = ?");
        $res = $dbc->execute($prep, $args);
        $data = array();
        while ($row = $dbc->fetchRow($res)) {
            $data[1] = $row['Good'];
            $data[2] = $row['Miss'];
            $data[4] = $row['Addd'];
            $data[5] = $row['Shelf'];
            $data[6] = $row['Cap'];
            $data[9] = $row['Disco'];
            $data[98] = $row['dnc'];
            $data[11] = $row['edited'];
        }
        if ($er = $dbc->error()) echo $er;

        return $data;
    }

    private function getSampleUpcs($queue, $session)
    {
        $dbc = $this->connect;
        $storeID = scanLib::getStoreID();
        if ($queue == 6) {
            list($in_str, $args) = $dbc->safeInClause(array(6,7,8));
            $args[] = $storeID;
            $args[] = $session;
            $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.batchCheckQueues
                WHERE inQueue IN ($in_str) AND storeID = ? AND session = ?");
            $res = $dbc->execute($prep, $args);
        } elseif (in_array($queue, array(9,10))) {
            list($in_str, $args) = $dbc->safeInClause(array(9,10));
            $args[] = $storeID;
            $args[] = $session;
            $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.batchCheckQueues
                WHERE inQueue IN ($in_str) AND storeID = ? AND session = ?");
            $res = $dbc->execute($prep, $args);
            
        } elseif ($queue == 97) {
            $args = array($storeID, $session);
            $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.batchCheckNotes
                WHERE storeID = ? AND session = ?");
            $res = $dbc->execute($prep, $args);
        } else {
            $args = array($storeID, $session, $queue);
            $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.batchCheckQueues
                WHERE storeID = ? AND session = ? AND inQueue = ?");
            $res = $dbc->execute($prep, $args);
        }
        $upcs = array();
        while ($row = $dbc->fetchRow($res)) {
            $upcs[] = $row['upc'];
        } 

        return $upcs;
    }

    private function formContent($dbc)
    {

        $storeID = scanLib::getStoreID();
        $queue = FormLib::get('queue', false);
        $session = FormLib::get('session', false);
        if ($session == false) $session = $_SESSION['sessionName'];
        $queueOpts = '';
        $sessionOpts = '';

        $counts = array();
        $counts = $this->getQueueCount();
        foreach ($this->options as $v => $o) {
            $sel = ($queue == $v) ? 'selected' : '';
            $count = (isset($counts[$v])) ? "[{$counts[$v]}]" : "";
            $queueOpts .= "<option value=\"$v\" $sel>$o $count</option>";
        }
        $args = array($storeID);
        $prep = $dbc->prepare("SELECT session FROM woodshed_no_replicate.batchCheckQueues 
            WHERE storeID = ? GROUP BY session");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $curSession = $row['session'];
            $sel = ($session == $curSession) ? 'selected' : '';
            $sessionOpts .= "<option val=\"$curSession\" $sel>$curSession</option>";
        }
        
        return <<<HTML
<form name="queue-form" class="form-inline">
    <div class="form-group">
        <select name="queue" id="queue" class="form-control form-control-sm">$queueOpts</select>
    </div>
    <div class="form-group">
        <select name="session" id="session" class="form-control form-control-sm">$sessionOpts</select>
    </div>
    <input type="hidden" id="storeID" value="$storeID"/>
    <input type="hidden" id="sessionName" value="$session"/>
    <input type="hidden" id="curQueue" value="$queue"/>
    <span id="hide-old-btn" class="inactive btn btn-default btn-sm" onclick="hideOld(); return false;">Hide <i>Old</i></span>
</form>
<div style="border-radius: 2.5px; padding: 5px; margin: 5px; max-width: 700px; border: 1px solid lightgrey;">
    <div style="text-align:center">
        <div>SHIFT + LEFT CLICK table cell to select column</div>
        <label>Column Selected: </label>
        <span id="column-filter-selected" style=" padding: 2.5px; border: 1px solid grey; border-radius: 5px;">None</span>
        <label for="column-filter-input">Search: </label>
        <span id="column-filter-input" style=" padding: 2.5px; border: 1px solid grey; border-radius: 5px;" contentEditable=true>Type to filter rows</span>
    </div>
</div>
HTML;
    }

    public function getView()
    {
        if (!isset($_SESSION['sessionName']))  {
            return $this->getLoginView(); 
        }
        $dbc = $this->connect;
        $queue = FormLib::get('queue');
        $session = FormLib::get('session');
        $this->storeID = $storeID = scanLib::getStoreID();
        $upcStr = '';
        $ret = '';
        $ret .= '<div id="loading" style="font-weight: bold; 
            padding: 5px; background: lightgrey;" align="center">LOADING</div>';
        $data = array();
        $batches = array();
        if ($queue == 0) {
            $query = "
                SELECT bl.upc
                FROM batchList AS bl 
                    LEFT JOIN products AS p ON bl.upc=p.upc 
                    LEFT JOIN productUser AS pu ON p.upc=pu.upc 
                    LEFT JOIN batches AS b ON bl.batchID=b.batchID 
                    LEFT JOIN FloorSectionsListView AS f ON p.upc=f.upc AND p.store_id=f.storeID 
                    LEFT JOIN StoreBatchMap AS sbm ON b.batchID=sbm.batchID AND p.store_id=sbm.storeID
                    LEFT JOIN woodshed_no_replicate.batchCheckQueues AS q ON bl.upc=q.upc
                        AND q.storeID=p.store_id AND q.session=?
                WHERE bl.batchID IN (SELECT b.batchID FROM batches AS b WHERE NOW() BETWEEN startDate AND endDate AND discountType > 0)
                    AND p.store_id = ?
                    AND p.inUse = 1
                    AND sbm.storeID = ?
                GROUP BY p.upc 
                ORDER BY f.sections";
            $args = array($session, $storeID, $storeID);
            $prep = $dbc->prepare($query);
            $res = $dbc->execute($prep, $args);
            while ($row = $dbc->fetchRow($res)) {
                $this->upcs[] = $row['upc'];
                $upcStr .= $row['upc']."\r\n";
            }
            if ($er = $dbc->error()) $ret .= $er;
            $ret .= "<input type=\"hidden\" id=\"unchecked_queue\"
                name=\"unchecked_queue\" value=1>";
            //end
        } else {
            $this->upcs = $this->getSampleUpcs($queue, $session);
            foreach($this->upcs as $upc) {
                $upcStr .= $upc."\r\n";
            }
        }

        list($in_str, $args) = $dbc->safeInClause($this->upcs);
        $args[] = $storeID;

        // get product information
        $cols = array('upc', 'brand', 'description', 'cost',
            'ubrand', 'udesc', 'size', 'last_sold');
        $prep = $dbc->prepare("
            SELECT p.*, u.brand AS ubrand, u.description AS udesc, 
                p.size, DATE(p.last_sold) AS last_sold,
                CASE 
                    WHEN p.last_sold IS NULL THEN DATEDIFF(NOW(), p.created)
                    ELSE
                    CASE 
                        WHEN DATEDIFF(NOW(), p.last_sold) > 0 THEN DATEDIFF(NOW(), p.last_sold)
                        ELSE 9999
                    END
                END AS daysWOsale,
                CASE
                    WHEN p.last_sold IS NULL THEN 'created'
                    ELSE 'last_sold'
                END AS daysWOtype 
            FROM products AS p
                LEFT JOIN productUser AS u ON p.upc=u.upc
            WHERE p.upc IN ($in_str)
            AND store_id = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            foreach ($cols as $col) {
                $data[$upc][$col] = $row[$col];
                $data[$upc]['queues'] = null;
                $data[$upc]['note'] = null;
                $data[$upc]['daysWOsale'] = $row['daysWOsale'];
            }
        }

        // get floorsections
        list($in_str, $args) = $dbc->safeInClause($this->upcs);
        $args[] = $storeID;
        $prep = $dbc->prepare("
            SELECT v.upc, v.sections AS name, s.subsection,
                GROUP_CONCAT(fs.name, ' [', s.subSection, ']' ORDER BY s.subSection ASC SEPARATOR ',') AS completeSections 
            FROM FloorSectionsListView AS v
                LEFT JOIN FloorSectionProductMap AS m
                    ON m.upc=v.upc
                LEFT JOIN FloorSubSections AS s
                    ON s.floorSectionID=m.floorSectionID
                        AND s.upc=v.upc
                INNER JOIN FloorSections AS fs
                    ON fs.floorSectionID=m.floorSectionID
                        AND fs.storeID=v.storeID
            WHERE v.upc IN ($in_str)
                AND v.storeID = ?
            GROUP BY v.upc
        ");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $fSections = $row['name'];
            $cSections = $row['completeSections'];
            $section = ($cSections != NULL) ? $cSections : $fSections;
            foreach ($cols as $col) {
                $data[$upc]['floorsection'] = $section;
            }
        }
        if ($er = $dbc->error()) echo $er;


        // get queues info 
        list($in_str, $args) = $dbc->safeInClause($this->upcs);
        $args[] = $session;
        $args[] = $storeID;
        $prep = $dbc->prepare("
            SELECT q.*
            FROM woodshed_no_replicate.batchCheckQueues AS q
            WHERE q.upc IN ($in_str)
                AND q.session = ? 
                AND q.storeID = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $inQueue = $row['inQueue'];
            $timestamp = $row['timestamp'];
            $data[$upc]['queues'][] = array($inQueue, $timestamp);
        }
        if ($er = $dbc->error()) echo $er;

        // get notes info 
        list($in_str, $args) = $dbc->safeInClause($this->upcs);
        $args[] = $session;
        $args[] = $storeID;
        $prep = $dbc->prepare("
            SELECT upc, notes
            FROM woodshed_no_replicate.batchCheckNotes
            WHERE upc IN ($in_str)
                AND session = ? 
                AND storeID = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $note = $row['notes'];
            if ($note != null) {
                $data[$upc]['note'] = $note;
            }
        }
        if ($er = $dbc->error()) echo $er;


        // get batches info 
        $cols = array('batchName', 'batchType', 'owner', 'storeBatchMapID', 'batchID');
        $prep = $dbc->prepare("
            SELECT * FROM batches AS b
                LEFT JOIN StoreBatchMap AS sbm ON b.batchID=sbm.batchID
            WHERE NOW() BETWEEN startDate AND endDate
                AND b.batchType NOT IN (4,6,8,9,13)
                AND sbm.storeID = ?"); 
        $res = $dbc->execute($prep, array($this->storeID));
        while ($row = $dbc->fetchRow($res)) {
            $batchID = $row['batchID'];
            $batches[] = $batchID;
            foreach ($cols as $col) {
                $this->batches[$batchID][$col] = $row[$col];
                //echo $row['batchID'].'<br/>';
            }
        }
        if ($er = $dbc->error()) echo $er;

        // get batchList info
        $sbmIDs = array();
        list($in_str, $args) = $dbc->safeInClause($batches);
        $prep = $dbc->prepare("SELECT upc, salePrice, b.batchName, b.batchType, b.batchID,
            b.startDate, b.endDate
            FROM batchList AS bl
                LEFT JOIN batches AS b ON bl.batchID=b.batchID
            WHERE b.batchID IN ($in_str)");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $salePrice = null;
            $salePrice = $row['salePrice'];
            $batchName = null;
            $batchName = $row['batchName'];
            $upc = null;
            $upc = $row['upc'];
            $batchType = null;
            $batchType = $row['batchType'];
            $batchID = null;
            $batchID = $row['batchID'];
            $data[$upc]['salePrice'] = $salePrice;
            $data[$upc]['batchName'] = $batchName;
            $data[$upc]['batchType'] = $batchType;
            $data[$upc]['batchID'] = $batchID;
            $data[$upc]['sbmID'] = $this->batches[$row['batchID']]['storeBatchMapID'];
            $data[$upc]['singleStoreBatch'] = 0;
            $data[$upc]['startDate'] = $row['startDate'];
            $data[$upc]['endDate'] = $row['endDate'];
            $sbmIDs[] = $this->batches[$row['batchID']]['storeBatchMapID'];
        }
        if ($er = $dbc->error()) echo $er;

        // find if batch is single store only
        foreach ($this->batches as $batchID => $row) {
            $id = $row['batchID'];
            $args = array($id);
            $prep = $dbc->prepare("SELECT * FROM StoreBatchMap WHERE batchID = ?");
            $res = $dbc->execute($prep, $args);
            $rows = $dbc->numRows($res);
            $this->storeBatchMap[$id] = $rows;
        }

        //ob_start();
        //var_dump($this->batches);
        //var_dump($data);
        //$result = ob_get_clean();
        $result = '';

        $td = '';
        foreach ($data as $upc => $row) {
            if (isset($row['upc'])) {
                $batchID = (isset($row['batchID'])) ? $row['batchID'] : 0;
                $upc = $row['upc'];
                $queues = '';
                $start = (isset($row['startDate'])) ? substr($row['startDate'], 0, 10) : '';
                $end = (isset($row['endDate'])) ? substr($row['endDate'], 0, 10) : '';

                $lastSold = (isset($row['last_sold'])) ? $row['last_sold'] : null;
                $batchName = (isset($row['batchName'])) ? $row['batchName'] : null;
                $size = (isset($row['size'])) ? $row['size'] : null;
                $brand = (isset($row['brand'])) ? $row['brand'] : null;
                $description = (isset($row['description'])) ? $row['description'] : null;
                $ubrand = (isset($row['ubrand'])) ? $row['ubrand'] : null;
                $udesc = (isset($row['udesc'])) ? $row['udesc'] : null;
                $batchType = (isset($row['batchType'])) ? $row['batchType'] : 0;
                $salePrice = (isset($row['salePrice'])) ? $row['salePrice'] : 0;
                $daysWOsale = (isset($row['daysWOsale'])) ? $row['daysWOsale'] : 0;
                $daysWOtype = (isset($row['daysWOtype'])) ? $row['daysWOtype'] : 0;
                $daysWOsaleText = '';
                $border = '';
                $symbol = "&#9608;";
                if ($daysWOsale < 20 && $daysWOsale != 0 ) {
                    $color = 'lightgreen';
                }
                if ($daysWOsale  > 19) {
                    $color = 'orange';
                }
                if ($daysWOsale  > 29) {
                    $color = 'tomato';
                }
                if ($daysWOsale  > 60) {
                    $color = 'darkred';
                }
                if ($daysWOsale == 0) {
                    $color = 'grey';
                }
                if ($daysWOsale == 1 && $daysWOtype == 'last_sold') {
                    $border = 'box-shadow: 2px 2px lightgreen';
                    $symbol = "&#9733;";
                }
                $daysWOsaleText = "<span style=\"color: $color; float: right; $border\">$symbol</span></span>";


                $extraData = <<<HTML
[lastsold]     $lastSold\r\n
[batch]        $batchName\r\n
[size]         $size\r\n
[pos]          $brand\r\n
[pos]          $description\r\n
[sign]         $ubrand\r\n
[sign]         $udesc\r\n
[start]        $start          [end] $end\r\n
HTML;
                if (isset($row['queues'])) {
                    if (is_array($row['queues'])) {
                        foreach ($row['queues'] as $queued) {
                            $queues .= $queued[0].", ";
                        }

                    }
                    
                }
                $row['floorsection'] = (isset($row['floorsection'])) ? $row['floorsection'] : '';
                $upcLink = "<a href=\"http://{$_SERVER['HTTP_HOST']}/git/fannie/item/ItemEditorPage.php?searchupc=$upc\" target=\"_blank\">{$upc}</a>";
                $note = (isset($row['note'])) ? $row['note'] : '';
                $noteChr = ($note != '') ? ' <i style="color: white; border-radius: 50%; background: purple; padding: 1px; font-weight: bold;">+n</i>' : '';
                $dealCol = $this->batchColors[$batchType];
                $dealDot = "<span style='background-color: $dealCol; border-radius: 50%; 
                        display: inline-block; height: 5px; width: 5px;'>&nbsp</span>";
                if (isset($this->storeBatchMap[$batchID])) {
                    $dealDot .= ($this->storeBatchMap[$batchID] == 1) ? " <span style='background-color: blue; transform: rotateY(0deg) rotate(45deg);
                        display: inline-block; height: 5px; width: 5px;'>&nbsp</span>" : '';
                }
                $td .= sprintf("
                    <tr data-lastsold=\"%s\" class=\"mytable\">
                    <td width='110px;'>%s</td>
                    <td data-sc=\"brand\" class=\"filter-brand\">%s</td>
                    <td data-note='%s' title='%s' data-sc=\"description\" class=\"filter-description\">%s</td>
                    <td data-extra=\"%s\" title=\"%s\" data-sc=\"deal\" class=\"filter-deal\">%s %s</td>
                    <td data-sc=\"location\" class=\"filter-location\">%s</td>
                    <td data-sc=\"queue\" class=\"queue-list filter-queue\">%s</td>
                    <td><button id='queue%s' value='1' class='queue-btn btn btn-info btn-sm'>Good</button></td>
                    <td><button id='queue%s' value='2' class='queue-btn btn btn-warning btn-sm'>Miss</button></td>
                    <td><button id='queue%s' value='99' class='queue-btn btn btn-secondary btn-sm'>DNC</button></td>
                    <td class=\"tc-unch\"><button id='queue%s' value='0' class='queue-btn btn btn-default btn-sm'>Unch</button></td>
                    <td class=\"tc-clear\"><button id='queue%s' value='%s' class='queue-btn btn btn-danger btn-sm'>Clear</button></td>
                    </tr>",
                    $lastSold,
                    $upcLink,
                    $brand = ($ubrand != null) ? $ubrand : $brand,
                    $note, $note, $desc = ($udesc != null) ? $udesc . $noteChr : $description . $noteChr,
                    $extraData, $extraData, $salePrice, $dealDot,
                    $row['floorsection'] . '' . $daysWOsaleText, 
                    $queues,
                    $upc, $upc, $upc, $upc, $upc, $queue
                );
            }
        }
        $this->addScript('../../../common/javascript/tablesorter/js/jquery.tablesorter.min.js');
        $this->addScript('../../../common/javascript/tablesorter/js/jquery.metadata.js');
        $this->addOnloadCommand("$('#product-list').tablesorter({theme:'bootstrap', headerTemplate: '{content} {icon}', widgets: ['uitheme','zebra']});");
        $timestamp = time();
        $this->addScript('scs.js?time='.$timestamp);
        $this->addScript('newpage.js');

        return <<<HTML
<div class="container-fluid" style="margin-top: 15px;">
    {$this->formContent($dbc)}
    $ret
    $result
    <div id="extra-content"></div>
</div>
<div class="table-responsive">
    <table id="product-list" class="table table-bordered table-sm small tablesorter tablesorter-bootstrap">
        <thead><th>upc</th><th>Brand</th><th>Description</th><th>Deal</th>
            <th>Location</th><th title="[Legend] DISC => Discontinued, SL => Supplies Last, ST => Shelf-Tag">Queues</th>
            <th></th><th></th><th class="tc-unch"></th><th class="tc-clear"></th></thead>
        <tbody>$td</tbody>
    </table>
</div>
<div class="container-fluid" style="margin-top: 15px;">
    <div class="form-group">

        <div style="position: relative">
            <span class="status-popup" style="display: none;">Copied!</span>
        <textarea id="textarea" class="copy-text" rows=5 cols=15 style="border: 1px solid lightgrey">$upcStr</textarea>
        </div>
    </div>
    <div id="bottom-content"></div>
</div>
HTML;
    }

    public function getTestView()
    {
        return <<<HTML
well hello, there!
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var stripeTable = function(){
    $('tr.mytable').each(function(){
        $(this).removeClass('stripe');
    });
    $('tr.mytable').each(function(i = 0){
        if ($(this).is(':visible')) {;
            if (i % 2 == 0) {
                $(this).addClass('stripe');
            } else {
                $(this).removeClass('stripe');
            }
        i++;
        }
    });

    return false;
};
stripeTable();
//var stripeRows = function()
//{
//    var i = 0;
//    $('tr').each(function(){
//        if (!$(this).parent('thead').is('thead')) {
//            i++;
//            if (i % 2 == 0) {
//                $(this).css('background-color', 'white');
//            } else {
//                $(this).css('background-color', '#FEF7E2');
//            }
//        }
//    });
//}
//stripeRows();
//var hideOld = function()
//{
//    var thisBtn = $('#hide-old-btn');
//    var text = thisBtn.text();
//    if (thisBtn.hasClass('inactive')) {
//        $('tr[data-age]').each(function(){
//            if ($(this).attr('data-age') == 'old' || $(this).attr('data-age') == 'ancient') {
//                $(this).hide();
//            };
//        });
//        thisBtn.removeClass('inactive')
//            .addClass('active');
//        text = text.replace('Hide', 'Show');
//    } else {
//        $('tr[data-age]').each(function(){
//            if ($(this).attr('data-age') == 'old' || $(this).attr('data-age') == 'ancient') {
//                $(this).show();
//            };
//        });
//        thisBtn.removeClass('active')
//            .addClass('inactive');
//        text = text.replace('Show', 'Hide');
//    }
//    thisBtn.text(text);
//    stripeRows();
//}
//// click or touch any cell with data-note
//$('td[data-note]').click(function(){
//    var note = $(this).attr('data-note');
//    if (note != '') {
//        alert('NOTE: '+note);
//    }
//});
//$('td[data-extra]').click(function(){
//    var extra = $(this).attr('data-extra');
//    alert(extra);
//});
//// add sale 'age' to tr based on most recent sale dates
//$('tr[data-lastsold]').each(function(){
//    var lastsold = $(this).attr('data-lastsold');
//    var tdate = new Date(lastsold);
//    var year = tdate.getFullYear();
//    var month = tdate.getMonth()+1;
//    var day = tdate.getDate()+1;
//
//    var check1 = new Date();
//    check1.setMonth(check1.getMonth() - 2);
//    var cy = check1.getFullYear();
//    var cm = check1.getMonth()+1;
//    var cd = check1.getDate()+1;
//    
//    var check2 = new Date();
//    check2.setMonth(check2.getMonth() - 12);
//    var cy = check2.getFullYear();
//    var cm = check2.getMonth()+1;
//    var cd = check2.getDate()+1;
//
//    var date1 = year+'-'+month+'-'+day;
//    var date2 = cy+'-'+cm+'-'+cd;
//
//    year = parseInt(year,10);
//    cy = parseInt(cy,10);
//    if (tdate < check2) {
//        $(this).find('td:first-child').css('background', 'tomato');
//        $(this).attr('data-age', 'ancient');
//    } else if (tdate < check1) {
//        $(this).find('td:first-child').css('background', 'yellow');
//        $(this).attr('data-age', 'old');
//    }
//});
//
//var queue_names = {'98':'DNC', '11':'Ed', '10':'SL', '9':'DISC', '8':'TWOUP', '7':'FOURUP', 
//    '6':'TWELVEUP', '5':'ST', '4':'Add', '2':'Miss', '1':'Good'};
//$('#queue').change(function(){
//    document.forms['queue-form'].submit();
//});
//$('#session').change(function(){
//    document.forms['queue-form'].submit();
//});
//var unchecked = $('#unchecked_queue').val();
//if (unchecked != undefined) {
//    $('#product-list tr').each(function(){
//        var queue_list = $(this).find('.queue-list').text();
//        if (queue_list.includes('1') || queue_list.includes('2')) {
//            $(this).closest('tr').hide();
//        } else {
//        }
//    });
//    $('.tc-rm').each(function(){
//        $(this).hide();
//    });
//}
//var queue_list = '';
//$('#product-list tr').each(function(){
//    var cur_tr = $(this);
//    for (var i=99; i>0; i--) {
//        if (queue_names[i] != undefined) {
//            queue_list = cur_tr.find('.queue-list').text();
//            var replace_str = queue_list.replace(new RegExp(i, 'gi'), queue_names[i]);
//            cur_tr.find('td.queue-list').text(replace_str);
//        };
//    };
//    var num_rpl = {'TWELVE':12, 'FOUR':4, 'TWO':2};
//    $.each(num_rpl, function(k, v){
//        queue_list = cur_tr.find('.queue-list').text();
//        var replace_str = queue_list.replace(new RegExp(k, 'gi'), v);
//        cur_tr.find('td.queue-list').text(replace_str);
//    });
//});
//$(document).ready(function(){
//    $('#loading').hide();
//});
//$("table").bind("sortStart",function() { 
//    stripeRows();
//}).bind("sortEnd",function() { 
//    stripeRows();
//}); 


$('body').append('<input type="hidden" id="keydown" />');

$(document).keydown(function(e){
    var key = e.keyCode;
    $('#keydown').val(key);
});
$(document).keyup(function(e){
    var key = e.keyCode;
    $('#keydown').val(0);
});
$(document).mousedown(function(e){
    if (e.which == 1 && $('#keydown').val() == 16) {
        e.preventDefault();
        //console.log('shift click');
        var target = $(e.target);
        let attr = target.attr('data-sc');
        if (typeof attr != 'undefined' && attr !== false) {
            let text = target.attr('data-sc');
            //console.log(text);
            $('#column-filter-selected').text(text);
        }
        $('#keydown').val(0);
    } else if (e.which == 1) {
        //console.log('left click');
    }
});

$('#column-filter-input').keyup(function(){
    $('tr').each(function(){
        $(this).show();
    });
    var text = $(this).text().toUpperCase();
    var column = $('#column-filter-selected').text();
    $('td.filter-'+column).each(function(){
        var contents = $(this).text();
        contents = contents.toUpperCase();
        if (contents.includes(text)) {
            $(this).closest('tr').show();
        } else {
            $(this).closest('tr').hide();
        }
    });
});

$('#column-filter-input').focusout(function(){
    let text = $(this).text();
    if (text.length == 0) {
        $(this).text('Enter String To Filter');
    }
});
$('#column-filter-input').click(function(){
    $(this).select();
});

JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
.stripe {
    background-color: #FFFACD;
}
.queue-btn {
    width: 50px;
}
.status-popup {
    display: none;
    position: absolute;
    top: 0px;
    left: 0px;
    background: white;
    padding: 5px;
    font-weight: bold;
    border-top-left-radius: 5px;
    border-top-right-radius: 5px;
    border-bottom-right-radius: 5px;
    border-style: solid solid solid solid;
    border-color: grey;
    border-width: 1px;
    box-shadow: 1px 1px slategrey;
}
HTML;
    }

}
WebDispatch::conditionalExec();
