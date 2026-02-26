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



function get_last_km($ierp, $crit, $winspeed,$himalai)
{

    $reg  = $crit['carReg'];

    $sql = "
        SELECT Trans_endDate
        FROM testdat.tblTransportstra_registration
        WHERE Car_registration = N'{$reg}'
        ORDER BY Trans_endDate DESC
        LIMIT 1
    ";
    
    $result = $himalai->query($sql)->fetch(PDO::FETCH_ASSOC);
    
    return json_encode([
        "success" => true,
        "data" => $result ?: null
    ]);
}


function get_last_invoice_id($ierp, $crit, $winspeed,$himalai)
{
    $invoiceFilter = $crit['invoiceFilter'] ?? '';

    $whereInvoice = '';
    if ($invoiceFilter !== '') {
        $len = mb_strlen($invoiceFilter, 'UTF-8');
        $whereInvoice = " AND LEFT(Winspeed_PRD.dbo.soinvhd.InvNo, $len) = N'$invoiceFilter' ";
    }

    $sql = "
    SELECT TOP 50
        Winspeed_PRD.dbo.soinvhd.invno as invoice_id,
        Winspeed_PRD.dbo.emcust.CustName as CustomerName
    FROM Winspeed_PRD.dbo.soinvhd
    LEFT JOIN Winspeed_PRD.dbo.emcust
        ON Winspeed_PRD.dbo.soinvhd.custid = Winspeed_PRD.dbo.emcust.custid
    WHERE
        Winspeed_PRD.dbo.soinvhd.DocuDate > convert(date,getdate()-1)
        AND Winspeed_PRD.dbo.soinvhd.docutype='107'
        AND Winspeed_PRD.dbo.soinvhd.DocuStatus <> 'C'
        AND Winspeed_PRD.dbo.soinvhd.MAFlag is null
        $whereInvoice
    ORDER BY Winspeed_PRD.dbo.soinvhd.invno DESC
    ";

    $result = $winspeed->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    return json_encode([
        "success" => true,
        "data" => $result
    ]);
}


