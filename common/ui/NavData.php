<?php
$navData = array(
    'Products' => array(
        array(
            'type' => "link",
            'text' => 'Brands Subdirectory',
            'heading' => 'Main',
            'user_type' => 2,
            'url' => "Item/Brands/"
        ),
        array(
            'type' => "link",
            'text' => 'Local Flag Discrep',
            'heading' => 'Main',
            'user_type' => 2,
            'url' => "Item/LocalFlagReport.php"
        ),
        array(
            'type' => "link",
            'text' => 'Pending Action',
            'heading' => 'Main',
            'user_type' => 2,
            'url' => "Item/PendingAction.php"
        ),
        array(
            'type' => "link",
            'text' => 'Track Product Change',
            'heading' => 'Main',
            'user_type' => 1,
            'url' => "Item/TrackItemChange.php"
        ),
    ),
    'Reports' => array(
        array(
            'type' => "link",
            'text' => 'DBA',
            'heading' => 'Main',
            'url' => "Reports/DBA.php",
            'user_type' => 2
        ),
        array(
            'type' => "link",
            'text' => 'Batch Review Report',
            'heading' => 'Main',
            'user_type' => 1,
            'url' => "Item/Batches/BatchReview/BatchReviewPage.php"
        ),
        array(
            'type' => "link",
            'text' => 'Batch Activity Report',
            'heading' => 'Main',
            'user_type' => 2,
            'url' => "Reports/BatchHistory.php"
        ),
        array(
            'type' => "link",
            'text' => 'Find PLU To Reuse BULK',
            'heading' => 'Main',
            'user_type' => 1,
            'url' => "Reports/BulkReusePluReport.php"
        ),
        array(
            'type' => "link",
            'text' => 'Find PLU To Reuse DELI',
            'heading' => 'Main',
            'user_type' => 1,
            'url' => "Reports/DeliReusePluReport.php"
        ),
        array(
            'type' => "link",
            'text' => 'Not Exist Yet SRP Fix',
            'heading' => 'Main',
            'user_type' => 2,
            'url' => "Reports/NotYetExistSrpAdjustment.php"
        ),
        array(
            'type' => "link",
            'text' => 'Price Rule Report',
            'heading' => 'Main',
            'user_type' => 1,
            'url' => "Reports/PriceRuleTypeReport.php"
        ),
        array(
            'type' => "link",
            'text' => 'Coop Deals QA & Breakdowns',
            'heading' => 'Main',
            'user_type' => 1,
            'url' => "Item/Batches/CoopDeals/CoopDealsReview.php"
        ),
        array(
            'type' => "link",
            'text' => 'Vendor Review Schedule',
            'heading' => 'Main',
            'user_type' => 2,
            'url' => "Reports/VendorReviewSchedule.php"
        ),
        array(
            'type' => "link",
            'text' => 'Weighted Average Cost',
            'heading' => 'Main',
            'user_type' => 2,
            'url' => "Testing/WeightedAvg.php"
        ),
    ),
    'Scanning' => array(
        array(
            'type' => "link",
            'text' => 'Dashboard',
            'heading' => 'Main',
            'url' => "Home/Dashboard.php",
            'user_type' => 2
        ),
        array(
            'type' => "link",
            'text' => '<span style="color: green; font-weight: bold;">Batch Check</span>',
            'heading' => 'Main',
            'url' => "Scanning/BatchCheck/newpage.php",
            'user_type' => 1
        ),
        array(
            'type' => "link",
            'text' => '<span style="color: plum; font-weight: bold;">Audit</span> Scanner',
            'heading' => 'Main',
            'url' => "Scanning/AuditScanner/ProductScanner.php",
            'user_type' => 1
        ),
        array(
            'type' => "link",
            'text' => '<span style="color: plum; font-weight: bold;">Audit</span> Report',
            'heading' => 'Main',
            'url' => "Scanning/AuditScanner/AuditReport.php",
            'user_type' => 1
        ),
        array(
            'type' => "link",
            'text' => '<span style="color: purple; font-weight: bold;">Basics</span> Scan',
            'heading' => 'Main',
            'url' => "Scanning/AuditScanner/BasicsScan.php",
            'user_type' => 0
        ),
        array(
            'type' => "link",
            'text' => 'Handheld Settings',
            'heading' => 'Main',
            'url' => "Scanning/ScannerSettings.php",
            'user_type' => 1
        ),
    ),

    'Misc' => array(
        array(
            'type' => "link",
            'text' => 'Breakdown NCG Edlp Files',
            'heading' => 'Main',
            'url' => "Testing/NcgEdlpFileBreakdown.php",
            'user_type' => 2
        ),
        array(
            'type' => "link",
            'text' => 'Print Multiple Receipts',
            'heading' => 'Main',
            'url' => "Testing/PrintMultipleReceipts.php",
            'user_type' => 2
        ),
        array(
            'type' => "link",
            'text' => 'Popups',
            'heading' => 'Main',
            'url' => "Item/Popups.php",
            'user_type' => 1
        ),
        array(
            'type' => "link",
            'text' => 'Rest Test',
            'heading' => 'Main',
            'url' => "Testing/RestTest.php",
            'user_type' => 1
        ),
        array(
            'type' => "help",
            'text' => 'Help',
            'heading' => 'Main',
            'url' => "",
            'user_type' => 1
        ),
    ),

);
