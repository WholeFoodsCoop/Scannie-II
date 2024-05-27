<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class NcgEdlpFileBreakdown 
*   Break new NCG EDLP files down
*   into separate file for each vendor
*/
class NcgEdlpFileBreakdown extends PageLayoutA
{

    protected $must_authenticate = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContents();
        $this->__routes[] = 'post<id>';

        return parent::preprocess();
    }

    public function postIdView()
    {
        $dbc = scanLib::getConObj();
        $ret = $this->pageContents();
        $file = file_get_contents($_FILES["fileUpload"]["tmp_name"]);
        $lines = explode("\r\n", $file);
        $data = array();
        $csv = array();
        $links = '<div style="padding: 25px;">';
        $thead = '';

        $td = '';
        foreach ($lines as $k => $line) {
            if ($k == 0) {
                $thead = $line;
            }
            $cells = explode(",", $line);
            if (isset($cells[5])) {

                $distro = $cells[5];
                $distro = str_replace(" ", "", $distro);
                $distro = str_replace("\"", "", $distro);
                $distro = str_replace("/", "", $distro);
                $distro = str_replace("'", "", $distro);

                $type = $cells[0];
                if ($type == 'Program') {
                    continue;
                }
                if (strpos($type, 'Basics') !== false) {
                    $type = 'Basics';
                }
                if (strpos($type, 'Core') !== false) {
                    $type = 'CoreSets';
                }

                $distro = $type."_".$distro;

                if (!isset($csv[$distro])) {
                    $csv[$distro] = $thead."\r\n";
                } else {
                    $csv[$distro] .= $line."\r\n";
                }
            }

        }

        foreach ($csv as $distro => $text) {
            file_put_contents("./noauto/NCG_$distro.csv", $text);
            $links .= "<div><a href=\"./noauto/NCG_$distro.csv\">$NCG_$distro.csv</a></div>";
        }
        $links .= "</div>";

        return $ret
            .$links;
    }

    public function pageContents()
    {
        $dbc = scanLib::getConObj();
        return <<<HTML
<div style="padding: 25px; width: 300px;">
    <form action="NcgEdlpFileBreakdown.php" method="post" enctype="multipart/form-data">
        Select file to upload:
        <input type="hidden" name="MAX_FILE_SIZE" value="1000000" />
        <input type="hidden" name="id" value="1" />
        <div class="form-group">
            <input type="file" name="fileUpload" id="fileUpload" class="form-control">
        </div>
        <div class="form-group">
            <input type="submit" value="Upload" name="submit" class="form-control">
        </div>
    </form>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