function show_transport($ierp, $crit, $winspeed,$himalai)
{
    $CarFilter      = $crit['registration'] ?? '';
    $RecordIDFilter = $crit['RecordID'] ?? '';
    $date           = $crit['date'] ?? '';
    $invoiceFilter  = $crit['invoice_id'] ?? '';

    /* ===============================
       🔹 กรณีเรียกดูรายละเอียด invoice
    =============================== */
    if ($RecordIDFilter !== '') {

        /* ===============================
           🔹 ดึง existing invoice
        =============================== */
        $sqlExisting = "
            SELECT invoice
            FROM testdat.tblTransportstra_registration_detail
            WHERE refCarID = '$RecordIDFilter' AND (act IS NULL OR act != 1)
        ";

        $existingInvoices = $himalai
            ->query($sqlExisting)
            ->fetchAll(PDO::FETCH_COLUMN);

        $hasExisting = !empty($existingInvoices);
        $existingList = '';
        if ($hasExisting) {
            $existingList = "'" . implode("','", $existingInvoices) . "'";
        }

        /* ===============================
           🔹 limit
        =============================== */
        $limit = ($invoiceFilter !== '') ? 20 : 50;

        /* ===============================
           🔹 search invoice
        =============================== */
        $whereInvoice = '';
        if ($invoiceFilter !== '') {
            $len = mb_strlen($invoiceFilter, 'UTF-8');
            $whereInvoice = " AND LEFT(h.InvNo, $len) = N'$invoiceFilter' ";
        }

        /* ===============================
           🔹 เงื่อนไข dynamic (สำคัญ)
        =============================== */
        $selectExisting = '';
        $whereExisting  = '';

        if ($hasExisting) {
            // แสดง invoice เดิมของทะเบียนนี้ได้
            $selectExisting = "
                ,CASE 
                    WHEN h.InvNo IN ($existingList) THEN 1
                    ELSE 0
                END AS is_existing
            ";

            $whereExisting = "
                AND (
                    h.MAFlag IS NULL
                    OR h.InvNo IN ($existingList)
                )
            ";
        } else {
            // ทะเบียนใหม่ → ห้าม invoice ที่ MAFlag = Y
            $whereExisting = "
                AND h.MAFlag IS NULL
            ";
        }

        /* ===============================
           🔹 MAIN QUERY
        =============================== */
        $sql01 = "
            SELECT DISTINCT TOP $limit
                CAST(h.DocuDate AS DATE) AS [Date],
                h.InvNo AS invoice_id,
                CASE 
                    WHEN LEFT(c.CustCode, 1) = '2' THEN '1' + RIGHT(c.CustCode, LEN(c.CustCode) - 1)
                    WHEN LEFT(c.CustCode, 1) = '4' THEN '3' + RIGHT(c.CustCode, LEN(c.CustCode) - 1)
                    WHEN LEFT(c.CustCode, 1) = '6' THEN '5' + RIGHT(c.CustCode, LEN(c.CustCode) - 1)
                    ELSE c.CustCode
                END AS Customer_ID,
                c.CustName AS CustomerName,         
                g.GoodCode AS item_id,          
                SUM(d.GoodQty2) AS ItemQty
                $selectExisting
            FROM Winspeed_PRD.dbo.SOInvHD h WITH (NOLOCK)
            LEFT JOIN Winspeed_PRD.dbo.SOInvDT d WITH (NOLOCK)
                ON h.SOInvID = d.SOInvID
            LEFT JOIN Winspeed_PRD.dbo.EMGood g WITH (NOLOCK)
                ON d.GoodID = g.GoodID
            LEFT JOIN Winspeed_PRD.dbo.EMCust c WITH (NOLOCK)
                ON h.CustID = c.CustID
            WHERE
                h.DocuDate > CONVERT(date, GETDATE() - 1)
                AND h.DocuType = '107'
                AND h.DocuStatus <> 'C'
                $whereExisting
                $whereInvoice
            GROUP BY
                CAST(h.DocuDate AS DATE),
                h.InvNo,
                g.GoodCode,
                c.CustName,
                CASE 
                    WHEN LEFT(c.CustCode, 1) = '2' THEN '1' + RIGHT(c.CustCode, LEN(c.CustCode) - 1)
                    WHEN LEFT(c.CustCode, 1) = '4' THEN '3' + RIGHT(c.CustCode, LEN(c.CustCode) - 1)
                    WHEN LEFT(c.CustCode, 1) = '6' THEN '5' + RIGHT(c.CustCode, LEN(c.CustCode) - 1)
                    ELSE c.CustCode
                END
        ";

        $rt01 = $winspeed->query($sql01)->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "data"    => $rt01
        ]);
        exit;
    }

    /* ===============================
       🔹 แสดงตารางหลัก (TABLE 1)
    =============================== */

    // ---- Create WHERE สำหรับ TABLE 1 ----
    $where = [];

    if ($CarFilter !== '') {
        $len = mb_strlen($CarFilter, 'UTF-8');
        $where[] = "LEFT(Car_registration, $len) = '$CarFilter'";
    }

    if ($date !== '') {
    $where[] = "DATE(Driving_date) = '$date'";
    }

    $whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

    // ---- SQL TABLE 1 ----
    $sql = "
        SELECT RecordID, Car_registration, Trans_startDate, Trans_endDate, 
               Fuel_costs, Shipping_costs,
               DATE(Driving_date) AS Driving_date, create_date
        FROM testdat.tblTransportstra_registration
        $whereSql
        ORDER BY create_date DESC
    ";

    $rt = $himalai->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $messageInfo = '';
    $isNewRecord = false;

    // ✅ ถ้าไม่มีข้อมูลในวันที่เลือก → หาข้อมูลวันล่าสุด
    if (empty($rt) && $CarFilter !== '' && $date !== '') {
        $len = mb_strlen($CarFilter, 'UTF-8');
        
        $sqlLatest = "
            SELECT TOP 1 
                RecordID, 
                Car_registration, 
                Trans_startDate, 
                Trans_endDate,
                Fuel_costs,
                Shipping_costs,
                CAST(Driving_date AS date) AS Driving_date,
                create_date
            FROM testdat.tblTransportstra_registration
            WHERE LEFT(Car_registration, $len) = '$CarFilter'
              AND DATE(Driving_date) < '$date'
            ORDER BY Driving_date DESC, create_date DESC
            LIMIT 1
        ";
        
        $rtLatest = $himalai->query($sqlLatest)->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rtLatest)) {
            $latestDate = $rtLatest[0]['Driving_date'];
            $messageInfo = "⚠️ วันนี้ ({$date}) ยังไม่มีข้อมูล | ข้อมูลล่าสุด: {$latestDate}";
            
            // สร้างแถวใหม่สำหรับวันนี้
            $rt = [[
                'RecordID' => null,
                'Car_registration' => $rtLatest[0]['Car_registration'],
                'Trans_startDate' => $rtLatest[0]['Trans_endDate'], // ใช้ End KM ของวันก่อนเป็น Start KM
                'Trans_endDate' => null,
                'Driving_date' => $date,
                'create_date' => date('Y-m-d H:i:s'),
                'is_new' => true
            ]];
            $isNewRecord = true;
        } else {
            // ไม่เคยมีข้อมูลเลย
            $messageInfo = "⚠️ ไม่พบข้อมูลทะเบียน {$CarFilter}";
            $rt = [[
                'RecordID' => null,
                'Car_registration' => $CarFilter,
                'Trans_startDate' => null,
                'Trans_endDate' => null,
                'Driving_date' => $date,
                'create_date' => date('Y-m-d H:i:s'),
                'is_new' => true
            ]];
            $isNewRecord = true;
        }
    }

    // ---- Table headers TABLE 1 ----
    $tb = [
        ["th" => "Car registration", "cols" => "120", "style" => "background-color:#ffcc00;"],
        ["th" => "Start KM", "cols" => "100", "style" => "background-color:#ffcc00;"],
        ["th" => "End KM", "cols" => "100", "style" => "background-color:#ffcc00;"],
        ["th" => "Fuel costs", "cols" => "100", "style" => "background-color:#ffcc00;"],
        ["th" => "Shipping costs", "cols" => "100", "style" => "background-color:#ffcc00;"],
        ["th" => "Action", "cols" => "100", "style" => "background-color:#ffcc00;"],
        ["th" => "Detail", "cols" => "70", "style" => "background-color:#ffcc00;"],
    ];

    $dtid = $crit['dtid'] ?? 1;

    // ---- Build TABLE 1 ----
    $html = "";
    
    // แสดงข้อความแจ้งเตือน
    if ($messageInfo !== '') {
        $html .= "<div style='padding:10px; background:#fff3cd; border:1px solid #ffc107; margin-bottom:10px; border-radius:4px;'>{$messageInfo}</div>";
    }
    
    $html .= "<table id='t{$dtid}' class='datatable' style='width:100%; border-collapse:collapse;'>
        <thead>
            <tr>";
    
    foreach ($tb as $column) {
        $html .= "<th style='{$column['style']} padding:8px; border:1px solid #ddd;'>{$column['th']}</th>";
    }
    
    $html .= "</tr></thead><tbody>";

    if (!empty($rt)) {
        
        // ✅ แสดงแถวล่าสุดก่อน (ให้กรอก End KM, Fuel costs, Shipping costs ได้)
        $firstRow = $rt[0];
        $recordId = $firstRow['RecordID'];
        $carReg   = $firstRow['Car_registration'];
        $startKM  = $firstRow['Trans_startDate'];
        $endKM    = $firstRow['Trans_endDate'];
        $fuelCosts = $firstRow['Fuel_costs'] ?? null;
        $shippingCosts = $firstRow['Shipping_costs'] ?? null;

        $html .= "<tr class='main-row' data-recordid='{$recordId}' data-refcarid='{$recordId}'>";
        $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$carReg}</td>";
        $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$startKM}</td>";

        /* =========================
           ✅ ถ้า EndKM ว่าง → ให้กรอกได้ทั้ง End KM, Fuel costs, Shipping costs
        ========================= */
        if ($endKM === null || $endKM === '') {
            // End KM
            $html .= "
                <td style='padding:8px; border:1px solid #ddd;'>
                    <input type='text'
                        class='input-end-km'
                        id='end_km_{$recordId}'
                        style='width:100%; padding:5px; box-sizing:border-box;'
                        placeholder='End KM'>
                </td>
            ";

            // Fuel costs
            $html .= "
                <td style='padding:8px; border:1px solid #ddd;'>
                    <input type='number'
                        class='input-fuel-costs'
                        id='fuel_costs_{$recordId}'
                        step='1'
                        min='0'
                        style='width:100%; padding:5px; box-sizing:border-box;'
                        placeholder='ค่าน้ำมัน'>
                </td>
            ";

            // Shipping costs
            $html .= "
                <td style='padding:8px; border:1px solid #ddd;'>
                    <input type='number'
                        class='input-shipping-costs'
                        id='shipping_costs_{$recordId}'
                        step='1'
                        min='0'
                        style='width:100%; padding:5px; box-sizing:border-box;'
                        placeholder='ค่าขนส่ง'>
                </td>
            ";

            // Save button
            $html .= "
                <td style='padding:8px; border:1px solid #ddd; text-align:center;'>
                    <button
                        type='button'
                        class='btn-save-km'
                        data-recordid='{$recordId}'
                        data-car='{$carReg}'
                        data-start='{$startKM}'
                        data-date='{$date}'
                        style='padding:5px 10px; background:#28a745; color:#fff; border:none; border-radius:3px; cursor:pointer;'>
                        Save
                    </button>
                </td>
            ";
        } else {
            // แสดงข้อมูลที่บันทึกแล้ว
            $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$endKM}</td>";
            
            // แสดง Fuel costs (ถ้ามี)
            $fuelDisplay = ($fuelCosts !== null && $fuelCosts > 0) 
                ? number_format($fuelCosts, 0) 
                : '-';
            $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$fuelDisplay}</td>";
            
            // แสดง Shipping costs (ถ้ามี)
            $shippingDisplay = ($shippingCosts !== null && $shippingCosts > 0) 
                ? number_format($shippingCosts, 0) 
                : '-';
            $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$shippingDisplay}</td>";
            
            $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:center;'>-</td>";
        }

        // ปุ่ม Detail (แสดงเฉพาะแถวที่มี RecordID)
        if ($recordId !== null) {
            $html .= "
                <td style='padding:8px; border:1px solid #ddd; text-align:center;'>
                    <button type='button'
                        class='btn-show-detail'
                        data-recordid='{$recordId}'
                        style='padding:5px 10px; cursor:pointer;'>▼</button>
                </td>
            </tr>";

            $html .= "
            <tr class='detail-row' id='detail-{$recordId}' style='display:none;'>
                <td colspan='7' style='padding:0; border:1px solid #ddd;'>
                    <div class='loading' style='padding:20px; text-align:center;'>Loading...</div>
                </td>
            </tr>";
        } else {
            $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:center;'>-</td></tr>";
        }

        // ✅ แสดงประวัติย้อนหลัง (อ่านอย่างเดียว)
        for ($i = 1; $i < count($rt); $i++) {
            $data = $rt[$i];
            $recordId = $data['RecordID'];
            $carReg   = $data['Car_registration'];
            $startKM  = $data['Trans_startDate'];
            $endKM    = $data['Trans_endDate'];
            $fuelCosts = $data['Fuel_costs'] ?? null;
            $shippingCosts = $data['Shipping_costs'] ?? null;

            $html .= "<tr class='main-row history-row' data-recordid='{$recordId}' data-refcarid='{$recordId}' style='background:#f9f9f9;'>";
            $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$carReg}</td>";
            $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$startKM}</td>";
            $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$endKM}</td>";
            
            // แสดง Fuel costs (ถ้ามี)
            $fuelDisplay = ($fuelCosts !== null && $fuelCosts > 0) 
                ? number_format($fuelCosts, 0) 
                : '-';
            $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$fuelDisplay}</td>";
            
            // แสดง Shipping costs (ถ้ามี)
            $shippingDisplay = ($shippingCosts !== null && $shippingCosts > 0) 
                ? number_format($shippingCosts, 0) 
                : '-';
            $html .= "<td style='padding:8px; border:1px solid #ddd;'>{$shippingDisplay}</td>";
            
            $html .= "<td style='padding:8px; border:1px solid #ddd; text-align:center;'>-</td>";

            // ปุ่ม Detail
            if ($recordId !== null) {
                $html .= "
                    <td style='padding:8px; border:1px solid #ddd; text-align:center;'>
                        <button type='button'
                            class='btn-show-detail'
                            data-recordid='{$recordId}'
                            style='padding:5px 10px; cursor:pointer;'>▼</button>
                    </td>
                </tr>";

                $html .= "
                <tr class='detail-row' id='detail-{$recordId}' style='display:none;'>
                    <td colspan='7' style='padding:0; border:1px solid #ddd;'>
                        <div class='loading' style='padding:20px; text-align:center;'>Loading...</div>
                    </td>
                </tr>";
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



function show_transport_detail($ierp, $crit, $winspeed,$himalai)
{
    $RecordID = $crit['RecordID'] ?? '';
    

    if ($RecordID === '') {
        echo json_encode([
            "success" => false,
            "message" => "RecordID is required"
        ]);
        exit;
    }

    /* ===============================
       🔹 ดึงเฉพาะข้อมูลที่บันทึกแล้ว
       จาก tblTransportstra_registration_detail
    =============================== */

    $sqlDetail = "
        SELECT 
            refCarID,
            Car_registration,
            Trans_startDate,
            Trans_endDate,
            invoice,
            item_id,
            qty,
            M3,
            Customer_ID,
            CustomerName,
            last_update,
            create_date
        FROM testdat.tblTransportstra_registration_detail
        WHERE refCarID = '$RecordID' 
          AND (act IS NULL OR act != 1)
        ORDER BY invoice, item_id
    ";

    $detailData = $himalai->query($sqlDetail)->fetchAll(PDO::FETCH_ASSOC);

    /* ===============================
       🔹 ส่งข้อมูลกลับ (เฉพาะข้อมูลที่มี)
    =============================== */
    echo json_encode([
        "success" => true,
        "detail_data" => $detailData,
        "readonly" => true  // บอกว่าเป็นโหมดดูอย่างเดียว
    ]);
    exit;
}

function savekm_transport($ierp, $crit, $winspeed,$himalai) {

    $recordID = $crit['recordID'];
    $startKM  = number_format((float)$crit['start_km'], 2, '.', '');
    $endKM    = number_format((float)$crit['end_km'], 2, '.', '');
    $fuel_costs   = $crit['fuel_costs'];
    $shipping_costs = $crit['shipping_costs'];

    try {


        $ierp->beginTransaction();

        $set = [];
        $set[] = "Trans_endDate = $endKM";
        $set[] = "last_update = NOW()";

        if ($fuel_costs !== '') {
            $set[] = "Fuel_costs = " . (int)$fuel_costs;
        }

        if ($shipping_costs !== '') {
            $set[] = "Shipping_costs = " . (int)$shipping_costs;
        }

        $setSql = implode(", ", $set);

        $sql1 = "
            UPDATE testdat.tblTransportstra_registration
            SET $setSql
            WHERE RecordID = $recordID
        ";

        $stmt1 = $himalai->prepare($sql1);
        $stmt1->execute();


        /* ===============================
           UPDATE TABLE 2 (detail)
        =============================== */
        $sql2 = "
            UPDATE testdat.tblTransportstra_registration_detail
            SET
                Trans_endDate = $endKM,
                last_update = NOW()
            WHERE refCarID =  $recordID
        ";

        $stmt2 = $himalai->prepare($sql2);
        $stmt2->execute();


        $ierp->commit();

        echo json_encode([
            "status" => "success"
        ]);
        exit;

    } catch (Exception $e) {

        $ierp->rollBack();

        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
        exit;
    }
}



function save_start_transport($ierp, $crit, $winspeed,$himalai)
{
    try {

        $carReg   = $crit['car_registration'];
        $startKM  = number_format((float)$crit['start_km'], 2, '.', '');
        $datetime = $crit['datetime'];
        $invoice_list = $crit['invoice_list'];

        if (!$invoice_list || count($invoice_list) === 0) {
            throw new Exception("invoice_list empty");
        }

        $himalai->beginTransaction();

        /* ---------------- INSERT MASTER ---------------- */
        $sqlInsert = "
            INSERT INTO testdat.tblTransportstra_registration
            (
                Car_registration,
                Trans_startDate,
                Driving_date,
                last_update
            )
            VALUES ('$carReg', $startKM, '$datetime', NOW())
        ";

        $himalai->exec($sqlInsert);

        /* ---------------- GET RecordID ---------------- */
        $recordID = $himalai->query("
            SELECT RecordID
            FROM testdat.tblTransportstra_registration
            ORDER BY RecordID DESC
            LIMIT 1
        ")->fetchColumn();

        if (!$recordID) {
            throw new Exception("cannot get RecordID");
        }

        /* ---------------- PREPARE INVOICE LIST ---------------- */
        $invoiceStr = "'" . implode("','", $invoice_list) . "'";

        /* ---------------- QUERY WINSPEED ---------------- */
        $sqlwin = "
            select
                Winspeed_PRD.dbo.soinvhd.invno as invoice_id,
                CASE 
                    WHEN LEFT(Winspeed_PRD.dbo.emcust.custcode, 1) = '2'
                        THEN '1' + RIGHT(Winspeed_PRD.dbo.emcust.custcode, LEN(Winspeed_PRD.dbo.emcust.custcode) - 1)
                    WHEN LEFT(Winspeed_PRD.dbo.emcust.custcode, 1) = '4'
                        THEN '3' + RIGHT(Winspeed_PRD.dbo.emcust.custcode, LEN(Winspeed_PRD.dbo.emcust.custcode) - 1)
                    WHEN LEFT(Winspeed_PRD.dbo.emcust.custcode, 1) = '6'
                        THEN '5' + RIGHT(Winspeed_PRD.dbo.emcust.custcode, LEN(Winspeed_PRD.dbo.emcust.custcode) - 1)
                    ELSE Winspeed_PRD.dbo.emcust.custcode
                END as Customer_ID,
                Winspeed_PRD.dbo.emcust.CustName as CustomerName,
                Winspeed_PRD.dbo.emgood.goodcode as item_id,
                sum(Winspeed_PRD.dbo.soinvdt.goodqty2) as ItemQty
            from Winspeed_PRD.dbo.soinvhd
            left join Winspeed_PRD.dbo.soinvdt
                on Winspeed_PRD.dbo.soinvhd.soinvid = Winspeed_PRD.dbo.soinvdt.soinvid
            left join Winspeed_PRD.dbo.emgood
                on Winspeed_PRD.dbo.soinvdt.goodid = Winspeed_PRD.dbo.emgood.goodid
            left join Winspeed_PRD.dbo.emcust
                on Winspeed_PRD.dbo.soinvhd.custid = Winspeed_PRD.dbo.emcust.custid
            where Winspeed_PRD.dbo.soinvhd.invno IN ($invoiceStr)
            group by
                Winspeed_PRD.dbo.soinvhd.invno,
                Winspeed_PRD.dbo.emgood.goodcode,
                Winspeed_PRD.dbo.emcust.CustName,
                CASE 
                    WHEN LEFT(Winspeed_PRD.dbo.emcust.custcode, 1) = '2'
                        THEN '1' + RIGHT(Winspeed_PRD.dbo.emcust.custcode, LEN(Winspeed_PRD.dbo.emcust.custcode) - 1)
                    WHEN LEFT(Winspeed_PRD.dbo.emcust.custcode, 1) = '4'
                        THEN '3' + RIGHT(Winspeed_PRD.dbo.emcust.custcode, LEN(Winspeed_PRD.dbo.emcust.custcode) - 1)
                    WHEN LEFT(Winspeed_PRD.dbo.emcust.custcode, 1) = '6'
                        THEN '5' + RIGHT(Winspeed_PRD.dbo.emcust.custcode, LEN(Winspeed_PRD.dbo.emcust.custcode) - 1)
                    ELSE Winspeed_PRD.dbo.emcust.custcode
                END
        ";

        $rows = $winspeed->query($sqlwin)->fetchAll(PDO::FETCH_ASSOC);

        /* ---------------- INSERT DETAIL ---------------- */
        foreach ($rows as $r) {

            $sqlDetail = "
                INSERT INTO testdat.tblTransportstra_registration_detail
                (
                    refCarID,
                    Car_registration,
                    Trans_startDate,
                    invoice,
                    Customer_ID,
                    CustomerName,
                    item_id,
                    qty
                )
                VALUES
                (
                    '$recordID',
                    '$carReg',
                     $startKM,
                    '{$r['invoice_id']}',
                    '{$r['Customer_ID']}',
                    '{$r['CustomerName']}',
                    '{$r['item_id']}',
                    {$r['ItemQty']}
                )
            ";

            $himalai->exec($sqlDetail);
        }
    /* ==================================================
           UPDATE M3 FROM iERP ITEM ATTRIBUTE
        ================================================== */
/* ==================================================
   GET M3 FROM SQL SERVER (iERP)
================================================== */
$sqlSrc = "
    select 
        A.IMA_ItemID,
        CAST(B.Attribute21_Value as DECIMAL(10,2)) as M3
    from iERP86_WDI.dbo.Item A
    inner join iERP86_WDI.dbo.ItemAttribute B
        on B.ItemAttr_IMA_RecordID = A.IMA_RecordID
    where left(A.IMA_ItemTypeCode,1) = '1'
    and left(A.IMA_ProdFam,3) = 'PSG'
    and B.Attribute17_Value is not null
";

$srcRows = $ierp->query($sqlSrc)->fetchAll(PDO::FETCH_ASSOC);


/* ==================================================
   UPDATE MYSQL TABLE
================================================== */
$stmt = $himalai->prepare("
    UPDATE testdat.tblTransportstra_registration_detail
    SET M3 = :m3
    WHERE refCarID = :recordID
    AND item_id = :item_id
");

foreach ($srcRows as $r) {
    $stmt->execute([
        ':m3' => $r['M3'],
        ':recordID' => $recordID,
        ':item_id' => $r['IMA_ItemID']
    ]);
}

        /* ==================================================
           UPDATE MAFlag (เพิ่มใหม่ตรงนี้)
        ================================================== */
        foreach ($invoice_list as $invoice) {

            $sqlUpd = "
                EXEC Winspeed_PRD.dbo.update_MAflag N'$invoice'
            ";

            $stmtUpd = $winspeed->prepare($sqlUpd);
            $successUpd = $stmtUpd->execute();

            if (!$successUpd) {
                throw new Exception("update MAFlag failed for invoice: " . $invoice);
            }
        }

        $himalai->commit();

        echo json_encode([
            "status" => "success",
            "RecordID" => $recordID
        ]);
    } catch (Exception $e) {

        $himalai->rollBack();

        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }

    exit;
}



function show_Report_transport($ierp, $crit, $winspeed, $himalai)
{
    $date = $crit['date'] ?? date('Y-m-d');
    $CarFilter = $crit['registration'] ?? '';

    /* ===============================
       STEP 1 : QUERY MYSQL
    ================================ */
    $where = [];
    $params = [];

    $where[] = "DATE(r.Driving_date) = :date";
    $params[':date'] = $date;

    if ($CarFilter !== '') {
        $where[] = "TRIM(r.Car_registration) = :reg";
        $params[':reg'] = $CarFilter;
    }

    $whereSql = "WHERE " . implode(' AND ', $where);

    $sql = "
        SELECT
            r.RecordID,
            r.Car_registration,
            r.Trans_startDate,
            r.Trans_endDate,
            DATE(r.Driving_date) AS Driving_date,
            d.invoice,
            d.item_id,
            d.qty,
            d.act,
            d.create_date AS detail_createDate
        FROM testdat.tblTransportstra_registration r
        LEFT JOIN testdat.tblTransportstra_registration_detail d
            ON r.RecordID = d.refCarID
        $whereSql
        ORDER BY
            r.RecordID DESC,
            d.create_date DESC
    ";

    $stmt = $himalai->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        header('Content-Type: application/json');
        echo json_encode([
            "table1" => [
                "table" => ["<table><tr><td>ไม่พบข้อมูล</td></tr></table>", "", "", ""],
                "tid"   => "#t1",
                "cid"   => "#c1",
                "tit"   => "รายงานการขนส่ง",
                "hi"    => -25,
                "wi"    => 5
            ],
            "rt" => [],
            "grouped" => []
        ]);
        exit;
    }

    /* ===============================
       STEP 2 : QUERY M3 FROM SQL SERVER
    ================================ */
    $itemIDs = array_unique(array_filter(array_column($rows, 'item_id')));

    $m3Map = [];

    if (!empty($itemIDs)) {

        $itemStr = "'" . implode("','", $itemIDs) . "'";

        $sqlAttr = "
            SELECT
                A.IMA_ItemID,
                CAST(B.Attribute21_Value AS DECIMAL(10,4)) AS M3_per_unit
            FROM iERP86_WDI.dbo.Item A
            INNER JOIN iERP86_WDI.dbo.ItemAttribute B
                ON B.ItemAttr_IMA_RecordID = A.IMA_RecordID
            WHERE A.IMA_ItemID IN ($itemStr)
            AND B.Attribute21_Value IS NOT NULL
        ";

        $attrRows = $ierp->query($sqlAttr)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($attrRows as $a) {
            $m3Map[$a['IMA_ItemID']] = $a['M3_per_unit'];
        }
    }

    /* ===============================
       STEP 3 : MERGE DATA
    ================================ */
    $rt = [];

    foreach ($rows as $r) {

        $itemID = $r['item_id'];
        $qty    = (float)$r['qty'];

        $M3_per_unit = isset($m3Map[$itemID]) ? (float)$m3Map[$itemID] : 0;
        $Total_M3    = $M3_per_unit * $qty;

        $r['M3_per_unit'] = $M3_per_unit;
        $r['Total_M3']    = $Total_M3;

        $rt[] = $r;
    }


    // ✅ จัดกลุ่มข้อมูลตาม RecordID และคำนวณ Total M3
    $grouped = [];
    foreach ($rt as $row) {
        $recordId = $row['RecordID'];
        if (!isset($grouped[$recordId])) {
            $grouped[$recordId] = [
                'main' => [
                    'RecordID' => $row['RecordID'],
                    'Car_registration' => $row['Car_registration'],
                    'Trans_startDate' => $row['Trans_startDate'],
                    'Trans_endDate' => $row['Trans_endDate'],
                    'Driving_date' => $row['Driving_date']
                ],
                'details' => [],
                'total_m3' => 0 // เพิ่มตัวแปรสำหรับรวม M3
            ];
        }
        
        // เพิ่ม detail ถ้ามี
        if (!empty($row['invoice']) || !empty($row['item_id'])) {
            $grouped[$recordId]['details'][] = [
                'invoice' => $row['invoice'],
                'item_id' => $row['item_id'],
                'qty' => $row['qty'],
                'M3_per_unit' => $row['M3_per_unit'],
                'Total_M3' => $row['Total_M3']
            ];
            
            // รวม Total M3
            $grouped[$recordId]['total_m3'] += floatval($row['Total_M3']);
        }
    }

    $tb = [
        ["th" => "ทะเบียนรถ", "cols" => "120"],
        ["th" => "เลขไมล์เริ่ม", "cols" => "100"],
        ["th" => "เลขไมล์สิ้นสุด", "cols" => "100"],
        ["th" => "วันที่ขับ", "cols" => "100"],
        ["th" => "Invoice", "cols" => "120"],
        ["th" => "รหัสสินค้า", "cols" => "120"],
        ["th" => "จำนวน", "cols" => "80"],
        ["th" => "M³/หน่วย", "cols" => "100"],
        ["th" => "รวม M³", "cols" => "100"],
    ];

    $dtid = $crit['tid'] ?? 1;

    // ✅ CSS สำหรับตกแต่งตาราง
    $tableStyle = "
        width:100%; 
        border-collapse:collapse; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    ";
    
    $thStyle = "
        background: linear-gradient(to bottom, #ffd700, #ffcc00);
        color: #333;
        font-weight: bold;
        padding: 12px 8px;
        border: 1px solid #ddd;
        text-align: center;
        font-size: 14px;
    ";
    
    $tdMainStyle = "
        vertical-align: middle;
        border: 1px solid #ddd;
        padding: 10px 8px;
        background-color: #f9f9f9;
        font-weight: 500;
    ";
    
    $tdDetailStyle = "
        border: 1px solid #ddd;
        padding: 8px;
        background-color: #fff;
    ";
    
    $tdTotalStyle = "
        border: 1px solid #ddd;
        padding: 10px 8px;
        background-color: #fff3cd;
        font-weight: bold;
        text-align: right;
        vertical-align: middle;
    ";

    // ✅ Build table with rowspan
    $html = "<table id='t{$dtid}' class='datatable' style='{$tableStyle}'>
        <thead><tr>";

    foreach ($tb as $column) {
        $html .= "<th style='{$thStyle}'>{$column['th']}</th>";
    }

    $html .= "</tr></thead><tbody>";

    if (!empty($grouped)) {
        foreach ($grouped as $recordId => $group) {
            $main = $group['main'];
            $details = $group['details'];
            $total_m3 = $group['total_m3'];
            $rowspan = max(1, count($details)); // อย่างน้อย 1 แถว

            // แถวแรกของแต่ละ RecordID
            $html .= "<tr data-recordid='{$recordId}'>";
            $html .= "<td rowspan='{$rowspan}' style='{$tdMainStyle} text-align:center;'>" 
                   . htmlspecialchars($main['Car_registration']) . "</td>";
            $html .= "<td rowspan='{$rowspan}' style='{$tdMainStyle} text-align:right;'>" 
                   . number_format($main['Trans_startDate'], 0) . "</td>";
            $html .= "<td rowspan='{$rowspan}' style='{$tdMainStyle} text-align:right;'>" 
                   . number_format($main['Trans_endDate'], 0) . "</td>";
            $html .= "<td rowspan='{$rowspan}' style='{$tdMainStyle} text-align:center;'>" 
                   . htmlspecialchars($main['Driving_date']) . "</td>";

            // ถ้ามี detail แสดง detail แรก
            if (!empty($details)) {
                $detail = $details[0];
                $html .= "<td style='{$tdDetailStyle}'>" . htmlspecialchars($detail['invoice'] ?? '-') . "</td>";
                $html .= "<td style='{$tdDetailStyle}'>" . htmlspecialchars($detail['item_id'] ?? '-') . "</td>";
                $html .= "<td style='{$tdDetailStyle} text-align:right;'>" 
                       . number_format($detail['qty'], 2) . "</td>";
                $html .= "<td style='{$tdDetailStyle} text-align:right;'>" 
                       . number_format($detail['M3_per_unit'], 4) . "</td>";
                
                // แสดง Total M3 รวมทั้งหมดแค่แถวแรก
                $html .= "<td rowspan='{$rowspan}' style='{$tdTotalStyle}'>" 
                       . number_format($total_m3, 4) . "</td>";
            } else {
                // ไม่มี detail
                $html .= "<td colspan='4' style='text-align:center; color:#999; {$tdDetailStyle}'>ไม่มีรายละเอียด</td>";
                $html .= "<td style='{$tdTotalStyle}'>0.0000</td>";
            }
            $html .= "</tr>";

            // แถวต่อไปของ detail (ถ้ามี) - ไม่แสดง Total M3 เพราะใช้ rowspan แล้ว
            for ($i = 1; $i < count($details); $i++) {
                $detail = $details[$i];
                $html .= "<tr data-recordid='{$recordId}'>";
                $html .= "<td style='{$tdDetailStyle}'>" . htmlspecialchars($detail['invoice'] ?? '-') . "</td>";
                $html .= "<td style='{$tdDetailStyle}'>" . htmlspecialchars($detail['item_id'] ?? '-') . "</td>";
                $html .= "<td style='{$tdDetailStyle} text-align:right;'>" 
                       . number_format($detail['qty'], 2) . "</td>";
                $html .= "<td style='{$tdDetailStyle} text-align:right;'>" 
                       . number_format($detail['M3_per_unit'], 4) . "</td>";
                // ไม่ต้องแสดง Total M3 เพราะใช้ rowspan แล้ว
                $html .= "</tr>";
            }
        }
    } else {
        $html .= "<tr><td colspan='9' style='text-align:center; padding:30px; color:#999; font-size:16px;'>
                  <i>ไม่พบข้อมูล</i></td></tr>";
    }

    $html .= "</tbody></table>";
    $cols = implode(",", array_column($tb, 'cols'));

    header('Content-Type: application/json');
    echo json_encode([
        "table1" => [
            "table" => [$html, $cols, "", ""],
            "tid"   => "#t{$dtid}",
            "cid"   => "#c{$dtid}",
            "tit"   => "รายงานการขนส่ง",
            "hi"    => -25,
            "wi"    => 5
        ],
        "rt" => $rt,
        "grouped" => $grouped
    ]);
    exit;
}

function show_Report_BOX_transport($ierp, $crit, $winspeed, $himalai)
{
    $date = $crit['date'] ?? date('Y-m-d');

    /* ================================
       STEP 1 : GET HEADER + DETAIL (MYSQL)
    ================================ */
    $sql = "
    SELECT
        r.RecordID,
        r.Car_registration,
        r.Trans_startDate,
        r.Trans_endDate,
        DATE_FORMAT(r.Driving_date, '%Y-%m-%d %H:%i') AS Driving_date,
        d.invoice,
        d.item_id,
        d.qty
    FROM testdat.tblTransportstra_registration r
    LEFT JOIN testdat.tblTransportstra_registration_detail d
        ON r.RecordID = d.refCarID
    WHERE DATE(r.Driving_date) = :date
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
                "tid"   => "#grid1",
                "cid"   => "#c1",
                "tit"   => "รายงานการขนส่ง",
                "hi"    => -25,
                "wi"    => 5
            ],
            "rt" => [],
            "record_count" => 0,
            "car_count" => 0,
            "grid_cells" => 64
        ]);
        exit;
    }

    /* ================================
       STEP 2 : GET M3 FROM SQL SERVER
    ================================ */
    $itemIDs = array_unique(array_filter(array_column($rows, 'item_id')));
    
    $m3Map = [];
    
    if (!empty($itemIDs)) {
        $itemStr = "'" . implode("','", $itemIDs) . "'";

        $sqlAttr = "
        SELECT 
            A.IMA_ItemID,
            CAST(B.Attribute21_Value AS DECIMAL(10,4)) AS M3
        FROM iERP86_WDI.dbo.Item A
        INNER JOIN iERP86_WDI.dbo.ItemAttribute B
            ON B.ItemAttr_IMA_RecordID = A.IMA_RecordID
        WHERE A.IMA_ItemID IN ($itemStr)
        AND B.Attribute21_Value IS NOT NULL
        ";

        $attrRows = $ierp->query($sqlAttr)->fetchAll(PDO::FETCH_ASSOC);

        /* map item → M3 */
        foreach ($attrRows as $a) {
            $m3Map[$a['IMA_ItemID']] = $a['M3'];
        }
    }

    /* ================================
       STEP 3 : GROUP RESULT
    ================================ */
    $result = [];

    foreach ($rows as $r) {

        $rid = $r['RecordID'];

        if (!isset($result[$rid])) {
            $result[$rid] = [
                "RecordID" => $r['RecordID'],
                "Car_registration" => $r['Car_registration'],
                "Trans_startDate" => $r['Trans_startDate'],
                "Trans_endDate" => $r['Trans_endDate'],
                "Driving_date" => $r['Driving_date'],
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

        if (isset($m3Map[$r['item_id']])) {
            $result[$rid]['total_M3'] += ($m3Map[$r['item_id']] * $qty);
        }
    }

    /* ================================
       FINAL FORMAT
    ================================ */
    $rt = [];

    foreach ($result as $r) {
        $r['invoice_count'] = count($r['invoice_count']);
        $r['item_count'] = count($r['item_count']);
        $rt[] = $r;
    }


    $dtid = $crit['tid'] ?? 1;

    // ✅ จัดกลุ่มข้อมูลตาม Car_registration
    $groupedByCar = [];
    foreach ($rt as $row) {
        $carReg = $row['Car_registration'];
        if (!isset($groupedByCar[$carReg])) {
            $groupedByCar[$carReg] = [];
        }
        $groupedByCar[$carReg][] = [
            'recordID' => $row['RecordID'],
            'car_registration' => $row['Car_registration'],
            'driving_date' => $row['Driving_date'],
            'trans_start' => $row['Trans_startDate'],
            'trans_end' => $row['Trans_endDate'],
            'invoice_count' => $row['invoice_count'],
            'item_count' => $row['item_count'],
            'total_qty' => $row['total_qty'],
            'total_M3' => $row['total_M3']
        ];
    }

    // ✅ เรียงข้อมูลภายในแต่ละทะเบียน: เก่าสุด (RecordID น้อย) → ใหม่สุด (RecordID มาก)
    foreach ($groupedByCar as $carReg => &$records) {
        usort($records, function($a, $b) {
            return intval($a['recordID']) - intval($b['recordID']); // เก่า → ใหม่
        });
    }
    unset($records); // ลบ reference

    // ✅ สร้าง Grid Array 8x8 (64 ช่องคงที่)
    $gridCells = [];
    $cellIndex = 0;
    
    foreach ($groupedByCar as $carReg => $records) {
        // แต่ละทะเบียนได้ 1 แถว (8 ช่อง)
        $colCount = 0;
        
        foreach ($records as $record) {
            if ($cellIndex >= 64) break 2; // หยุดถ้าเกิน 64 ช่อง
            if ($colCount >= 8) break; // หยุดถ้าครบ 8 คอลัมน์
            
            $gridCells[$cellIndex] = $record;
            $cellIndex++;
            $colCount++;
        }
        
        // เติมช่องว่างที่เหลือในแถว
        while ($colCount < 8 && $cellIndex < 64) {
            $gridCells[$cellIndex] = null;
            $cellIndex++;
            $colCount++;
        }
    }
    
    // เติมช่องว่างให้ครบ 64 ช่อง
    while (count($gridCells) < 64) {
        $gridCells[] = null;
    }

    // ✅ สร้าง HTML โดยตรง (64 ช่อง) - ลบ <colgroup> ออก
    $html = '<div style="padding:20px;background-color:#f0f0f0;">
    <style>
        #grid' . $dtid . ' colgroup { display: none !important; }
    </style>
    
    <div id="grid' . $dtid . '" style="display:grid;grid-template-columns:repeat(8,1fr);grid-template-rows:repeat(8,1fr);gap:8px;background-color:#f5f5f5;">';
    
    // สร้าง 64 ช่อง
    for ($i = 0; $i < 64; $i++) {
        $cell = $gridCells[$i];
        
        if ($cell === null) {
            // ช่องว่าง
            $html .= '<div style="border:2px dashed #ccc;border-radius:6px;background-color:#fafafa;min-height:120px;"></div>';
        } else {
            // ช่องมีข้อมูล
            $car = htmlspecialchars($cell['car_registration'] ?? '-');
            $rid = htmlspecialchars($cell['recordID'] ?? '');
            $date_val = htmlspecialchars($cell['driving_date'] ?? '-');
            $start = htmlspecialchars($cell['trans_start'] ?? '-');
            $end = htmlspecialchars($cell['trans_end'] ?? '-');
            $inv = number_format(intval($cell['invoice_count'] ?? 0), 0);
            $item = number_format(intval($cell['item_count'] ?? 0), 0);
            $qty = number_format($cell['total_qty'] ?? 0, 2);
            $raw_m3 = floatval($cell['total_M3'] ?? 0);
            $m3 = number_format($raw_m3, 4);

            // ✅ คำนวณ %
            $maxM3 = 7.9;
            $percent = ($maxM3 > 0) ? ($raw_m3 / $maxM3) * 100 : 0;
            $percent = min(100, round($percent, 1));

            // ✅ กำหนดสีตาม %
            if ($percent <= 40) {
                $m3Color = '#000'; // ส้ม
                $bgColor = '#ffac33';
            } elseif ($percent <= 70) {
                $m3Color = '#000'; // เหลือง
                $bgColor = '#ffdd56';
            } else {
                $m3Color = '#000'; // เขียว
                $bgColor = '#00f969';
            }

            $html .= '
<div style="
    border:2px solid #ddd;
    border-radius:8px;
    padding:10px;
    background:linear-gradient(to bottom,#ffffff,#f9f9f9);
    box-shadow:0 2px 4px rgba(0,0,0,0.1);
    min-height:130px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    font-size:11px;
"
data-recordid="'.$rid.'" data-car="'.$car.'" data-index="'.$i.'"
>

    <!-- HEADER -->
    <div style="
        background:linear-gradient(to right,#ffd700,#ffcc00);
        color:#333;
        font-weight:bold;
        padding:6px;
        border-radius:6px;
        margin-bottom:6px;
        text-align:center;
        font-size:11px;
    ">
        '.$car.'
    </div>

    <!-- BODY -->
    <div style="line-height:1.5;  background-color:'.$bgColor.'; border-radius:6px;
    padding:6px;">

        <!-- DATE -->
        <div style="margin-bottom:4px;">
            <span style="font-size:11px;color:#333;font-weight:500;">📅
                '.$date_val.'
            </span>
        </div>

        <!-- KM RANGE -->
        <div style="
            margin-bottom:6px;
            background:#eef3ff;
            border-radius:4px;
            padding:4px;
            text-align:center;
        ">
            <span style="font-size:9px;color:#5c6bc0;font-weight:600;">
                🛞 '.$start.' → '.$end.' km
            </span>
        </div>

        <!-- INVOICE + ITEM -->
        <div style="display:flex;gap:8px;margin-bottom:6px;">
            <div style="flex:1;text-align:center;">
                <div style="font-size:9px;color:#777;">📄 Inv</div>
                <div style="font-size:12px;font-weight:600;color:#333;">
                    '.$inv.'
                </div>
            </div>
            <div style="flex:1;text-align:center;">
                <div style="font-size:9px;color:#777;">📦 Item</div>
                <div style="font-size:12px;font-weight:600;color:#333;">
                    '.$item.'
                </div>
            </div>
        </div>

        <!-- QTY + M3 -->
        <div style="display:flex;gap:8px;">
            <div style="flex:1;text-align:center;">
                <div style="font-size:9px;color:#777;">QTY</div>
                <div style="font-size:12px;font-weight:600;color:#333;">
                    '.$qty.'
                </div>
            </div>

            <div style="
                flex:1;
                border-radius:6px;
                padding:4px;
                text-align:center;
            ">
                <div style="font-size:9px;color:#555;">M³</div>
                <div style="font-size:13px;color:'.$m3Color.';font-weight:bold;">
                    '.$m3.'
                </div>
                <div style="font-size:9px;color:#666;">
                    '.$percent.'%
                </div>
            </div>
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
            "tid"   => "#grid{$dtid}",
            "cid"   => "#c{$dtid}",
            "tit"   => "รายงานการขนส่ง",
            "hi"    => -25,
            "wi"    => 5
        ],
        "rt" => $rt,
        "record_count" => count($rt),
        "car_count" => count($groupedByCar),
        "grid_cells" => 64
    ]);
    exit;
}