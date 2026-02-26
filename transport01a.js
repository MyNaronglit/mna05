$(document).ready(function () {
    // $(document).bind("idle.idleTimer", function () {
    //     window.location.href = "../ploxouta.php";
    // });
    // $.idleTimer(parseInt($("#timeout").val()));
  
    $("#tabs").tabs();
    prg = "transport01x.php";
  
    try { bind_transport("_t1"); } catch (error) { }
    try { bind_Report_transport("_t2"); } catch (error) { }
    try { bind_Report_BOX_transport("_t3"); } catch (error) { }


  });
  
  
//############## TaskConfirm ###########################################################################################33

function bind_Report_transport(tid) {
    $("#get" + tid).on("click", function () {
        var date = $("#date" + tid).val();
        var registration = $("#registration" + tid).val();
        
        // ตรวจสอบว่ามีการเลือกวันที่หรือไม่
        if (!date) {
            alert('กรุณาเลือกวันที่');
            return;
        }
        
        show_Report_transport(tid, date, registration);
    });
}

function show_Report_transport(tid, date, registration) {
    var crit = { tid: tid, date: date, registration: registration };

    save_SaveAndAlert("show_Report_transport", crit, function (response) {
        // แสดงตารางที่ c{tid}00
        if (response && response.table1) {
            create_fix_tab(
                response.table1.cid,
                response.table1.table,
                response.table1.tid,
                response.table1.tit,
                response.table1.hi,
                response.table1.wi
            );
        } else {
            console.error('Invalid response format:', response);
        }
    });
}


//#########################################################################################################33

function bind_Report_BOX_transport(tid) {
    $("#get" + tid).on("click", function () {
        var date = $("#date" + tid).val();
        
        // ตรวจสอบว่ามีการเลือกวันที่หรือไม่
        if (!date) {
            alert('กรุณาเลือกวันที่');
            return;
        }
        
        show_Report_BOX_transport(tid, date);
    });
}

function show_Report_BOX_transport(tid, date) {
    var crit = { tid: tid, date: date };

    save_SaveAndAlert("show_Report_BOX_transport", crit, function (response) {
        if (response && response.table1) {
            create_fix_tab(
                response.table1.cid,
                response.table1.table,
                response.table1.tid,
                response.table1.tit,
                response.table1.hi,
                response.table1.wi
            );
            
            // ✅ ลบ <colgroup> ที่ถูกแทรกเข้ามา
            setTimeout(function () {
                var grid = document.querySelector('#grid' + tid);
                if (grid) {
                    var colgroups = grid.querySelectorAll('colgroup');
                    colgroups.forEach(function (cg) { cg.remove(); });
                }
            }, 100);
        } else {
            console.error('Invalid response format:', response);
        }
    });

}


  // ###### TaskEdit #####################################################################################3
