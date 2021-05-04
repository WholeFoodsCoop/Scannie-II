
<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class BatchHistory extends PageLayoutA
{

    protected $must_authenticate = true;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $dbc = scanLib::getConObj();
        $LIMIT = (FormLib::get('limit', false)) ? FormLib::get('limit') : 100;
        $prep = $dbc->prepare("SELECT b.*, p.brand, p.description, u.name AS user
            FROM batchUpdate AS b
                LEFT JOIN products AS p ON b.upc=p.upc
                LEFT JOIN Users AS u ON u.uid=b.user
            GROUP BY batchUpdateID
            ORDER BY batchUpdateID DESC 
            LIMIT $LIMIT");
        $res = $dbc->execute($prep);
        $td = '';
        $th = "<th>batchUpdateID</th><th>updateType</th><th>upc</th><th>specialPrice</th><th>batchID</th><th>batchType</th><th>modified</th><th>user</th><th>startDate</th><th>endDate</th><th>batchName</th><th>owner</th><th>quantity</th><th>brand</th><th>description</th>";
        while ($row = $dbc->fetchRow($res)) {
            $td .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>",
                $row['batchUpdateID'],
                $row['updateType'],
                $row['upc'],
                $row['specialPrice'],
                $row['batchID'],
                $row['batchType'],
                $row['modified'],
                $row['user'],
                $row['startDate'],
                $row['endDate'],
                $row['batchName'],
                $row['owner'],
                $row['quantity'],
                $row['brand'],
                $row['description']
            );
        }

        return <<<HTML
<h2>Batch History</h2>
<form name="batchHistory">
    <label>Limit</label>
    <input type="number" name="limit" value="$LIMIT"/>
</form>
<div class="table-responsive"><table class="table table-bordered table-sm small">$th$td</table></div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('input[type="number"]').change(function(){
    document.forms['batchHistory'].submit();
});
function stripeTable(){
    var i = 0;
    $('tr').each(function(){
        $(this).css('background', 'white');
    });
    $('tr').each(function(){
        if ( $(this).is(':visible') ) {
            if (i % 2 != 0) {
                $(this).css('background', '#FEF7E2');   
            }
            i++;
        }
    });
};
stripeTable();
JAVASCRIPT;
    }

    public function cssContent()
    {
    }

    public function helpContent()
    {
        return <<<HTML
<h5>Batch History</h5>
<p>The Batch History page returns every batch related transaction as the 
history is recorded.  This page can take several minutes to load, especially 
when requesting more than 100 rows of batch activity.</p>
<p>Rows are sorted from newest to oldest</p>
HTML;
    }

}
WebDispatch::conditionalExec();
