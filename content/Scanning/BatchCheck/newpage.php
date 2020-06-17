<?php 
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../common/sqlconnect/SQLManager.php');
}

class newpage extends PageLayoutA 
{
    
    protected $title = "Batch Check";
    protected $ui = TRUE;
    protected $options = array(
        0 => 'Unchecked',
        1 => 'Good',
        2 => 'Miss',
        4 => 'Add',
        5 => 'Shelf-Tag',
        6 => 'Cap-Signs',
        9 => 'Disco/Supplies Last',
        11 => 'Edited',
        98 => 'DNC',
        99 => 'Main Menu',
    );
    protected $batchColors = array(
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
        $dbc = scanLib::getConObj();
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
        $dbc = scanLib::getConObj();
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
        $dbc = scanLib::getConObj();
        $storeID = scanLib::getStoreID();
        if ($queue == 6) {
            list($in_str, $args) = $dbc->safeInClause(array(6,7,8));
            $args[] = $storeID;
            $args[] = $session;
            $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.batchCheckQueues
                WHERE inQueue IN ($in_str) AND storeID = ? AND session = ?");
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
            $count = ($counts[$v]) ? "[{$counts[$v]}]" : "";
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
HTML;
    }

    public function getView()
    {
        if (!$_SESSION['sessionName'])  {
            return $this->getLoginView(); 
        }
        $dbc = scanLib::getConObj();
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
                    INNER JOIN FloorSectionsListView AS f ON p.upc=f.upc AND p.store_id=f.storeID 
                    LEFT JOIN StoreBatchMap AS sbm ON b.batchID=sbm.batchID AND p.store_id=sbm.storeID
                    LEFT JOIN woodshed_no_replicate.batchCheckQueues AS q ON bl.upc=q.upc
                        AND q.storeID=p.store_id AND q.session=?
                WHERE bl.batchID IN (SELECT b.batchID FROM batches AS b WHERE NOW() BETWEEN startDate AND endDate)
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

        // get product information
        $cols = array('upc', 'brand', 'description', 'cost',
            'ubrand', 'udesc', 'size', 'last_sold');
        $prep = $dbc->prepare("
            SELECT p.*, u.brand AS ubrand, u.description AS udesc, 
                p.size, DATE(p.last_sold) AS last_sold
            FROM products AS p
                LEFT JOIN productUser AS u ON p.upc=u.upc
            WHERE p.upc IN ($in_str)");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            foreach ($cols as $col) {
                $data[$upc][$col] = $row[$col];
            }
        }

        // get floorsections
        list($in_str, $args) = $dbc->safeInClause($this->upcs);
        $args[] = $storeID;
        $prep = $dbc->prepare("
            SELECT v.upc, v.sections AS name 
            FROM FloorSectionsListView AS v
            WHERE v.upc IN ($in_str)
                AND v.storeID = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $section = $row['name'];
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
        $prep = $dbc->prepare("SELECT upc, salePrice, b.batchName, b.batchType, b.batchID
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
            if ($row['upc'] != null) {
                $batchID = $row['batchID'];
                $upc = $row['upc'];
                $queues = '';
                $extraData = "
[lastsold] {$row['last_sold']}\r\n
[batch] {$row['batchName']}\r\n
[size] {$row['size']}\r\n
[pos] {$row['brand']}\r\n
[pos] {$row['description']}\r\n
[sign] {$row['ubrand']}\r\n
[sign] {$row['udesc']}\r\n
                ";
                foreach ($row['queues'] as $queued) {
                    $queues .= $queued[0].", ";
                }
                $upcLink = "<a href=\"http://{$_SERVER['HTTP_HOST']}/git/fannie/item/ItemEditorPage.php?searchupc=$upc\" target=\"_blank\">{$upc}</a>";
                $note = ($row['note']) ? $row['note'] : '';
                $noteChr = ($note != '') ? ' <i style="color: white; border-radius: 50%; background: purple; padding: 1px; font-weight: bold;">+n</i>' : '';
                $dealCol = $this->batchColors[$row['batchType']];
                $dealDot = "<span style='background-color: $dealCol; border-radius: 50%; 
                        display: inline-block; height: 5px; width: 5px;'>&nbsp</span>";
                $dealDot .= ($this->storeBatchMap[$batchID] == 1) ? " <span style='background-color: blue; transform: rotateY(0deg) rotate(45deg);
                        display: inline-block; height: 5px; width: 5px;'>&nbsp</span>" : '';
                $td .= sprintf("
                    <tr data-lastsold=\"%s\">
                    <td width='110px;'>%s</td>
                    <td>%s</td>
                    <td data-note='%s' title='%s'>%s</td>
                    <td data-extra=\"%s\" title=\"%s\">%s %s</td>
                    <td>%s</td>
                    <td class=\"queue-list\">%s</td>
                    <td><button id='queue%s' value='1' class='queue-btn btn btn-info btn-sm'>Good</button></td>
                    <td><button id='queue%s' value='2' class='queue-btn btn btn-warning btn-sm'>Miss</button></td>
                    <td><button id='queue%s' value='99' class='queue-btn btn btn-secondary btn-sm'>DNC</button></td>
                    <td class=\"tc-unch\"><button id='queue%s' value='0' class='queue-btn btn btn-default btn-sm'>Unch</button></td>
                    <td class=\"tc-clear\"><button id='queue%s' value='%s' class='queue-btn btn btn-danger btn-sm'>Clear</button></td>
                    </tr>",
                    $row['last_sold'],
                    $upcLink,
                    $brand = ($row['ubrand']) ? $row['ubrand'] : $row['brand'],
                    $note, $note, $desc = ($row['udesc']) ? $row['udesc'] . $noteChr : $row['description'] . $noteChr,
                    $extraData, $extraData, $row['salePrice'], $dealDot,
                    $row['floorsection'], 
                    $queues,
                    $row['upc'],$row['upc'],$row['upc'],$row['upc'],$row['upc'],$queue
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

JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
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
