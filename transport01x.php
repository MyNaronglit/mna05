<?php
session_start();

$pusername = $_SESSION['pusername'];
if (!isset($pusername)) {
    echo json_encode(["stp_alert" => ["status" => "danger", "text" => "Must be loged in before process!"]]);
    exit();
}

$func = '../' . (gethostname() == 'lamancha' ? '../' : '') . 'xrequire/psgfunct.php';
require_once $func;
require_once './kqiq/mydata_pdo.php';
require_once './kqiq/export_data_pdo.php';
require_once './stpstd.php';

$db = new MyPdo();
$ier = 3;
$ierp = $db->connect_sql($ier);
$win = 2;
$winspeed = $db->connect_sql($win);
$himal = 0;
$himalai = $db->connectb($himal);

$function_name = $_POST['type'];
$parameters = [$ierp, $_POST['crit'], $winspeed, $himalai];
echo $function_name(...$parameters);

function get_last_km($ierp, $crit, $winspeed, $himalai)
{
    $reg = $crit['carReg'];

    $sql = "
        SELECT Car_registration, stop_KM, stop_time
        FROM psgdata.tblTransport
        WHERE Car_registration = '$reg'
        ORDER BY stop_time DESC
        LIMIT 1
    ";
    
    $result = $himalai->query($sql)->fetch(PDO::FETCH_ASSOC);
    
    return json_encode([
        "success" => true,
        "data" => $result ?: null
    ]);
}

function get_last_invoice_id($ierp, $crit, $winspeed, $himalai)
{
    $invoiceFilter = $crit['invoiceFilter'] ?? '';

    $whereInvoice = '';
    if ($invoiceFilter !== '') {
        $whereInvoice = " AND h.InvNo LIKE N'%$invoiceFilter%' ";
    }

    $sql = "
           SELECT TOP 50
    h.invno AS invoice_id,
    CASE 
        WHEN LEFT(c.custcode, 1) = '2' THEN '1' + RIGHT(c.custcode, LEN(c.custcode) - 1)
        WHEN LEFT(c.custcode, 1) = '4' THEN '3' + RIGHT(c.custcode, LEN(c.custcode) - 1)
        WHEN LEFT(c.custcode, 1) = '6' THEN '5' + RIGHT(c.custcode, LEN(c.custcode) - 1)
        ELSE c.custcode
    END AS Customer_ID,
    c.CustName AS CustomerName,
    SUM(d.goodqty2 * d.goodprice2) AS Sales_amt
FROM Winspeed_PRD.dbo.soinvhd h WITH (NOLOCK)
LEFT JOIN Winspeed_PRD.dbo.emcust c WITH (NOLOCK)
    ON h.custid = c.custid
LEFT JOIN Winspeed_PRD.dbo.soinvdt d WITH (NOLOCK)
    ON h.soinvid = d.soinvid
WHERE
    h.DocuDate > CONVERT(date, GETDATE()-7)
    AND h.docutype = '107'
    AND h.DocuStatus <> 'C'
    AND h.MAFlag IS NULL
     $whereInvoice
GROUP BY
    h.invno,
    c.CustName,
    c.custcode
ORDER BY
    h.invno DESC
    ";

    $result = $winspeed->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    return json_encode([
        "success" => true,
        "data" => $result
    ]);
}

function show_transport($ierp, $crit, $winspeed, $himalai)
{
    $CarFilter = $crit['registration'] ?? '';
    $date = $crit['date'] ?? '';

    $where = [];
    if ($CarFilter !== '') {
        $len = mb_strlen($CarFilter, 'UTF-8');
        $where[] = "LEFT(Car_registration, $len) = '$CarFilter'";
    }
    if ($date !== '') {
        $where[] = "DATE(start_time) = '$date'";
    }
    $whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "
        SELECT RecordID, Car_registration, start_KM, stop_KM, 
               Fuel, Shipping,
               DATE(start_time) AS start_time, lastupdate
        FROM psgdata.tblTransport
        $whereSql
        ORDER BY lastupdate DESC
    ";

    $rt = $himalai->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $messageInfo = '';
    $isNewRecord = false;

    if (empty($rt) && $CarFilter !== '' && $date !== '') {
        $len = mb_strlen($CarFilter, 'UTF-8');
        
        $sqlLatest = "
            SELECT 
                RecordID, Car_registration, start_KM, stop_KM,
                Fuel, Shipping,
                DATE(start_time) AS start_time, lastupdate
            FROM psgdata.tblTransport
            WHERE LEFT(Car_registration, $len) = '$CarFilter'
              AND DATE(start_time) < '$date'
            ORDER BY start_time DESC, lastupdate DESC
            LIMIT 1
        ";
        
        $rtLatest = $himalai->query($sqlLatest)->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rtLatest)) {
            $latestDate = $rtLatest[0]['start_time'];
            $messageInfo = "⚠️ วันนี้ ({$date}) ยังไม่มีข้อมูล | ข้อมูลล่าสุด: {$latestDate}";
            
            $rt = [[
                'RecordID' => null,
                'Car_registration' => $rtLatest[0]['Car_registration'],
                'start_KM' => $rtLatest[0]['start_KM'],
                'stop_KM' => $rtLatest[0]['stop_KM'],
                'Fuel'  => $rtLatest[0]['Fuel'],
                'Shipping'  => $rtLatest[0]['Shipping'],
                'start_time' => $date,
                'lastupdate' => date('Y-m-d H:i:s'),
                'is_new' => true
            ]];
            $isNewRecord = true;
        } else {
            $messageInfo = "⚠️ ไม่พบข้อมูลทะเบียน {$CarFilter}";
            $rt = [[
                'RecordID' => null,
                'Car_registration' => $CarFilter,
                'start_KM' => null,
                'stop_KM' => null,
                'start_time' => $date,
                'lastupdate' => date('Y-m-d H:i:s'),
                'is_new' => true
            ]];
            $isNewRecord = true;
        }
    }
$tb = [
    ["th" => "Car registration", "cols" => "150", "style" => "background-color:#ffcc00;"],
    ["th" => "Start KM", "cols" => "110", "style" => "background-color:#ffcc00;"],
    ["th" => "End KM", "cols" => "110", "style" => "background-color:#ffcc00;"],
    ["th" => "Fuel costs", "cols" => "110", "style" => "background-color:#ffcc00;"],
    ["th" => "Shipping", "cols" => "110", "style" => "background-color:#ffcc00;"],
    ["th" => "Detail", "cols" => "90", "style" => "background-color:#ffcc00;"],
];

$dtid = $crit['dtid'] ?? 1;
$html = "";

$isNoDataWarning = false;
if ($messageInfo !== '') {
    $html .= "<div style='padding:10px; background:#fff3cd; border:1px solid #ffc107; margin-bottom:10px; border-radius:4px;'>{$messageInfo}</div>";
    if (strpos($messageInfo, 'ยังไม่มีข้อมูล') !== false) {
        $isNoDataWarning = true;
    }
}

$html .= "<table id='t{$dtid}' class='datatable' style='width:100%; border-collapse:collapse;'><thead><tr>";

foreach ($tb as $column) {
    $html .= "<th style='{$column['style']} padding:8px; border:1px solid #ddd;'>{$column['th']}</th>";
}

$html .= "</tr></thead><tbody>";

