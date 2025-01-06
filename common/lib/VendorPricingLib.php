<?php
/*******************************************************************************

    Copyright 2024 Whole Foods Community Co-op.

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
*   @class VendorPricingLib 
*/
class VendorPricingLib 
{
    /*
        recalcVendorSrpsQ
        returns pre-prepared SQL statement to recalculate vendor SRPs
        prepared statement includes one argument (?) - vendorID INT
    */
    public static function recalcVendorSrpsQ($markup=0, $includePriceRules=0)
    {

        $price_rule_and = ($includePriceRules == 0) ? "
    AND p.price_rule_id = 0 " : "";

        return <<<SQL
SELECT
    p.upc, p.brand, p.description, (p.cost + (p.cost * $markup)) AS cost,
    CASE
        WHEN f.futureCost IS NOT NULL AND f.futureCost <> (p.cost + (p.cost * $markup)) THEN f.futureCost ELSE (p.cost + (p.cost * $markup))
    END AS cost,

    CASE
        WHEN f.futureCost IS NOT NULL AND f.futureCost <> (p.cost + (p.cost * $markup)) THEN 'futureCost' ELSE '(p.cost + (p.cost * $markup))'
    END AS costFrom,

    CASE
        WHEN f.futureCost IS NOT NULL AND f.futureCost <> (p.cost + (p.cost * $markup)) THEN f.startDate ELSE pcc.date
    END AS costChangeDate,

    CASE
        WHEN f.futureCost IS NOT NULL AND f.futureCost <> (p.cost + (p.cost * $markup)) THEN f.futureCost - (p.cost + (p.cost * $markup)) ELSE pcc.difference
    END AS costChange,
    p.normal_price,
    vd.margin as vendorSpecificMargin,
    c.margin as vendorSpecificMargin2,
    b.margin as departmentMargin,

    CASE WHEN f.futureCost IS NOT NULL AND f.futureCost <> (p.cost + (p.cost * $markup))

            THEN
                CASE
                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN f.futureCost / (1 - c.margin) ELSE
                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN f.futureCost / (1 - vd.margin) ELSE
                            CASE WHEN b.margin IS NOT NULL THEN f.futureCost / (1 - b.margin) ELSE f.futureCost / (1 - 0.40)
                        END
                    END
                END
            ELSE
                CASE
                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN (p.cost + (p.cost * $markup)) / (1 - c.margin) ELSE
                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN (p.cost + (p.cost * $markup)) / (1 - vd.margin) ELSE
                            CASE WHEN b.margin IS NOT NULL THEN (p.cost + (p.cost * $markup)) / (1 - b.margin) ELSE (p.cost + (p.cost * $markup)) / (1 - 0.40)
                        END
                    END
                END
    END AS rawSRP,

    CONCAT(FLOOR(ROUND(
    CASE
        WHEN f.futureCost IS NOT NULL AND f.futureCost <> (p.cost + (p.cost * $markup))

            THEN
                CASE
                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN f.futureCost / (1 - c.margin) ELSE
                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN f.futureCost / (1 - vd.margin) ELSE
                            CASE WHEN b.margin IS NOT NULL THEN f.futureCost / (1 - b.margin) ELSE f.futureCost / (1 - 0.40)
                        END
                    END
                END
            ELSE
                CASE
                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN (p.cost + (p.cost * $markup)) / (1 - c.margin) ELSE
                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN (p.cost + (p.cost * $markup)) / (1 - vd.margin) ELSE
                            CASE WHEN b.margin IS NOT NULL THEN (p.cost + (p.cost * $markup)) / (1 - b.margin) ELSE (p.cost + (p.cost * $markup)) / (1 - 0.40)
                        END
                    END
                END
    END, 3)), '.', prr.validEnding) AS NewSRP,

    #prr.rangeID,
    #prr.rawTenth,
    #prr.validEnding,
    m.super_name,
    v.vendorName
FROM products AS p
    LEFT JOIN FutureVendorItems AS f ON f.upc=p.upc AND f.vendorID=p.default_vendor_id AND f.startDate >= DATE(NOW())
    LEFT JOIN productCostChanges AS pcc ON pcc.upc=p.upc
    LEFT JOIN vendors AS v ON v.vendorID=p.default_vendor_id
    LEFT JOIN departments AS b ON p.department=b.dept_no
    LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=b.dept_no
    LEFT JOIN VendorSpecificMargins AS c ON c.vendorID=p.default_vendor_id AND c.deptID=p.department
    LEFT JOIN woodshed_no_replicate.tmpSrps AS srp ON srp.upc=p.upc
    LEFT JOIN vendorItems AS vi ON vi.upc=p.upc AND vi.vendorID=p.default_vendor_id
    LEFT JOIN vendorDepartments AS vd ON vd.vendorID=p.default_vendor_id AND vd.deptID=vi.vendorDept

    LEFT JOIN woodshed_no_replicate.PriceRoundingRules AS prr ON
        prr.floatMin <= CASE
        WHEN f.futureCost IS NOT NULL AND f.futureCost <> (p.cost + (p.cost * $markup))

            THEN
                CASE
                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN f.futureCost / (1 - c.margin) ELSE
                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN f.futureCost / (1 - vd.margin) ELSE
                            CASE WHEN b.margin IS NOT NULL THEN f.futureCost / (1 - b.margin) ELSE f.futureCost / (1 - 0.40)
                        END
                    END
                END
            ELSE
                CASE
                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN (p.cost + (p.cost * $markup)) / (1 - c.margin) ELSE
                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN (p.cost + (p.cost * $markup)) / (1 - vd.margin) ELSE
                            CASE WHEN b.margin IS NOT NULL THEN (p.cost + (p.cost * $markup)) / (1 - b.margin) ELSE (p.cost + (p.cost * $markup)) / (1 - 0.40)
                        END
                    END
                END
    END
        AND prr.floatMax >= CASE
        WHEN f.futureCost IS NOT NULL AND f.futureCost <> (p.cost + (p.cost * $markup))

            THEN
                CASE
                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN f.futureCost / (1 - c.margin) ELSE
                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN f.futureCost / (1 - vd.margin) ELSE
                            CASE WHEN b.margin IS NOT NULL THEN f.futureCost / (1 - b.margin) ELSE f.futureCost / (1 - 0.40)
                        END
                    END
                END
            ELSE
                CASE
                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN (p.cost + (p.cost * $markup)) / (1 - c.margin) ELSE
                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN (p.cost + (p.cost * $markup)) / (1 - vd.margin) ELSE
                            CASE WHEN b.margin IS NOT NULL THEN (p.cost + (p.cost * $markup)) / (1 - b.margin) ELSE (p.cost + (p.cost * $markup)) / (1 - 0.40)
                        END
                    END
                END
    END
        AND prr.rawTenth = SUBSTR(SUBSTR(ROUND(
    CASE
        WHEN f.futureCost IS NOT NULL AND f.futureCost <> (p.cost + (p.cost * $markup))

            THEN
                CASE
                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN f.futureCost / (1 - c.margin) ELSE
                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN f.futureCost / (1 - vd.margin) ELSE
                            CASE WHEN b.margin IS NOT NULL THEN f.futureCost / (1 - b.margin) ELSE f.futureCost / (1 - 0.40)
                        END
                    END
                END
            ELSE
                CASE
                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN (p.cost + (p.cost * $markup)) / (1 - c.margin) ELSE
                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN (p.cost + (p.cost * $markup)) / (1 - vd.margin) ELSE
                            CASE WHEN b.margin IS NOT NULL THEN (p.cost + (p.cost * $markup)) / (1 - b.margin) ELSE (p.cost + (p.cost * $markup)) / (1 - 0.40)
                        END
                    END
                END
    END, 2), -2), 1, 1)
#-- note that if you round to >>> END, 3, -3) <<< then rounding will round down on the thousandths digit, while
#-- >>>END, 2, -2) <<< will round correctly (eg 1.235 will round up to 1.24, which is what we want)


WHERE v.vendorID = ? 
    AND m.super_name != 'PRODUCE'
    #-- search for inUse = 0 to update OOU items
    AND p.inUse = 1

##-- SEPARATE BATCHES BY INC || REDUX SUB-TYPE
    # INC
    # AND p.normal_price < srp.rounded
    # REDUX
    # AND p.normal_price > srp.rounded
#    AND p.normal_price <> CONCAT(FLOOR(ROUND(
#    CASE
#        WHEN f.futureCost IS NOT NULL AND f.futureCost <> (p.cost + (p.cost * $markup))
#
#            THEN
#                CASE
#                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN f.futureCost / (1 - c.margin) ELSE
#                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN f.futureCost / (1 - vd.margin) ELSE
#                            CASE WHEN b.margin IS NOT NULL THEN f.futureCost / (1 - b.margin) ELSE f.futureCost / (1 - 0.40)
#                        END
#                    END
#                END
#            ELSE
#                CASE
#                    WHEN c.margin IS NOT NULL AND c.margin > 0 THEN (p.cost + (p.cost * $markup)) / (1 - c.margin) ELSE
#                        CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN (p.cost + (p.cost * $markup)) / (1 - vd.margin) ELSE
#                            CASE WHEN b.margin IS NOT NULL THEN (p.cost + (p.cost * $markup)) / (1 - b.margin) ELSE (p.cost + (p.cost * $markup)) / (1 - 0.40)
#                        END
#                    END
#                END
#    END, 3)), '.', prr.validEnding)

## DONT LOOK AT ITEMS WITH PRICE RULES TO START
    $price_rule_and
    # AND m.super_name = 'WELLNESS'
GROUP BY p.upc
;
SQL;

    }

}
