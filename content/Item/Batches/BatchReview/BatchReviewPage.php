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
    include(__DIR__.'/../../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../../common/sqlconnect/SQLManager.php');
}
class BatchReviewPage extends PageLayoutA
{
    protected $title = 'Batch Review';
    protected $description = '[Batch Review] Review price change batch data to 
        ensure accuracy.';
    protected $ui = TRUE;
    
    public function body_content() 
    {
        
        $dbc = scanLib::getConObj();
        $curPage = basename($_SERVER['PHP_SELF']);
        
        $id = $_GET['id'];
        $batchname = "";
        if ($id > 0) {
            $a = array($id);
            $p = $dbc->prepare("SELECT batchName FROM batches WHERE batchID = ?");
            $r = $dbc->execute($p, $a);
            $row = $dbc->fetchRow($r);
            $batchname = $row['batchName'];
        }
        $ret = '';
        $ret .= '<div class="container-fluid">';
        include('BatchReviewLinks.php');
        $ret .= $this->form_content($id, $batchname);
        
        $ret .= '<a href="http://key/git/fannie/batches/newbatch/EditBatchPage.php?id=' 
            . $id . '" target="_blank"><span class="text-primary no-print">View Batch</span></a>';
        $nextBatch = $_SERVER['PHP_SELF'] . '?id=' . ($id + 1);
        $prevBatch = $_SERVER['PHP_SELF'] . '?id=' . ($id - 1);
        $ret .= '&nbsp;<a class="btn btn-default btn-sm" href="' . $prevBatch .'">prev</a>';
        $ret .= '&nbsp;<a class="btn btn-default btn-sm" href="' . $nextBatch .'">next</a><br><br>';

        if ($id) {
            $query = $dbc->prepare('
                SELECT 
                    bl.upc,
                    p.description,
                    p.department AS pdept,
                    d.dept_name,
                    p.cost,
                    bl.salePrice AS price,
                    vd.margin AS unfiMarg,
                    vd.posDeptID,
                    vd.name AS vendorDeptName,
                    vd.deptID as unfiDeptId,
                    d.margin,
                    v.discountRate,
                    p.normal_price,
                    p.brand 
                FROM batchList as bl
                    LEFT JOIN products AS p ON p.upc = bl.upc
                    LEFT JOIN departments AS d ON d.dept_no = p.department
                    LEFT JOIN vendorDepartments AS vd 
                        ON vd.vendorID = p.default_vendor_id 
                            AND vd.posDeptID = p.department
                    LEFT JOIN vendors AS v ON v.vendorID = p.default_vendor_id
                WHERE bl.batchID = ' . $id . '
                GROUP BY p.upc
                ;');
            $result = $dbc->execute($query);
            $ret .= '
                <div class=""><table class="table table-bordered table-condensed small">
                    <th>UPC</th>
                    <th>Brand</th>
                    <th>Description</th>
                    <th>POS Dept.</th>
                    <th>Cost</th>
                    <th>Cur.Price</th>
                    <th>New Price</th>
                    <th>New Marg.</th>
                    <th>Desired Marg.</th>
                    <th title="Difference between New Margin and Desired Margin.">Diff.</th>
            ';
            while ($row = $dbc->fetch_row($result)) {
                $newMargin = ($row['price'] - $row['cost']) / $row['price'];
                if ($row['discountRate']) {
                    $newMargin = ( ( ($row['price'] * .10) + $row['price'] ) - $row['cost']) / ( ($row['price'] * .10) + $row['price'] );
                }
                $newMargin  = sprintf('%0.2f', $newMargin);
                
                $upc = '<a href="../../../../../../git/fannie/item/ItemEditorPage.php?searchupc=' . $row['upc'] . '" target="_blank">' . $row['upc'] . '</a>';
                $diff = $newMargin - $row['margin'];
                $diff = sprintf('%0.2f', $diff);
                $discountRate = $row['discountRate'];
                
                $ret .= '<tr><td>' . $upc . '</td>';
                $ret .= '<td>' . $row['brand'] . '</td>';
                $ret .= '<td>' . $row['description'] . '</td>';
                $ret .= '<td>' . $row['pdept'] . ' - ' . $row['dept_name'] . '</td>';
                $ret .= '<td>' . $row['cost'] . '</td>';
                $ret .= '<td>' . $row['normal_price'] . '</td>';
                $ret .= '<td><span style="color: grey"><u>' . $row['price'] . '</u></span></td>';
                $ret .= '<td>' . $newMargin . '</td>';
                $ret .= '<td>' . $row['margin'] . '</td>';
                
                if ($diff < -0.08 | $diff > 0.08) {
                    $ret .= '<td><span style="color: tomato;">' . $diff . '</span></td>';
                } else {
                    $ret .= '<td>' . $diff . '</td>';
                }
                
            }
            $ret .= '</table></div>';

        }
        
        return $ret;
        
    }
    
    public function form_content($id, $batchname)
    {
        $ret = '';
        $ret .= '<h4>Batch Review Page</h4>';
        
        if ($id) $ret .= ' Batch ID # ' . $id . ' - ' . $batchname;
        
        $ret .= '
            <form method="get" class="form-inline no-print">
                <div class="form-group">
                    <input type="text" class="form-control" name="id" placeholder="Enter Batch  ID" autofocus>
                </div>
                <div class="form-group">
                    <button class="btn btn-default" type="submit">Submit</button>
                </div>
            </form>
        ';
        
        return $ret;
    }

    public function cssContent()
    {
        return <<<HTML
.form-group {
    margin-right: 5px;
}
HTML;
    }
    
    public function helpContent()
    {
        return '
            <ul>
            <li><b>Non-UNFI Review</b> Review non-UNFI vendors.</li>
            <li><b>UNFI Review</b> Review UNFI batches</li>
            <li><b>UNFI-MILK Review</b> Has not been updated in a long time, use with discretion.</li>
            </ul>
            <ul><label>Things to pay attention to </label>
            <div style="border: 1px solid lightgrey;"></div>
            <li><b>UNFI Batches</b> Make sure that POS Dept. matches UNFI Category. If 
                these categories do not match, check that the margin POS is using is correct.</li>
            <li><b>Diff</b> Diff. is the difference between what the new actual margin will be 
                and the desired margin. If this number is off by more than 0.05, there 
                is likely an issue with the new SRP.</li>

            </ul>';
    }
    
}

WebDispatch::conditionalExec();
