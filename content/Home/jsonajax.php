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

class FormLib
{
    public static function get($name)
    {
        if (isset($_POST[$name])) {
            return $_POST[$name];
        } else if (isset($_GET[$name])) {
            return $_GET[$name];
        }
        return false;
    }
}

$sfcRemoveId = FormLib::get('sfcRemoveId');
if ($sfcRemoveId) {
    echo "ID: $sfcRemoveId";

    $file = file_get_contents("noauto/stagedFutureVendors.json");
    $data = json_decode($file);

    unset($data->{0}->{$sfcRemoveId});

    $newJson = json_encode($data);
    file_put_contents("noauto/stagedFutureVendors.json", $newJson);
}