function bind_transport(tid) {
    // กดปุ่ม Get เพื่อแสดงตาราง
    $(document).on("click", "#get" + tid, function () {
        var registration = $("#registration" + tid).val();
        var date = $("#date" + tid).val();

        if (registration === "") {
            alert("กรุณากรอก ทะเบียน ก่อน!");
            return;
        }

        var tid_a = $(this).attr("tida");
        show_transport(tid, tid_a, registration, date);
    });


    $(document).on('change', '#Car_reg_INSERT_t1', function () {

        var carReg = $(this).val();

        selectedInvoices_now = {};

        loadInvoiceList(carReg, '');

        loadLastKM(carReg);
    });

    function loadLastKM(carReg) {

        var crit = { carReg: carReg };

        save_SaveAndAlert("get_last_km", crit,
            function (response) {
                if (response.data && response.data.Trans_endDate) {
                    $('#StartKM_t1').val(response.data.Trans_endDate);
                } else {
                    $('#StartKM_t1').val('');
                }
            }
        );
    }


    // กดปุ่ม Save KM
    $(document).on("click", ".btn-save-km", function () {

        var recordID = $(this).data("recordid");
        var carReg   = $(this).data("car");
        var tid  = '_t1';
        var date = $("#date" + tid).val();
        var tid_a = $("#get" + tid).attr("tida");
        var startKM = $("#start_km_" + recordID).val();
        var endKM   = $("#end_km_" + recordID).val();
        var fuelCosts = $("#fuel_costs_" + recordID).val();
        var shippingCosts = $("#shipping_costs_" + recordID).val();

        // fallback start KM จาก text
        if (!startKM) {
            startKM = $("tr[data-recordid='" + recordID + "']").find("td:eq(1)").text().trim();
        }

        if (endKM === "") {
            alert("กรุณากรอก End KM!");
            return;
        }

        startKM = parseFloat(startKM || 0);
        endKM   = parseFloat(endKM);



        if (endKM <= startKM) {
            alert("End KM ต้องมากกว่า Start KM!");
            return;
        }

        // fix format .00
        endKM = endKM.toFixed(2);
        
        var crit = {
            recordID: recordID,
            car_registration: carReg,
            start_km: startKM,
            end_km: endKM,
            fuel_costs: fuelCosts,
            shipping_costs: shippingCosts,
            date: date
        };

        save_SaveAndAlert("savekm_transport", crit,
            function (response) {

                if (response.status === "success") {
                    alert("บันทึกข้อมูลเรียบร้อย");

                    loadLastKM(carReg);
                    show_transport(tid, tid_a, carReg, date);
                } else {
                    alert("บันทึกไม่สำเร็จ");
                    
                }
            }
        );
    });

    // กดปุ่ม RecordID เพื่อแสดงรายละเอียด
    $(document).on("click", ".btn-show-detail", function () {
        var recordID = $(this).data("recordid");
        var detailRow = $("#detail-" + recordID);
        
        if (detailRow.is(":visible")) {
            detailRow.hide();
            return;
        }

        $(".detail-row").hide();
        detailRow.show();
        load_detail_data(tid, recordID);
    });

}

function show_transport(tid, tid_a, registration, date) {
    var crit = { 
        dtid: tid, 
        allow: tid_a,
        registration: registration,
        date: date
    };
    
    save_SaveAndAlert("show_transport", crit, function (response) {
        create_fix_tab(
            response.table1.cid,
            response.table1.table,
            response.table1.tid,
            response.table1.tit,
            response.table1.hi,
            response.table1.wi
        );
        
        // แสดงข้อความแจ้งเตือน (ถ้ามี)
        if (response.message) {
            console.log(response.message);
        }
    });
}

var selectedInvoices = {};
var existingInvoices = {};
var deletedInvoices  = {};