if (!empty($rt)) {
    $firstRow = $rt[0];
    $recordId = $firstRow['RecordID'];
    $carReg = $firstRow['Car_registration'];
    $startKM = $firstRow['start_KM'];
    $endKM = $firstRow['stop_KM'];
    $fuelCosts = $firstRow['Fuel'] ?? 0;
    $shippingCosts = $firstRow['Shipping'] ?? null;

    $html .= "<tr class='main-row' data-recordid='{$recordId}' data-refcarid='{$recordId}'>";
    $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$carReg}</td>";
    $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:right;'>" . number_format($startKM) . "</td>";

    if ($isNoDataWarning) {
        $endKmDisplay = ($endKM !== null && $endKM > 0) ? number_format($endKM) : '-';
        $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:right;'>{$endKmDisplay}</td>";
    } else {
        $endKmValue = ($endKM !== null && $endKM > 0) ? (int)$endKM : '';
        $html .= "<td style='padding:8px; border:1px solid #ddd;'>
            <input type='text' 
            class='integer-format input-end-km' 
            id='end_km_{$recordId}' 
            data-recordid='{$recordId}'
            data-car='{$carReg}'
            data-start='{$startKM}'
            data-date='{$date}'
            value='{$endKmValue}'
            style='width:100%; padding:5px; box-sizing:border-box; background:#ffffff; color:#000000; text-align:right; height:25px; border:1px solid #ddd;' 
            placeholder='End KM'>
        </td>";
    }

    if ($isNoDataWarning) {
        $fuelDisplay = ($fuelCosts !== null && $fuelCosts > 0) ? number_format($fuelCosts, 2) : '-';
        $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:right;'>{$fuelDisplay}</td>";
    } else {
        $fuelValue = ($fuelCosts !== null && $fuelCosts > 0)
            ? rtrim(rtrim(number_format($fuelCosts, 2, '.', ''), '0'), '.')
            : '';
        $html .= "<td style='padding:8px; border:1px solid #ddd;'>
            <input type='text' 
            class='number-format input-fuel-costs' 
            id='fuel_costs_{$recordId}' 
            data-recordid='{$recordId}'
            data-car='{$carReg}'
            data-start='{$startKM}'
            data-date='{$date}'
            value='{$fuelValue}'
            step='0.0001'
            min='0'
            style='width:100%; padding:5px; box-sizing:border-box; background:#ffffff; color:#000000; text-align:right; height:25px; border:1px solid #ddd;' 
            placeholder='ค่าน้ำมัน'>
        </td>";
    }
   $shippingDisplay = ($shippingCosts !== null && $shippingCosts > 0)
    ? $shippingCosts
    : '';

    $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:right;'>
    <span class='number-format'>{$shippingDisplay}</span>
</td>";
    if ($recordId !== null) {
        $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:center;'><button type='button' class='btn-show-detail' data-recordid='{$recordId}' style='padding:5px 10px; cursor:pointer;'>▼</button></td></tr>";
        $html .= "<tr class='detail-row' id='detail-{$recordId}' style='display:none;'><td colspan='6' style='padding:0; border:1px solid #ddd;'><div class='loading' style='padding:20px; text-align:center;'>Loading...</div></td></tr>";
    } else {
        $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:center;'>-</td></tr>";
    }
    for ($i = 1; $i < count($rt); $i++) {
        $data = $rt[$i];
        $recordId = $data['RecordID'];
        $carReg = $data['Car_registration'];
        $startKM = $data['start_KM'];
        $endKM = $data['stop_KM'];
        $fuelCosts = $data['Fuel'] ?? 0;
        $shippingCosts = $data['Shipping'] ?? null;

        $html .= "<tr class='main-row history-row' data-recordid='{$recordId}' data-refcarid='{$recordId}' style='background:#f9f9f9;'>";
        $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$carReg}</td>";
        $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:right;'>" . number_format($startKM) . "</td>";
        if ($isNoDataWarning) {
            $endKmDisplay = ($endKM !== null && $endKM > 0) ? number_format($endKM) : '-';
            $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:right;'>{$endKmDisplay}</td>";
        } else {
            $endKmValue = ($endKM !== null && $endKM > 0) ? (int)$endKM : '';
            $html .= "<td style='padding:8px; border:1px solid #ddd;'>
                <input type='text' 
                class='integer-format input-end-km' 
                id='end_km_{$recordId}' 
                data-recordid='{$recordId}'
                data-car='{$carReg}'
                data-start='{$startKM}'
                data-date='{$date}'
                value='{$endKmValue}'
                style='width:100%; padding:5px; box-sizing:border-box; background:#f9f9f9; color:#000000; text-align:right; height:25px; border:1px solid #ddd;' 
                placeholder='End KM'>
            </td>";
        }
        if ($isNoDataWarning) {
            $fuelDisplay = ($fuelCosts !== null && $fuelCosts > 0) ? number_format($fuelCosts, 2) : '-';
            $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:right;'>{$fuelDisplay}</td>";
        } else {
            $fuelValue = ($fuelCosts !== null && $fuelCosts > 0) ? number_format($fuelCosts, 2, '.', '') : '';
            $html .= "<td style='padding:8px; border:1px solid #ddd;'>
                <input type='text' 
                class='number-format input-fuel-costs' 
                id='fuel_costs_{$recordId}' 
                data-recordid='{$recordId}'
                data-car='{$carReg}'
                data-start='{$startKM}'
                data-date='{$date}'
                value='{$fuelValue}'
                style='width:100%; padding:5px; box-sizing:border-box; background:#f9f9f9; color:#000000; text-align:right; height:25px; border:1px solid #ddd;' 
                placeholder='ค่าน้ำมัน'>
            </td>";
        }
        $shippingDisplay = ($shippingCosts !== null && $shippingCosts > 0)
    ? $shippingCosts
    : '';

        $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:right;'>
    <span class='number-format'>{$shippingDisplay}</span>
</td>";
        if ($recordId !== null) {
            $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:center;'><button type='button' class='btn-show-detail' data-recordid='{$recordId}' style='padding:5px 10px; cursor:pointer;'>▼</button></td></tr>";
            $html .= "<tr class='detail-row' id='detail-{$recordId}' style='display:none;'><td colspan='6' style='padding:0; border:1px solid #ddd;'><div class='loading' style='padding:20px; text-align:center;'>Loading...</div></td></tr>";
        } else {
            $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:center;'>-</td></tr>";
        }
    }
}

$html .= "</tbody></table>";
$cols = implode(",", array_column($tb, 'cols'));
    header('Content-Type: application/json');
    echo json_encode([
        "table1" => [
            "table" => [$html, $cols, "", ""],
            "tid" => "#t{$dtid}",
            "cid" => "#c{$dtid}",
            "tit" => "",
            "hi" => -25,
            "wi" => 5
        ],
        "rt" => $rt,
        "message" => $messageInfo
    ]);
    exit;
}
function show_transport_detail($ierp, $crit, $winspeed, $himalai)
{
    $RecordID = $crit['RecordID'];
    $sqlDetail = "
        SELECT 
            d.refCarID AS RecordID,
            d.invoice,
            d.item_id,
            d.qty,
            d.M3,
            d.Customer_ID,
            d.lastupdate,
            d.shipping,
            tt.stop_time
        FROM psgdata.tblTransport_detail d
        LEFT JOIN psgdata.tblTransport tt 
            ON tt.RecordID = d.refCarID
        WHERE d.refCarID = '$RecordID'
        ORDER BY d.invoice, d.item_id
    ";

    $detailData = $himalai->query($sqlDetail)->fetchAll(PDO::FETCH_ASSOC);

    if (empty($detailData)) {
        echo json_encode(["success" => true, "detail" => []]);
        exit;
    }
    $invoices = array_unique(array_column($detailData, 'invoice'));
    $inList = "'" . implode("','", $invoices) . "'";
    $sqlCust = "
        SELECT 
            h.invno,
            c.custcode,
            c.CustName
        FROM Winspeed_PRD.dbo.soinvhd h
        LEFT JOIN Winspeed_PRD.dbo.emcust c
            ON h.custid = c.custid
        WHERE h.invno IN ($inList)
    ";
    $custResult = $winspeed->query($sqlCust)->fetchAll(PDO::FETCH_ASSOC);
    $invoiceMap = [];
    foreach ($custResult as $c) {
        $invoiceMap[$c['invno']] = [
            'custcode' => $c['custcode'],
            'CustName' => $c['CustName']
        ];
    }
    foreach ($detailData as &$row) {
        $inv = $row['invoice'];
        $row['CustomerName'] = $invoiceMap[$inv]['CustName'] ?? '';
        $row['WinspeedCustCode'] = $invoiceMap[$inv]['custcode'] ?? '';
    }
    echo json_encode([
        "success" => true,
        "detail" => $detailData
    ]);
    exit;
}


