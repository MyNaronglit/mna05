<?php

session_start();
require_once './kqiq/myclass.php';
$cri = new baseClass();
$err = $cri->check_auth();
// if ($err != '')
// {
//     echo $err;
//     exit();
// }
$stpapp = 'transport01';
$date = date("Y-m-d");
$username = $_SESSION['pusername'];

require_once './kqiq/mydata_pdo.php';
$db = new MyPdo();
$himal = 0;
$himalai = $db->connectb($himal);
$lib = 5;
$librarix = $db->connectb($lib);

$sql = "SELECT AllowID,b_Allow `AllowStatus`,AllowDetails1 `AllowDetail`
    FROM webservice.tblSTP_ManageUser
    WHERE UserName IN ('{$username}') AND PathWeb IN ('transport01','all')";
$allow = $librarix->query($sql)->fetchAll(PDO::FETCH_ASSOC);
con_RowToCol($allow, 'AllowID');
function con_RowToCol(&$arr, $key)
{
    $t = [];
    foreach ($arr as $val) {
        $t += [$val[$key] => $val];
    }
    $arr = $t;
} {
    echo '
	<!DOCTYPE HTML>
        <html>
        <head>
        <title>Transport Data</title>

		<meta http-equiv="content-type" content="text/html; charset=UTF-8" >
		<link rel="stylesheet" href="./kdtt/jquery-ui-1.12.1.min.css">

		<link rel="stylesheet" href="./kdtt/base-2.0-min.css">
		<link rel="stylesheet" href="./kdtt/jquery.multiple_select-min.css">
		<link rel="stylesheet" href="./kdtt/screen-min.css">
		<link rel="stylesheet" href="./kdtt/flipclock-min.css">

		<link rel="stylesheet" href="mystyle-min.css" type="text/css" media="screen"/>
        <link rel="stylesheet" href="mystyle_n-min.css" media="screen"/>
		<link rel="stylesheet" href="./kdtt/stpstd-min.css">

		<style>
    ';
} { //
    $list_tab = ["_t1", "_t2", "_t3", "_t4", "_t5", "_manual", "mu"];
    $s_tab = $s_crit = $s_cX10 = uniqid("x");
    foreach ($list_tab as $key => $value) {
        $s_tab .= ",#tab{$value}";
        $s_crit .= ",#crit{$value}";
        $s_cX10 .= ",#c{$value}10";
    }
  echo "
    {$s_tab}{
        display: grid;
        grid-area: tab;
        grid-template-columns: 100% ; /* ซ้าย 70% ขวา 30% */
        grid-template-rows: min-content 1fr;
        gap: 10px;
        grid-template-areas:
            \"crit1 crit1\"
            \"c110 c210\";
    }

    {$s_crit}{ 
        grid-area: crit1; 
    }

    {$s_cX10}{ 
        grid-area: c110; /* ซ้าย */
    }

    {$s_cX20}{ 
        grid-area: c210; /* ขวา */
        display: none;
    }
";

}
echo "
#tab_t3{
    width: 95%;
    margin: 0 auto;  
}
   .form-box {
    background: #ffffff;
    color: #000000;
    border: 1px solid #ccc;
    padding: 15px;
}
    .form-row {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    .form-row label {
        width: 120px;
        font-weight: bold;
        color: #333;
    }
    .form-row input[type='text'],
    .form-row input[type='number'] {
   background: #fff;
    color: #000;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
";


echo '</style></head><body>';
echo '<div class="page">';
echo '<div class="menu">';
echo $_SESSION['menu'];
echo '</div>';

echo '<div id="tabs">';
echo '<div class="tablist"><ul>';
echo '<li><a href="#tab_t1">Transport</a></li>';
echo '<li><a href="#tab_t2">Report Transport</a></li>';
echo '<li><a href="#tab_t3">Report BOX Transport</a></li>';
if ($allow['TRASPORT02']['AllowStatus'])
echo '<li><a href="#tab_t5">Report Sum Transport</a></li>';

if ($allow['TRASPORT01']['AllowStatus'])
echo '<li><a href="#tab_t4">Add license</a></li>';
if ($allow['A01']['AllowStatus'])
    echo '<li><a href="#tabmu" >ManageUser</a></li>';
echo '</ul></div>';
//TaskEdit

$sql="SELECT DISTINCT car_regist FROM psgdata.tblTransport_regist ORDER BY car_regist";
$rt = $himalai->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$options = "<option value=''>-- เลือกทะเบียนรถ --</option>";

foreach ($rt as $row) {
    $reg = htmlspecialchars($row['car_regist']);
    $options .= "<option value='{$reg}'>{$reg}</option>";
}

$tid = '_t1';

/* default date + time */
$today = date('Y-m-d');
$curHour = date('H');

/* ปัดนาทีเป็น step 5 */
$curMin = date('i');
$curMin = round($curMin / 5) * 5;
if ($curMin == 60) {
    $curMin = 55;
}
$curMin = str_pad($curMin, 2, '0', STR_PAD_LEFT);

/* hour options */
$hourOpt = "";
for ($h = 0; $h < 24; $h++) {
    $hh = str_pad($h, 2, '0', STR_PAD_LEFT);
    $selected = ($hh == $curHour) ? "selected" : "";
    $hourOpt .= "<option value='$hh' $selected>$hh</option>";
}

/* minute options */
$minOpt = "";
for ($m = 0; $m < 60; $m += 5) {
    $mm = str_pad($m, 2, '0', STR_PAD_LEFT);
    $selected = ($mm == $curMin) ? "selected" : "";
    $minOpt .= "<option value='$mm' $selected>$mm</option>";
}

echo "
<div id='tab{$tid}' tid='{$tid}' >
    <div id='crit{$tid}'>
        <fieldset>
        <label>Start registration.</label>
        <select id='Car_reg_INSERT{$tid}'>
        {$options}
        </select>

    <label>Start KM</label>
    <input type='text' id='StartKM{$tid}'  class='integer-format input-end-km'>


        <label>Date & Time :</label>
            <input type='date' id='date_insert{$tid}' value='{$today}'>

            <select id='hour{$tid}'>
                {$hourOpt}
            </select>
            :
            <select id='minute{$tid}'>
                {$minOpt}
            </select>


        <label>Invoice ID :</label>
        <input type='text' id='invoice_search{$tid}' 
               placeholder='ค้นหา invoice...' 
               style='width:200px;margin-bottom:5px;'>
        <input type='button' id='save_start{$tid}' value=' Save '/>
        <div id='selected_invoice_preview'></div>
        <div style='display:flex; gap:10px;'>
    <div id='invoice_t1'
         style='border:1px solid #ccc;height:250px;overflow:auto;padding:5px;width:50%;'>
    </div>

    <div id='invoice02_t1'
         style='border:1px solid #ccc;height:250px;overflow:auto;padding:5px;width:50%;background:#f8fafc;'>
    </div>
</div>

        </fieldset>

        <fieldset>
        <label>Get Car</label>
        <select id='registration{$tid}'>
        {$options}
        </select>

        <input type='button' id='get{$tid}' value=' Get '>

        <label>Start :</label>
        <input type='date' id='date{$tid}' value='{$date}'/>
        </fieldset>
    </div>

    <div id='c{$tid}'></div>
    <div id='c2{$tid}'></div>
    <div id='result-area' class='mt-4'></div>
</div>
";



    $tid = '_t2';
    $date_d3 = date("Y-m-d");
    echo "
    <div id='tab{$tid}' tid='{$tid}'>
        <div id='crit{$tid}'>
            <fieldset>
            <label for='registration{$tid}'> Invoice. </label>
            <input type='text' id='invoice{$tid}' 
               placeholder='ค้นหา invoice...' 
               style='width:200px;margin-bottom:5px;'>
                <label for='registration{$tid}'> Car registration. </label>
                <select id='registration{$tid}' tida='{$tid_a}'>
                {$options}
                </select>
                <label for='date{$tid}'>Date : </label>
                <input type='date' id='date{$tid}'  value='{$date}'/>
                <input type='button' id='get{$tid}' value=' Get '/>
            </fieldset>
           </div>
        <div id='c{$tid}'></div>

    </div>
";


if ($allow['TRASPORT02']['AllowStatus']) {
    $tid = '_t5';
    $date_d3 = date("Y-m-d");
    echo "
    <div id='tab{$tid}' tid='{$tid}'>
        <div id='crit{$tid}'>
            <fieldset>
            <label for='registration{$tid}'> Invoice. </label>
            <input type='text' id='invoice{$tid}' 
               placeholder='ค้นหา invoice...' 
               style='width:200px;margin-bottom:5px;'>
                <label for='registration{$tid}'> Car registration. </label>
                <select id='registration{$tid}' tida='{$tid_a}'>
                {$options}
                </select>
                <label for='date{$tid}'>Date : </label>
                <input type='date' id='date{$tid}'  value='{$date}'/>
                <input type='button' id='get{$tid}' value=' Get '/>
            </fieldset>
           </div>
        <div id='c{$tid}'></div>

    </div>
";
}
$tid = '_t3';
$date_d3 = date("Y-m-d");
echo "    
    <div id='tab{$tid}' tid='{$tid}'>
        <div id='crit{$tid}'>
            <fieldset>
                <label for='date{$tid}'>Date : </label>
                <input type='date' id='date{$tid}'  value='{$date}'/>
                <input type='button' id='get{$tid}' value=' Get '/>
            </fieldset>
           </div>
        <div id='c{$tid}'></div>

    </div>
";
if ($allow['TRASPORT01']['AllowStatus']) {
$tid = '_t4';
$date_d3 = date("Y-m-d");
echo "    
    <div id='tab{$tid}' tid='{$tid}'>
        <div id='crit{$tid}'>
            <fieldset>
                <div class='form-row'>
                    <label for='plate{$tid}'>ป้ายทะเบียน :</label>
                    <input type='text' id='plate{$tid}' placeholder='กรอกป้ายทะเบียน' />
                </div>
                
                <div class='form-row'>
                    <label for='driver{$tid}'>คนขับ :</label>
                    <input type='text' id='driver{$tid}' placeholder='กรอกชื่อคนขับ' />
                </div>
                <div class='form-row'>
                    <label for='car_atten{$tid}'>คนติดรถ :</label>
                    <input type='text' id='car_atten{$tid}' placeholder='กรอกชื่อคนติดรถ' />
                </div>
                <input type='button' id='save{$tid}' value=' Save '/>
            </fieldset>
        </div>

        <div id='c{$tid}'></div>

    </div>
";
}

if ($allow['A01']['AllowStatus']){ //ManageUser
    echo "
    <div id='tabmu' >
    </div> <!-- #tabmu -->
    <input type='hidden' id='stppath' value='{$stpapp}'/>";
}

echo "<input type=\"hidden\" id=\"stpapp\" value=\"{$stpapp}\"/>";
echo '<input type="hidden" id="timeout" value="' . $_SESSION['timeout'] . '">';
echo '</div> <!-- #tabs -->';
echo '</div> <!-- .page -->'; { //Script
    echo '<script src="./kque/jquery-3.3.1.min.js"></script>';
    echo '<script src="./kque/jquery-ui-1.12.1.min.js"></script>';
    echo '<script src="./kque/jquery.multiple_select-min.js"></script>';
    echo '<script src="./kque/jquery.fixheadertable_r-min.js"></script>';
    echo '<script src="./kque/jquery.fileDownload-min.js"></script>';
    echo '<script src="./kque/upclick-min.js"></script>';
    echo '<script src="./kque/flipclock.min.js"></script>';
    // echo '<script src="./kque/jquery.idletimer-min.js"></script>';
    echo '<script src="myajax-min.js"></script>';
    echo '<script src="./kcri/stpstd-min.js?filemtime=' . filemtime('./kcri/stpstd-min.js') . '"> </script>';
}
echo "<script src=\"./kcri/{$stpapp}a-min.js?filemtime=" . filemtime("./kcri/{$stpapp}a-min.js") . "\"> </script>";
if ($allow['A01']['AllowStatus'])
    echo '<script src="./kcri/stpmua-min.js"> </script>';
echo '</body>';
echo '</html>';