/* ===============================
   🔹 ฟังก์ชันดึงข้อมูล Detail
   จาก tblTransportstra_registration_detail
=============================== */
function load_detail_data(tid, recordID) {

    var crit = { 
        RecordID: recordID
    };

    save_SaveAndAlert("show_transport_detail", crit, function (response) {
        var detailRow = $("#detail-" + recordID);

        if (!response.detail || response.detail.length === 0) {
            detailRow.find("td").html("<div style='padding:20px; text-align:center; color:#999;'>ไม่มีข้อมูล Invoice</div>");
            return;
        }

        var detailData = response.detail;

        // Group by invoice
        var grouped = {};
        detailData.forEach(function (item) {
            if (!grouped[item.invoice]) {
                grouped[item.invoice] = {
                    invoice: item.invoice,
                    Customer_ID: item.Customer_ID,
                    CustomerName: item.CustomerName,
                    create_date: item.create_date
                };
            }
        });

        var html = "<div style='padding:15px; font-family:Arial; background:#fff; border-radius:8px;'>";
        html += "<div style='margin-bottom:10px; font-size:16px; font-weight:bold; color:#1e40af;'>รายการ Invoice</div>";
        html += "<div style='max-height:350px; overflow-y:auto; border:1px solid #ddd; border-radius:4px;'>";
        html += "<table style='width:100%; border-collapse:collapse;'>";
        html += "<thead style='background:#1e3a8a; color:#fff; position:sticky; top:0;'>";
        html += "<tr>";
        html += "<th style='padding:10px; border:1px solid #ddd; text-align:center;'>ลำดับ</th>";
        html += "<th style='padding:10px; border:1px solid #ddd;'>Invoice</th>";
        html += "<th style='padding:10px; border:1px solid #ddd;'>Customer Name</th>";
        html += "<th style='padding:10px; border:1px solid #ddd; text-align:center;'>วันที่สร้าง</th>";
        html += "</tr>";
        html += "</thead>";
        html += "<tbody>";

        var rowNum = 1;

        for (var key in grouped) {
            if (grouped.hasOwnProperty(key)) {
                var group = grouped[key];

                var createDate = group.create_date
                    ? new Date(group.create_date).toLocaleString('th-TH', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })
                    : '-';

                html += "<tr style='background:#f8f9fa;'>";
                html += "<td style='padding:10px; border:1px solid #ddd; text-align:center; font-weight:bold; color:#2196f3;'>";
                html += rowNum;
                html += "</td>";
                html += "<td style='padding:10px; border:1px solid #ddd;'>";
                html += "<div style='font-weight:bold; color:#333;'>" + group.invoice + "</div>";
                html += "<div style='font-size:11px; color:#999; margin-top:2px;'>ID: " + group.Customer_ID + "</div>";
                html += "</td>";
                html += "<td style='padding:10px; border:1px solid #ddd;'>";
                html += group.CustomerName;
                html += "</td>";
                html += "<td style='padding:10px; border:1px solid #ddd; text-align:center; font-size:12px; color:#666;'>";
                html += createDate;
                html += "</td>";
                html += "</tr>";

                rowNum++;
            }
        }

        html += "</tbody>";
        html += "</table>";
        html += "</div>";

        html += "<div style='margin-top:15px; padding:10px; background:#e3f2fd; border-radius:4px; font-size:13px; color:#1976d2;'>";
        html += "ℹ️ แสดงข้อมูล " + Object.keys(grouped).length + " Invoice (" + detailData.length + " รายการ)";
        html += "</div>";
        html += "</div>";

        detailRow.find("td").html(html);
    });
}

var selectedInvoices_now = {};
var selectedInvoiceInfo = {};
$(document).ready(function(){

 
    // search invoice
    $(document).on('keyup', '#invoice_search_t1', function(){
        var keyword = $(this).val();
        var reg = $('#Car_reg_INSERT_t1').val();
        loadInvoiceList(reg, keyword);
    });

$(document).on("change", "input[name='invoice_chk']", function () {
    var inv = $(this).val();
    var cust = $(this).data("cust");

    if (this.checked) {
        selectedInvoices_now[inv] = true;
        selectedInvoiceInfo[inv] = cust;
    } else {
        delete selectedInvoices_now[inv];
        delete selectedInvoiceInfo[inv];
    }

    renderSelectedPreview();
});

    function renderSelectedPreview() {
    var html = "";
    var i = 1;

    for (var inv in selectedInvoices_now) {
        if (selectedInvoices_now.hasOwnProperty(inv)) {
            html += "<div style='font-weight:bold;color:#2563eb'>";
            html += i + ". " + inv + " - " + (selectedInvoiceInfo[inv] || "");
            html += "</div>";
            i++;
        }
    }

    $("#selected_invoice_preview").html(html);
}

$(document).on("click", "#save_start_t1", function () {

    var tid = "_t1";
    var tid_a = $("#get" + tid).attr("tida");
    var carReg   = $("#Car_reg_INSERT" + tid).val();
    var startKM  = $("#StartKM" + tid).val();
    var date     = $("#date_insert" + tid).val();
    var hour     = $("#hour" + tid).val();
    var minute   = $("#minute" + tid).val();

    var datetime = date + " " + hour + ":" + minute + ":00";

    /* ดึง invoice ที่ถูกเลือก */
    var invoices = [];
    $("#invoice" + tid + " input[type=checkbox]:checked").each(function () {
        invoices.push($(this).val());
    });

    if (!carReg) {
        alert("กรุณาเลือกทะเบียนรถ");
        return;
    }

    if (!date) {
        alert("กรุณาเลือกวันที่");
        return;
    }

    if (invoices.length === 0) {
        alert("กรุณาเลือก invoice อย่างน้อย 1 รายการ");
        return;
    }

    var crit = {
        car_registration: carReg,
        start_km: startKM,
        datetime: datetime,
        invoice_list: invoices
    };

    save_SaveAndAlert("save_start_transport", crit,
        function (response) {

            if (response.status === "success") {
                alert("บันทึก Start Transport เรียบร้อย");

                var regSelect = $('#registration_t1');
                if (regSelect.find("option[value='" + carReg + "']").length === 0) {
                    regSelect.append("<option value='" + carReg + "'>" + carReg + "</option>");
                }

                // set ค่า
                regSelect.val(carReg).trigger('change');
                loadInvoiceList(carReg, "");
                show_transport(tid, tid_a, carReg, date);
            } else {
                alert("บันทึกไม่สำเร็จ");
                console.log(response);
            }

        }
    );

});

});