function savekm_transport($ierp, $crit, $winspeed, $himalai)
{
    $recordID = $crit['recordID'];
    $Car_license = $crit['car_registration'];
    $startKM = (int)$crit['start_km'];
    $endKM = (int)$crit['end_km']; 
    $fuelCosts = isset($crit['fuel_costs']) && $crit['fuel_costs'] !== '' ? (float)$crit['fuel_costs'] : 0;
    

    try {
        $himalai->beginTransaction();

        $set = [];
        $set[] = "stop_KM = $endKM";
        $set[] = "stop_time = NOW()";
        $set[] = "Fuel = $fuelCosts";


        $setSql = implode(", ", $set);

        $sql1 = "UPDATE psgdata.tblTransport SET $setSql WHERE RecordID = $recordID";
        $himalai->exec($sql1);

        $himalai->commit();
        echo json_encode(["status" => "success"]);
        exit;

    } catch (Exception $e) {
        $himalai->rollBack();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        exit;
    }
}
function save_shipping($ierp, $crit, $winspeed, $himalai)
{
    $RecordID = $crit['RecordID'];
    $invoice = $crit['invoice'];
    $shipping_costs = $crit['shipping_costs'];
    $totalShipping = $crit['total_shipping'];
    
    try {
        $himalai->beginTransaction();
        
        // ✅ ลบ ORDER BY และ LIMIT ออก - update ทุก record ที่เจอ
        $sqlDetail = "
            UPDATE psgdata.tblTransport_detail
            SET shipping = '$shipping_costs'
            WHERE refCarID = '$RecordID'
              AND invoice = '$invoice'
              ORDER BY lastupdate DESC
             LIMIT 1
        ";
        
        $stmt = $himalai->prepare($sqlDetail);
        $stmt->execute();
        
        // ✅ เช็คว่ามีการ update หรือไม่
        $affected = $stmt->rowCount();
        error_log("Updated $affected rows for invoice: $invoice");
        
        // ✅ Update ยอดรวม
        $sqlHeader = "
            UPDATE psgdata.tblTransport
            SET Shipping =  $totalShipping
            WHERE RecordID = '$RecordID'
        ";
        
        $stmt2 = $himalai->prepare($sqlHeader);
        $stmt2->execute();
        
        $himalai->commit();
        
        echo json_encode([
            "status" => "success",
            "updated_rows" => $affected
        ]);
        exit;
        
    } catch (Exception $e) {
        $himalai->rollBack();
        
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
        exit;
    }
}



