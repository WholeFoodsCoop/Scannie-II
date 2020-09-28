<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class PriceRuleTypeReport extends PageLayoutA
{

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $dbc = scanLib::getConObj();
        $cur_super = FormLib::get('superDept', false);

        $args = array($cur_super);
        $prep = $dbc->prepare("
            SELECT 
                p.upc, p.brand, p.description, t.description AS prType, p.normal_price,
                p.cost
            FROM products AS p
                LEFT JOIN PriceRules AS pr ON p.price_rule_id=pr.priceRuleID
                LEFT JOIN PriceRuleTypes AS t ON pr.priceRuleTypeID=t.priceRuleTypeID
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
            WHERE t.priceRuleTypeID > 0
                AND m.super_name = ? 
                AND p.inUse = 1
            GROUP BY p.upc
            ORDER BY t.description, p.normal_price, p.upc
        ");
        $res = $dbc->execute($prep, $args);
        $table = "";
        if ($cur_super != false) {
            while ($row = $dbc->fetchRow($res)) {
                $table .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                    $row['upc'],
                    $row['brand'],
                    $row['description'],
                    $row['prType'],
                    $row['normal_price'],
                    $row['cost']
                );
            }
        }

        return <<<HTML
<div class="container-fluid" style="margin-top: 15px">
    <div class="row">
        <div class="col-lg-8">
            <table class="table table-condensed table-bordered table-striped table-sm small" id="main-table">
                <thead><th>upc</th><th>brand</th><th>description</th><th>price rule type</th></thead>
                <tbody>$table</tbody>
            </table>
        </div>
        <div class="col-lg-4">
            {$this->formContent()}
            <iframe src="http://key/Scannie/content/Item/MarginCalculator.php"
                frameBorder=0 height="500px"></iframe>
        </div>
    </div>
</div>
HTML;
    }

    public function formContent()
    {
        $dbc = scanLib::getConObj();
        $cur_super = FormLib::get('superDept', false);

        $prep = $dbc->prepare("SELECT super_name FROM MasterSuperDepts
            GROUP BY super_name");
        $res = $dbc->execute($prep);
        $supers = "";
        while ($row = $dbc->fetchRow($res)) {
            $name = $row['super_name'];
            $sel = ($cur_super ==  $name) ? 'selected' : '';
            $supers .= "<option value=\"$name\" $sel>$name</option>";
        }
        return <<<HTML
<form method="post" name="superForm">
    <div class="form-group">
        <select name="superDept" class="form-control form-control-sm">$supers</select>
    </div>
    <div class="form-group">
        <select name="priceRuleType" class="form-control form-control-sm"><option value=null>Select Rule Type</option></select>
    </div>
</form>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('select[name=superDept]').change(function(){
    document.forms['superForm'].submit();
});
var rule_types = [];
$('#main-table tr').each(function(){
    var text = $(this).find('td:eq(3)').text();
    if ($.inArray(text, rule_types) == -1) {
        rule_types.push(text);
    }
});
$.each(rule_types, function(i,type) {
    $('select[name=priceRuleType]').append("<option value="+type+">"+type+"</option>");
});
$('select[name=priceRuleType]').change(function(){
    $('#main-table tr').each(function(){
        $(this).closest('tr').show();
    });
    $('#main-table tr').each(function(){
        var text = $(this).find('td:eq(3)').text();
        var selected = $('select[name=priceRuleType]').find('option:selected').text();
        if (text != selected && !$(this).parent('thead').is('thead') && selected != 'Select Rule Type') {
            $(this).closest('tr').hide();
        }
    });
});
JAVASCRIPT;
    }

    public function helpContent()
    {
        return <<<HTML
<p>Review items using special pricing rules by super department.</p>
HTML;
    }

}
WebDispatch::conditionalExec();