function loadInvoiceList(reg, keyword) {

    var crit = {
        reg: reg,
        invoiceFilter: keyword
    };

    save_SaveAndAlert("get_last_invoice_id", crit,
        function (response) {

            if (!response.data) { return; }

            var html = "";
            var fixedRow = {
                invoice_id: "P6900001329",
                CustomerName: "2_หจก.สหอะไหล่อิมปอร์ด  เอ็กช์ปอร์ด"
            };


            if (!response.data.some(r => r.invoice_id === fixedRow.invoice_id)) {
                response.data.unshift(fixedRow);
            }
            // -------------------------
            // ส่วนที่ 1 : selected list
            // -------------------------
            for (var inv in selectedInvoices_now) {
                if (selectedInvoices_now.hasOwnProperty(inv)) {
                    html += "<label style='display:block;background:#f1f5f9;padding:4px;border-radius:6px;'>";
                    html += "<input type='checkbox' name='invoice_chk' value='" + inv + "' data-cust='" + (selectedInvoiceInfo[inv] || "") + "' checked>";
                    html += inv + " - " + (selectedInvoiceInfo[inv] || "");
                    html += "</label>";
                }
            }

            // -------------------------
            // ส่วนที่ 2 : search result
            // -------------------------
            response.data.forEach(function (row) {

                if (selectedInvoices_now[row.invoice_id]) { return; }

                html += "<label style='display:block;'>";
                html += "<input type='checkbox' name='invoice_chk' value='" + row.invoice_id + "' data-cust='" + row.CustomerName + "'>";
                html += row.invoice_id + " - " + row.CustomerName;
                html += "</label>";
            });

            $('#invoice_t1').html(html);
        }
    );
}

function getDatetime(tid){
    var d = document.getElementById("date" + tid).value;
    var h = document.getElementById("hour" + tid).value;
    var m = document.getElementById("minute" + tid).value;

    return d + " " + h + ":" + m + ":00";
}


  function get_ShowAndAlert(type, crit, callback) {
      $.ajax({
          url: prg,
          type: "POST",
          data: {
              type: type,
              crit: crit
          },
          dataType: "json",
          error: function (jqXHR, status, err) {
              var json = jqXHR.responseJSON;
              if (json === undefined)
                  stp_alert("danger", "Error! Permission denied login and try again.");
              else stp_alert(json.stp_alert.status, json.stp_alert.text);
          },
          success: function (data) {
              if (data.stp_alert)
                  stp_alert(data.stp_alert.status, data.stp_alert.text);
              callback(data);
          }
      });
  }
  
  function save_SaveAndAlert(type, crit, callback ) {
      $.ajax({
          url: prg,
          type: "POST",
          data: {
              type: type,
              crit: crit
          },
          dataType: "json",
          error: function (jqXHR, status, err) {
              var json = jqXHR.responseJSON;
              if (json === undefined)
                  stp_alert("danger", "Error! Permission denied login and try again.");
              else stp_alert(json.stp_alert.status, json.stp_alert.text);
          },
          success: function (data) {
              console.log("Response from PHP:", data);  
              

              if (data.stp_alert)
                  stp_alert(data.stp_alert.status, data.stp_alert.text);
              callback(data);
          }
      });
  }