function save_start_transport($ierp, $crit, $winspeed, $himalai)
{
    try {
        $carReg       = $crit['car_registration'];
        $startKM      = $crit['start_km'];
        $datetime     = $crit['datetime'];
        $invoice_list = $crit['invoice_list'];

        if (!$invoice_list || count($invoice_list) === 0) {
            throw new Exception("invoice_list empty");
        }

        $inputDate = date('Y-m-d', strtotime($datetime));

        // ======================================================
        // ตรวจสอบว่ามีรอบขนส่งที่ยังเปิดอยู่ในวันนี้หรือไม่
        // ======================================================
        $sqlOpen = "
            SELECT RecordID
            FROM psgdata.tblTransport
            WHERE Car_registration = '$carReg'
            AND DATE(start_time) = '$inputDate'
            AND stop_KM = 0
            AND stop_time IS NULL
            ORDER BY start_time DESC
            LIMIT 1
        ";
        $openRecord = $himalai->query($sqlOpen)->fetch(PDO::FETCH_ASSOC);

        // ======================================================
        // กรณีที่ 1 : มีรอบเปิดอยู่แล้ว → เพิ่ม invoice ใหม่
        // ======================================================
        if ($openRecord !== false) {

            $recordID = $openRecord['RecordID'];

            // ดึง invoice ที่มีอยู่แล้วในรอบนี้
            $existingInvoices = $himalai->query("
                SELECT invoice
                FROM psgdata.tblTransport_detail
                WHERE refCarID = $recordID
            ")->fetchAll(PDO::FETCH_COLUMN);

            // หา invoice ที่ยังไม่มี
            $newInvoices = array_diff($invoice_list, $existingInvoices);

            if (empty($newInvoices)) {
                echo json_encode([
                    "status"   => "success",
                    "message"  => "Invoice ทั้งหมดมีอยู่แล้วในรอบนี้",
                    "RecordID" => $recordID
                ]);
                exit;
            }

            // ดึงรายละเอียด invoice ใหม่จาก Winspeed
            $invoiceStr = "'" . implode("','", $newInvoices) . "'";
            $rows = $winspeed->query("
                SELECT
                    h.invno as invoice_id,
                    CASE
                        WHEN LEFT(c.custcode,1)='2' THEN '1'+RIGHT(c.custcode,LEN(c.custcode)-1)
                        WHEN LEFT(c.custcode,1)='4' THEN '3'+RIGHT(c.custcode,LEN(c.custcode)-1)
                        WHEN LEFT(c.custcode,1)='6' THEN '5'+RIGHT(c.custcode,LEN(c.custcode)-1)
                        ELSE c.custcode
                    END as Customer_ID,
                    g.goodcode as item_id,
                    SUM(d.goodqty2) as ItemQty
                FROM Winspeed_PRD.dbo.soinvhd h
                LEFT JOIN Winspeed_PRD.dbo.soinvdt d ON h.soinvid = d.soinvid
                LEFT JOIN Winspeed_PRD.dbo.emgood  g ON d.goodid  = g.goodid
                LEFT JOIN Winspeed_PRD.dbo.emcust  c ON h.custid  = c.custid
                WHERE h.invno IN ($invoiceStr)
                GROUP BY
                    h.invno, g.goodcode,
                    CASE
                        WHEN LEFT(c.custcode,1)='2' THEN '1'+RIGHT(c.custcode,LEN(c.custcode)-1)
                        WHEN LEFT(c.custcode,1)='4' THEN '3'+RIGHT(c.custcode,LEN(c.custcode)-1)
                        WHEN LEFT(c.custcode,1)='6' THEN '5'+RIGHT(c.custcode,LEN(c.custcode)-1)
                        ELSE c.custcode
                    END
            ")->fetchAll(PDO::FETCH_ASSOC);

            // ดึง M3_BOXs map จาก iERP
            $m3Map = [];
            foreach ($ierp->query("
                SELECT A.IMA_ItemID,
                       ROUND(
                           CAST(B.Attribute21_Value AS DECIMAL(10,4)) /
                           CAST(B.Attribute17_Value AS DECIMAL(10,4))
                       ,4) AS M3_BOXs
                FROM iERP86_WDI.dbo.Item A
                INNER JOIN iERP86_WDI.dbo.ItemAttribute B
                    ON B.ItemAttr_IMA_RecordID = A.IMA_RecordID
                WHERE LEFT(A.IMA_ItemTypeCode,1) = '1'
                AND LEFT(A.IMA_ProdFam,3) = 'PSG'
                AND B.Attribute21_Value IS NOT NULL
                AND B.Attribute17_Value IS NOT NULL
            ")->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $m3Map[$s['IMA_ItemID']] = (float)$s['M3_BOXs'];
            }

            $himalai->beginTransaction();

            // INSERT detail + UPDATE M3
            foreach ($rows as $r) {
                $invoiceId  = $r['invoice_id'];
                $customerId = $r['Customer_ID'];
                $itemId     = $r['item_id'];
                $qty        = (float)$r['ItemQty'];
                $totalM3    = isset($m3Map[$itemId]) ? round($qty * $m3Map[$itemId], 4) : 0;

                $himalai->exec("
                    INSERT INTO psgdata.tblTransport_detail
                        (refCarID, invoice, Customer_ID, item_id, qty, M3)
                    VALUES
                        ($recordID, '$invoiceId', '$customerId', '$itemId', $qty, $totalM3)
                ");
            }

            // อัปเดต MAFlag ใน Winspeed
            foreach ($newInvoices as $invoice) {
                $stmtUpd = $winspeed->prepare("EXEC Winspeed_PRD.dbo.update_MAflag N'$invoice'");
                if (!$stmtUpd->execute()) {
                    throw new Exception("update MAFlag failed for invoice: $invoice");
                }
            }

            $himalai->commit();

            echo json_encode([
                "status"   => "success",
                "message"  => "เพิ่ม invoice ใหม่เรียบร้อย",
                "RecordID" => $recordID
            ]);
            exit;
        }

        // ======================================================
        // กรณีที่ 2 : ไม่มีรอบเปิดอยู่ → สร้างรอบใหม่
        // ======================================================

        // ตรวจสอบ Start KM ต้องไม่น้อยกว่า Stop KM ล่าสุด
        $last = $himalai->query("
            SELECT stop_KM
            FROM psgdata.tblTransport
            WHERE Car_registration = '$carReg'
            AND stop_KM IS NOT NULL
            ORDER BY start_time DESC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if ($last !== false && (int)$startKM < (int)$last['stop_KM']) {
            echo json_encode([
                "status"  => "error",
                "message" => "Start KM ห้ามน้อยกว่า Stop KM ล่าสุด ({$last['stop_KM']})"
            ]);
            exit;
        }

        // ดึงรายละเอียด invoice ทั้งหมดจาก Winspeed
        $invoiceStr = "'" . implode("','", $invoice_list) . "'";
        $rows = $winspeed->query("
            SELECT
                h.invno as invoice_id,
                CASE
                    WHEN LEFT(c.custcode,1)='2' THEN '1'+RIGHT(c.custcode,LEN(c.custcode)-1)
                    WHEN LEFT(c.custcode,1)='4' THEN '3'+RIGHT(c.custcode,LEN(c.custcode)-1)
                    WHEN LEFT(c.custcode,1)='6' THEN '5'+RIGHT(c.custcode,LEN(c.custcode)-1)
                    ELSE c.custcode
                END as Customer_ID,
                g.goodcode as item_id,
                SUM(d.goodqty2) as ItemQty
            FROM Winspeed_PRD.dbo.soinvhd h
            LEFT JOIN Winspeed_PRD.dbo.soinvdt d ON h.soinvid = d.soinvid
            LEFT JOIN Winspeed_PRD.dbo.emgood  g ON d.goodid  = g.goodid
            LEFT JOIN Winspeed_PRD.dbo.emcust  c ON h.custid  = c.custid
            WHERE h.invno IN ($invoiceStr)
            GROUP BY
                h.invno, g.goodcode,
                CASE
                    WHEN LEFT(c.custcode,1)='2' THEN '1'+RIGHT(c.custcode,LEN(c.custcode)-1)
                    WHEN LEFT(c.custcode,1)='4' THEN '3'+RIGHT(c.custcode,LEN(c.custcode)-1)
                    WHEN LEFT(c.custcode,1)='6' THEN '5'+RIGHT(c.custcode,LEN(c.custcode)-1)
                    ELSE c.custcode
                END
        ")->fetchAll(PDO::FETCH_ASSOC);

        // ดึง M3_BOXs map จาก iERP
        $m3Map = [];
        foreach ($ierp->query("
            SELECT A.IMA_ItemID,
                   ROUND(
                       CAST(B.Attribute21_Value AS DECIMAL(10,4)) /
                       CAST(B.Attribute17_Value AS DECIMAL(10,4))
                   ,4) AS M3_BOXs
            FROM iERP86_WDI.dbo.Item A
            INNER JOIN iERP86_WDI.dbo.ItemAttribute B
                ON B.ItemAttr_IMA_RecordID = A.IMA_RecordID
            WHERE LEFT(A.IMA_ItemTypeCode,1) = '1'
            AND LEFT(A.IMA_ProdFam,3) = 'PSG'
            AND B.Attribute21_Value IS NOT NULL
            AND B.Attribute17_Value IS NOT NULL
        ")->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $m3Map[$s['IMA_ItemID']] = (float)$s['M3_BOXs'];
        }

        $himalai->beginTransaction();

        // INSERT header รอบใหม่
        $himalai->exec("
            INSERT INTO psgdata.tblTransport (Car_registration, start_KM, start_time)
            VALUES ('$carReg', $startKM, '$datetime')
        ");

        $recordID = $himalai->query("
            SELECT RecordID FROM psgdata.tblTransport
            ORDER BY RecordID DESC LIMIT 1
        ")->fetchColumn();

        if (!$recordID) {
            throw new Exception("cannot get RecordID");
        }

        // INSERT detail ทุกรายการ
        foreach ($rows as $r) {
            $invoiceId  = $r['invoice_id'];
            $customerId = $r['Customer_ID'];
            $itemId     = $r['item_id'];
            $qty        = (float)$r['ItemQty'];
            $totalM3    = isset($m3Map[$itemId]) ? round($qty * $m3Map[$itemId], 4) : 0;

            $himalai->exec("
                INSERT INTO psgdata.tblTransport_detail
                    (refCarID, invoice, Customer_ID, item_id, qty, M3)
                VALUES
                    ($recordID, '$invoiceId', '$customerId', '$itemId', $qty, $totalM3)
            ");
        }

        // อัปเดต MAFlag ใน Winspeed
        foreach ($invoice_list as $invoice) {
            $stmtUpd = $winspeed->prepare("EXEC Winspeed_PRD.dbo.update_MAflag N'$invoice'");
            if (!$stmtUpd->execute()) {
                throw new Exception("update MAFlag failed for invoice: $invoice");
            }
        }

        $himalai->commit();

        echo json_encode([
            "status"   => "success",
            "RecordID" => $recordID
        ]);

    } catch (Exception $e) {
        if ($himalai->inTransaction()) {
            $himalai->rollBack();
        }
        echo json_encode([
            "status"  => "error",
            "message" => $e->getMessage()
        ]);
    }
    exit;
}
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


function show_Report_transport($ierp, $crit, $winspeed, $himalai)
{
    $date      = $crit['date']         ?? date('Y-m-d');
    $CarFilter = $crit['registration'] ?? '';
    $invoiceFilter = $crit['invoice'] ?? '';
$where  = [];
$params = [];
if (!empty($date)) {
    $where[] = "DATE(r.start_time) = :date";
    $params[':date'] = $date;
}
if ($invoiceFilter !== '') {
    $len = mb_strlen($invoiceFilter, 'UTF-8');
    $where[] = "LEFT(d.invoice, $len) = :inv";
    $params[':inv'] = $invoiceFilter;
}
if ($CarFilter !== '') {
    $where[] = "TRIM(r.Car_registration) = :reg";
    $params[':reg'] = $CarFilter;
}

$whereSql = "WHERE " . implode(" AND ", $where);
    $sql = "
        SELECT
            r.RecordID, r.Car_registration, r.start_KM, r.stop_KM, r.Fuel, r.Shipping,
            DATE(r.start_time) AS start_time,
            d.invoice, d.item_id, d.qty, d.M3
        FROM psgdata.tblTransport r
        LEFT JOIN psgdata.tblTransport_detail d ON r.RecordID = d.refCarID
        $whereSql
        ORDER BY r.RecordID DESC, d.lastupdate DESC
    ";

    $stmt = $himalai->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo json_encode([
        "status" => "error",
        "message" => "ไม่พบข้อมูล"
    ]);
    exit;
}

    // ---------- ดึงค่า M3 ต่อหน่วย (จาก iERP) ----------
  $itemIDs = array_unique(array_filter(array_column($rows, 'item_id')));
$m3Map   = [];

if (!empty($itemIDs)) {

    $itemStr = "'" . implode("','", $itemIDs) . "'";

    $sqlAttr = "
        SELECT 
            A.IMA_ItemID,
            CAST(B.Attribute17_Value AS DECIMAL(10,4)) AS Packsize,
            CAST(B.Attribute21_Value AS DECIMAL(10,4)) AS M3_Total,
            ROUND(
                CAST(B.Attribute21_Value AS DECIMAL(10,4)) /
                NULLIF(CAST(B.Attribute17_Value AS DECIMAL(10,4)),0)
            ,4) AS M3_BOXs
        FROM iERP86_WDI.dbo.Item A
        INNER JOIN iERP86_WDI.dbo.ItemAttribute B
            ON B.ItemAttr_IMA_RecordID = A.IMA_RecordID
        WHERE A.IMA_ItemID IN ($itemStr)
          AND B.Attribute21_Value IS NOT NULL
          AND B.Attribute17_Value IS NOT NULL
    ";

    $attrRows = $ierp->query($sqlAttr)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($attrRows as $a) {
        // เก็บค่า M3 ต่อกล่อง
        $m3Map[$a['IMA_ItemID']] = (float)$a['M3_BOXs'];
    }
}


    // ---------- คำนวณ M3  ----------
 
$rt = [];

foreach ($rows as $r) {

    $qty    = (float)($r['qty'] ?? 0);
    $itemID = $r['item_id'] ?? '';

    // ดึงค่า M3 ต่อกล่อง
    $M3_per_box = $m3Map[$itemID] ?? 0;

    // คำนวณ M3 รวม
    $Total_M3 = $M3_per_box * $qty;

    $r['M3_per_unit'] = $M3_per_box; // จริงๆ คือ per_box
    $r['Total_M3']    = $Total_M3;
    $rt[] = $r;
}

    // ---------- Group ตาม RecordID ----------

$grouped       = [];
$grandTotal_M3 = 0;

foreach ($rt as $row) {

    $recordId = $row['RecordID'];

    if (!isset($grouped[$recordId])) {

        $grouped[$recordId] = [
            'main' => [
                'Car_registration' => $row['Car_registration'],
                'start_KM'         => $row['start_KM'],
                'stop_KM'          => $row['stop_KM'],
                'Fuel'             => $row['Fuel'],
                'Shipping'         => $row['Shipping'],
                'start_time'       => $row['start_time'],
            ],
            'details'  => [],
            'total_m3' => 0,
        ];
    }

    if (!empty($row['invoice'])) {

        $grouped[$recordId]['details'][] = [
            'invoice'     => $row['invoice'],
            'item_id'     => $row['item_id'],
            'qty'         => $row['qty'],
            'M3_per_unit' => $row['M3_per_unit'], // per box
            'Total_M3'    => $row['Total_M3'],
        ];

        $grouped[$recordId]['total_m3'] += $row['Total_M3'];
        $grandTotal_M3 += $row['Total_M3'];
    }
}
    // =====================================================
    // ==================  HTML TABLE  =====================
    // =====================================================
    $dtid = $crit['tid'] ?? "_t2";

    $tdStyle      = "padding:8px;border:1px solid #ddd;";
    $tdRight      = "padding:8px;border:1px solid #ddd;text-align:right;";
    $tdCenter     = "padding:8px;border:1px solid #ddd;text-align:center;";
    $tdTotalM3    = "padding:8px;border:1px solid #ddd;text-align:right;font-weight:bold;background:#e8f5e9;color:#2e7d32;";
    $tdTotalSales = "padding:8px;border:1px solid #ddd;text-align:right;font-weight:bold;background:#e3f2fd;color:#1565c0;";

    $html = "<table id='t{$dtid}' style='width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:13px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.05);'>";

    // ---------- Header (13 คอลัมน์) ----------
    $html .= "
    <thead>
    <tr style='background:#ffb300;'>
        <th style='padding:10px;border:1px solid #ddd;'>ทะเบียนรถ</th>
        <th style='padding:10px;border:1px solid #ddd;'>ไมล์เริ่ม</th>
        <th style='padding:10px;border:1px solid #ddd;'>ไมล์สิ้นสุด</th>
        <th style='padding:10px;border:1px solid #ddd;'>Fuel</th>
        <th style='padding:10px;border:1px solid #ddd;'>Shipping</th>
        <th style='padding:10px;border:1px solid #ddd;'>วันที่</th>
        <th style='padding:10px;border:1px solid #ddd;'>Invoice</th>
        <th style='padding:10px;border:1px solid #ddd;'>Item</th>
        <th style='padding:10px;border:1px solid #ddd;'>Qty</th>
        <th style='padding:10px;border:1px solid #ddd;'>M³/Unit</th>
        <th style='padding:10px;border:1px solid #ddd;'>รวม M³</th>
    </tr>
    </thead>
    <tbody>";

    // ---------- Loop แต่ละ RecordID ----------
    foreach ($grouped as $g) {
        $main      = $g['main'];
        $details   = $g['details'];
        $rowspan   = max(1, count($details));
        $lastIndex = count($details) - 1;

        $html .= "<tr style='background:#f5f5f5;'>";

        // --- คอลัมน์ main (rowspan) ---
        $html .= "<td rowspan='$rowspan' style='{$tdStyle}'>{$main['Car_registration']}</td>";
        $html .= "<td rowspan='$rowspan' style='{$tdRight}'>" . number_format($main['start_KM'])    . "</td>";
        $html .= "<td rowspan='$rowspan' style='{$tdRight}'>" . number_format($main['stop_KM'])     . "</td>";
        $html .= "<td rowspan='$rowspan' style='{$tdRight}'>" . number_format($main['Fuel'],    2)  . "</td>";
        $html .= "<td rowspan='$rowspan' style='{$tdRight}'>" . number_format($main['Shipping'], 2) . "</td>";
        $html .= "<td rowspan='$rowspan' style='{$tdCenter}'>{$main['start_time']}</td>";

        if (!empty($details)) {

            foreach ($details as $idx => $d) {
                $isFirst   = ($idx === 0);
                $isLastRow = ($idx === $lastIndex);

                // แถวแรกอยู่ใน <tr> ที่เปิดไว้แล้ว, แถวถัดไปเปิด <tr> ใหม่
                if (!$isFirst) {
                    $html .= "<tr>";
                }

                // --- Invoice / Item / Qty  (แต่ละ detail row) ---
                $html .= "<td style='{$tdStyle}'>{$d['invoice']}</td>";
                $html .= "<td style='{$tdStyle}'>{$d['item_id']}</td>";
                $html .= "<td style='{$tdRight}'>"  . number_format($d['qty'])           . "</td>";
                $html .= "<td style='{$tdRight}'>"  . number_format($d['M3_per_unit'], 4) . "</td>";

                // --- รวม M³ และ รวม Sales amt เฉพาะแถวสุดท้ายของ group ---
                if ($isLastRow) {
                    $html .= "<td style='{$tdTotalM3}'>"    . number_format($g['total_m3'],    4) . "</td>";

                } else {
                    $html .= "<td></td>";
                }

                $html .= "</tr>";
            }

        } else {
            // ไม่มี detail
            $html .= "<td colspan='7' style='{$tdCenter}'>-</td></tr>";
        }
    }

    // ---------- Grand Total (13 คอลัมน์) ----------
$html .= "
<tr style='background:#1976d2;color:white;font-weight:bold;font-size:14px;'>
    <td colspan='10' style='padding:10px;text-align:right;border:1px solid #ddd; color: wheat;'>
        รวมทั้งหมด
    </td>
    <td style='padding:10px;text-align:right;border:1px solid #ddd; color: wheat;'>"
       . number_format($grandTotal_M3, 4) . "
    </td>
</tr>";

    $html .= "</tbody></table>";

    echo json_encode([
        "table1" => [
            "table" => [$html, "", "", ""],
            "tid"   => "#t{$dtid}",
            "cid"   => "#c{$dtid}",
            "tit"   => "รายงานการขนส่ง"
        ],
        "grandTotal_M3"    => $grandTotal_M3,
    ]);
    exit;
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


function show_Report_BOX_transport($ierp, $crit, $winspeed, $himalai)
{
    $date = $crit['date'] ?? date('Y-m-d');

    $sql = "
    SELECT
        r.RecordID, r.Car_registration, r.start_KM, r.stop_KM,
        DATE_FORMAT(r.start_time, '%Y-%m-%d %H:%i') AS start_time,
        d.invoice, d.item_id, d.qty, d.M3
    FROM psgdata.tblTransport r
    LEFT JOIN psgdata.tblTransport_detail d ON r.RecordID = d.refCarID
    WHERE DATE(r.start_time) = :date
    ";

    $stmt = $himalai->prepare($sql);
    $stmt->bindValue(':date', $date);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        header('Content-Type: application/json');
        echo json_encode([
            "table1" => [
                "table" => ["<div>ไม่พบข้อมูล</div>", "", "", ""],
                "tid" => "#grid1",
                "cid" => "#c1",
                "tit" => "รายงานการขนส่ง",
                "hi" => -25,
                "wi" => 5
            ],
            "rt" => [],
            "record_count" => 0,
            "car_count" => 0,
            "grid_cells" => 64
        ]);
        exit;
    }

    $itemIDs = array_unique(array_filter(array_column($rows, 'item_id')));
    $m3Map = [];
    
    if (!empty($itemIDs)) {
        $itemStr = "'" . implode("','", $itemIDs) . "'";
        $sqlAttr = "SELECT A.IMA_ItemID, CAST(B.Attribute21_Value AS DECIMAL(10,4)) AS M3 ,
        round(CAST(B.Attribute21_Value as DECIMAL(10, 4))/(cast(B.Attribute17_Value as DECIMAL(10, 4)) ),4) as [M3_BOXs]
                    FROM iERP86_WDI.dbo.Item A 
                    INNER JOIN iERP86_WDI.dbo.ItemAttribute B ON B.ItemAttr_IMA_RecordID = A.IMA_RecordID 
                    WHERE A.IMA_ItemID IN ($itemStr) AND B.Attribute21_Value IS NOT NULL";
        $attrRows = $ierp->query($sqlAttr)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($attrRows as $a) {
            $m3Map[$a['IMA_ItemID']] = $a['M3_BOXs'];
        }
    }

    $result = [];
    foreach ($rows as $r) {
        $rid = $r['RecordID'];
        if (!isset($result[$rid])) {
            $result[$rid] = [
                "RecordID" => $r['RecordID'],
                "Car_registration" => $r['Car_registration'],
                "start_KM" => $r['start_KM'],
                "stop_KM" => $r['stop_KM'],
                "start_time" => $r['start_time'],
                "invoice_count" => [],
                "item_count" => [],
                "total_qty" => 0,
                "total_M3" => 0
            ];
        }

        if (!empty($r['invoice'])) {
            $result[$rid]['invoice_count'][$r['invoice']] = true;
        }
        if (!empty($r['item_id'])) {
            $result[$rid]['item_count'][$r['item_id']] = true;
        }

        $qty = (float)$r['qty'];
        $result[$rid]['total_qty'] += $qty;

        $m3Value = isset($m3Map[$r['item_id']]) ? $m3Map[$r['item_id']] : (float)($r['M3_BOXs'] ?? 0);
        $result[$rid]['total_M3'] += ($m3Value * $qty);
    }

    $rt = [];
    foreach ($result as $r) {
        $r['invoice_count'] = count($r['invoice_count']);
        $r['item_count'] = count($r['item_count']);
        $rt[] = $r;
    }

    $dtid = $crit['tid'] ?? "_t3";

    $groupedByCar = [];
    foreach ($rt as $row) {
        $carReg = $row['Car_registration'];
        if (!isset($groupedByCar[$carReg])) {
            $groupedByCar[$carReg] = [];
        }
        $groupedByCar[$carReg][] = [
            'recordID' => $row['RecordID'],
            'car_registration' => $row['Car_registration'],
            'start_time' => $row['start_time'],
            'trans_start' => $row['start_KM'],
            'trans_end' => $row['stop_KM'],
            'invoice_count' => $row['invoice_count'],
            'item_count' => $row['item_count'],
            'total_qty' => $row['total_qty'],
            'total_M3' => $row['total_M3']
        ];
    }

    foreach ($groupedByCar as $carReg => &$records) {
        usort($records, function($a, $b) {
            return intval($a['recordID']) - intval($b['recordID']);
        });
    }
    unset($records);

    $gridCells = [];
    $cellIndex = 0;
    
    foreach ($groupedByCar as $carReg => $records) {
        $colCount = 0;
        foreach ($records as $record) {
            if ($cellIndex >= 64) break 2;
            if ($colCount >= 8) break;
            $gridCells[$cellIndex] = $record;
            $cellIndex++;
            $colCount++;
        }
        while ($colCount < 8 && $cellIndex < 64) {
            $gridCells[$cellIndex] = null;
            $cellIndex++;
            $colCount++;
        }
    }
    
    while (count($gridCells) < 64) {
        $gridCells[] = null;
    }

    $html = '<div style="padding:20px;background-color:#f0f0f0;"><style>#grid' . $dtid . ' colgroup { display: none !important; }</style><div id="grid' . $dtid . '" style="display:grid;grid-template-columns:repeat(8,1fr);grid-template-rows:repeat(8,1fr);gap:8px;background-color:#f5f5f5;">';
    
    for ($i = 0; $i < 64; $i++) {
        $cell = $gridCells[$i];
        
        if ($cell === null) {
            $html .= '<div style="border:2px dashed #ccc;border-radius:6px;background-color:#fafafa;min-height:120px;"></div>';
        } else {
            $car = htmlspecialchars($cell['car_registration'] ?? '-');
            $rid = htmlspecialchars($cell['recordID'] ?? '');
            $date_val = htmlspecialchars($cell['start_time'] ?? '-');
            $start = htmlspecialchars($cell['trans_start'] ?? '-');
            $end = htmlspecialchars($cell['trans_end'] ?? '-');
            $inv = number_format(intval($cell['invoice_count'] ?? 0), 0);
            $item = number_format(intval($cell['item_count'] ?? 0), 0);
            $qty = number_format(floatval($cell['total_qty'] ?? 0), 2);
            $raw_m3 = floatval($cell['total_M3'] ?? 0);
            $m3 = number_format($raw_m3, 4);

            $maxM3 = 7.9;
            $percent = ($maxM3 > 0) ? ($raw_m3 / $maxM3) * 100 : 0;
            $percent = min(100, round($percent, 1));
            $startDisplay = is_numeric($start) ? number_format($start) : $start;
            $endDisplay   = is_numeric($end) ? number_format($end) : $end;
            if ($percent <= 40) {
                $m3Color = '#000';
                $bgColor = '#ffac33';
            } elseif ($percent <= 70) {
                $m3Color = '#000';
                $bgColor = '#ffdd56';
            } else {
                $m3Color = '#000';
                $bgColor = '#00f969';
            }

            $html .= '<div style="border:2px solid #ddd;border-radius:8px;padding:10px;background:linear-gradient(to bottom,#ffffff,#f9f9f9);box-shadow:0 2px 4px rgba(0,0,0,0.1);min-height:130px;display:flex;flex-direction:column;justify-content:space-between;font-size:11px;" data-recordid="'.$rid.'" data-car="'.$car.'" data-index="'.$i.'">
<div style="background:linear-gradient(to right,#ffd700,#ffcc00);color:#333;font-weight:bold;padding:6px;border-radius:6px;margin-bottom:6px;text-align:center;font-size:11px;">'.$car.'</div>
<div style="line-height:1.5;background-color:'.$bgColor.';border-radius:6px;padding:6px;">
<div style="margin-bottom:4px;"><span style="font-size:11px;color:#333;font-weight:500;">📅 '.$date_val.'</span></div>
<div style="margin-bottom:6px;background:#eef3ff;border-radius:4px;padding:4px;text-align:center;"><span style="font-size:13px;color:#5c6bc0;font-weight:600;">🛞 '.$startDisplay.' → '.$endDisplay.' km</span></div>
<div style="display:flex;gap:8px;margin-bottom:6px;">
<div style="flex:1;text-align:center;"><div style="font-size:9px;color:#777;">📄 Inv</div><div style="font-size:12px;font-weight:600;color:#333;">'.$inv.'</div></div>
<div style="flex:1;text-align:center;"><div style="font-size:9px;color:#777;">📦 Item</div><div style="font-size:12px;font-weight:600;color:#333;">'.$item.'</div></div>
</div>
<div style="display:flex;gap:8px;">
<div style="flex:1;text-align:center;"><div style="font-size:9px;color:#777;">QTY</div><div style="font-size:12px;font-weight:600;color:#333;">'.$qty.'</div></div>
<div style="flex:1;border-radius:6px;padding:4px;text-align:center;"><div style="font-size:9px;color:#555;">M³</div><div style="font-size:13px;color:'.$m3Color.';font-weight:bold;">'.$m3.'</div><div style="font-size:9px;color:#666;">'.$percent.'%</div></div>
</div>
</div>
</div>';
        }
    }

    $html .= '</div></div>';

    header('Content-Type: application/json');
    echo json_encode([
        "table1" => [
            "table" => [$html, "", "", ""],
            "tid" => "#grid{$dtid}",
            "cid" => "#c{$dtid}",
            "tit" => "รายงานการขนส่ง",
            "hi" => -25,
            "wi" => 5
        ],
        "rt" => $rt,
        "record_count" => count($rt),
        "car_count" => count($groupedByCar),
        "grid_cells" => 64
    ]);
    exit;
}


function Add_license($ierp, $crit, $winspeed, $himalai)
{
    try {

        $sql = "INSERT INTO psgdata.tblTransport_regist
                (car_regist, driver, car_atten)
                VALUES
                ('{$crit['plate']}', 
                 '{$crit['driver']}', 
                 '{$crit['car_atten']}')";

        $stmt = $himalai->prepare($sql);
        $stmt->execute();

        echo json_encode([
            "status" => "success"
        ]);

    } catch (PDOException $e) {

        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }

    exit;
}



/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////





function show_Report_Sum_transport($ierp, $crit, $winspeed, $himalai)
{
    $date          = $crit['date']         ?? date('Y-m-d');
    $CarFilter     = $crit['registration'] ?? '';
    $invoiceFilter = $crit['invoice']      ?? '';

    $where  = [];
    $params = [];

    if (!empty($date)) {
        $where[]         = "DATE(r.start_time) = :date";
        $params[':date'] = $date;
    }
    if ($invoiceFilter !== '') {
        $len     = mb_strlen($invoiceFilter, 'UTF-8');
        $where[] = "LEFT(d.invoice, $len) = :inv";
        $params[':inv'] = $invoiceFilter;
    }
    if ($CarFilter !== '') {
        $where[]        = "TRIM(r.Car_registration) = :reg";
        $params[':reg'] = $CarFilter;
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "
        SELECT
            r.RecordID, r.Car_registration, r.start_KM, r.stop_KM, r.Fuel, r.Shipping,
            DATE(r.start_time) AS start_time,
            d.invoice, d.item_id, d.qty, d.M3
        FROM psgdata.tblTransport r
        LEFT JOIN psgdata.tblTransport_detail d ON r.RecordID = d.refCarID
        $whereSql
        ORDER BY r.RecordID DESC, d.lastupdate DESC
    ";

    $stmt = $himalai->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo json_encode(["status" => "error", "message" => "ไม่พบข้อมูล"]);
        exit;
    }

    // ---------- ดึง Sales_amt + CustomerName จาก $winspeed ----------
    $salesMap    = [];   // key: "invoice_id|item_id" → Sales_amt
    $custNameMap = [];   // key: "invoice_id"         → CustomerName

    $invoices = array_unique(array_filter(array_column($rows, 'invoice')));
    $itemIDs2 = array_unique(array_filter(array_column($rows, 'item_id')));

    if (!empty($invoices) && !empty($itemIDs2)) {
        $invoicesStr = "'" . implode("','", $invoices) . "'";
        $itemsStr    = "'" . implode("','", $itemIDs2) . "'";

        $sqlSales = "
            SELECT
                h.invno                        AS invoice_id,
                g.goodcode                     AS item_id,
                c.CustName                     AS CustomerName,
                SUM(d.goodqty2)                AS ItemQty,
                SUM(d.goodqty2 * d.goodprice2) AS Sales_amt
            FROM Winspeed_PRD.dbo.soinvhd h WITH (NOLOCK)
            LEFT JOIN Winspeed_PRD.dbo.emcust  c WITH (NOLOCK) ON h.custid  = c.custid
            LEFT JOIN Winspeed_PRD.dbo.soinvdt d WITH (NOLOCK) ON h.soinvid = d.soinvid
            LEFT JOIN Winspeed_PRD.dbo.emgood  g WITH (NOLOCK) ON d.goodid  = g.goodid
            WHERE h.invno      IN ($invoicesStr)
              AND g.goodcode   IN ($itemsStr)
              AND h.docutype    = '107'
              AND h.DocuStatus <> 'C'
            GROUP BY h.invno, g.goodcode, c.CustName
        ";

        $salesRows = $winspeed->query($sqlSales)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($salesRows as $s) {
            $key             = $s['invoice_id'] . '|' . $s['item_id'];
            $salesMap[$key]  = (float)$s['Sales_amt'];
            $custNameMap[$s['invoice_id']] = $s['CustomerName'] ?? '-';
        }
    }

    // ---------- attach Sales_amt + CustomerName ต่อ row ----------
    foreach ($rows as &$r) {
        $salesKey       = ($r['invoice'] ?? '') . '|' . ($r['item_id'] ?? '');
        $r['Sales_amt']     = $salesMap[$salesKey] ?? 0.0;
        $r['CustomerName']  = $custNameMap[$r['invoice'] ?? ''] ?? '-';
    }
    unset($r);

    // ---------- Group: RecordID → CustomerName → invoices ----------
    // grouped[RecordID] = {
    //   main:         { Car_registration, start_KM, ... }
    //   customers:    { CustomerName → { invoices: [inv_id,...], sales: float } }
    //   total_sales:  float
    // }
    $grouped          = [];
    $grandTotal_Sales = 0;

    foreach ($rows as $row) {
        $recordId = $row['RecordID'];
        $inv      = $row['invoice']      ?? '';
        $cust     = $row['CustomerName'] ?? '-';

        if (!isset($grouped[$recordId])) {
            $grouped[$recordId] = [
                'main' => [
                    'Car_registration' => $row['Car_registration'],
                    'start_KM'         => $row['start_KM'],
                    'stop_KM'          => $row['stop_KM'],
                    'Fuel'             => $row['Fuel'],
                    'Shipping'         => $row['Shipping'],
                    'start_time'       => $row['start_time'],
                ],
                'customers'   => [],
                'total_sales' => 0,
            ];
        }

        if ($inv === '') continue;

        // สร้าง customer entry ถ้ายังไม่มี
        if (!isset($grouped[$recordId]['customers'][$cust])) {
            $grouped[$recordId]['customers'][$cust] = [
                'invoices' => [],   // unique invoice_id list
                'sales'    => 0.0,
            ];
        }

        // รวม Sales_amt ต่อ invoice ของ customer นี้ (กัน duplicate item)
        if (!isset($grouped[$recordId]['customers'][$cust]['invoices'][$inv])) {
            $grouped[$recordId]['customers'][$cust]['invoices'][$inv] = 0.0;
        }
        $grouped[$recordId]['customers'][$cust]['invoices'][$inv] += $row['Sales_amt'];
        $grouped[$recordId]['customers'][$cust]['sales']          += $row['Sales_amt'];
        $grouped[$recordId]['total_sales']                        += $row['Sales_amt'];
        $grandTotal_Sales                                         += $row['Sales_amt'];
    }

    // ---------- HTML TABLE ----------
    $dtid = $crit['tid'] ?? "_t2";

    $th      = "padding:10px;border:1px solid #ddd;text-align:center;";
    $td      = "padding:8px;border:1px solid #ddd;";
    $tdr     = "padding:8px;border:1px solid #ddd;text-align:right;";
    $tdc     = "padding:8px;border:1px solid #ddd;text-align:center;";
    $tdCust  = "padding:8px;border:1px solid #ddd;background:#fff9c4;font-weight:bold;";
    $tdCount = "padding:8px;border:1px solid #ddd;text-align:center;background:#fff9c4;font-weight:bold;color:#e65100;";
    $tdSales = "padding:8px;border:1px solid #ddd;text-align:right;background:#e3f2fd;font-weight:bold;color:#1565c0;";
    $tdTotal = "padding:8px;border:1px solid #ddd;text-align:right;background:#c8e6c9;font-weight:bold;color:#1b5e20;";

    // 9 คอลัมน์: main(6) + CustomerName + จำนวน Invoice + Sales amt
    $html = "<table id='t{$dtid}' style='width:100%;border-collapse:collapse;
             font-family:Arial,sans-serif;font-size:13px;background:#fff;
             box-shadow:0 2px 8px rgba(0,0,0,.05);'>";

    $html .= "
    <thead>
    <tr style='background:#ffb300;'>
        <th style='{$th}'>ทะเบียนรถ</th>
        <th style='{$th}'>ไมล์เริ่ม</th>
        <th style='{$th}'>ไมล์สิ้นสุด</th>
        <th style='{$th}'>Fuel</th>
        <th style='{$th}'>Shipping</th>
        <th style='{$th}'>วันที่</th>
        <th style='{$th}'>CustomerName</th>
        <th style='{$th}'>Invoice</th>
        <th style='{$th}'>Sales amt</th>
    </tr>
    </thead>
    <tbody>";

    foreach ($grouped as $g) {
        $main      = $g['main'];
        $customers = $g['customers'];   // [ custName => { invoices, sales } ]
        $custCount = count($customers);
        $rowspan   = max(1, $custCount);

        // แถวแรก — main + customer แรก
        $html .= "<tr style='background:#f5f5f5;'>";
        $html .= "<td rowspan='{$rowspan}' style='{$td}'>{$main['Car_registration']}</td>";
        $html .= "<td rowspan='{$rowspan}' style='{$tdr}'>" . number_format($main['start_KM'])    . "</td>";
        $html .= "<td rowspan='{$rowspan}' style='{$tdr}'>" . number_format($main['stop_KM'])     . "</td>";
        $html .= "<td rowspan='{$rowspan}' style='{$tdr}'>" . number_format($main['Fuel'],    2)  . "</td>";
        $html .= "<td rowspan='{$rowspan}' style='{$tdr}'>" . number_format($main['Shipping'], 2) . "</td>";
        $html .= "<td rowspan='{$rowspan}' style='{$tdc}'>{$main['start_time']}</td>";

        if (!empty($customers)) {
            $custIdx = 0;
            foreach ($customers as $custName => $custData) {
                $invCount  = count($custData['invoices']);
                $custSales = $custData['sales'];

                // แถวแรกอยู่ใน <tr> ที่เปิดไว้แล้ว, แถวถัดไปเปิด <tr> ใหม่
                if ($custIdx > 0) {
                    $html .= "<tr>";
                }

                $html .= "<td style='{$tdCust}'>" . htmlspecialchars($custName) . "</td>";
                $html .= "<td style='{$tdCount}'>{$invCount}</td>";
                $html .= "<td style='{$tdSales}'>" . number_format($custSales, 2) . "</td>";
                $html .= "</tr>";

                $custIdx++;
            }

            // แถวรวมทั้งหมดของรถ 1 คัน
            $html .= "
            <tr style='background:#e8f5e9;'>
                <td colspan='8' style='{$td}text-align:right;font-weight:bold;color:#2e7d32;background-color: skyblue;'>
                    รวม " . count($customers) . " ลูกค้า
                </td>
                <td style='{$tdTotal}background-color: skyblue;'>" . number_format($g['total_sales'], 2) . "</td>
            </tr>";

        } else {
            $html .= "<td colspan='3' style='{$tdc}'>-</td></tr>";
        }
    }

    // Grand Total
    $html .= "
    <tr style='background:#1976d2;color:wheat;font-weight:bold;font-size:14px;'>
        <td colspan='8' style='padding:10px;text-align:right;border:1px solid #ddd;color: wheat;'>
            รวมทั้งหมด
        </td>
        <td style='padding:10px;text-align:right;border:1px solid #ddd;color: wheat;'>"
           . number_format($grandTotal_Sales, 2) . "
        </td>
    </tr>";

    $html .= "</tbody></table>";

    echo json_encode([
        "table1" => [
            "table" => [$html, "", "", ""],
            "tid"   => "#t{$dtid}",
            "cid"   => "#c{$dtid}",
            "tit"   => "รายงานการขนส่ง (สรุป)"
        ],
        "grandTotal_Sales" => $grandTotal_Sales,
    ]);
    exit;
}