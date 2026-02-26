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
    $(`#get${tid}`).on("click", function () {
        const date = $(`#date${tid}`).val();
        const registration = $(`#registration${tid}`).val();
        
        // ตรวจสอบว่ามีการเลือกวันที่หรือไม่
        if (!date) {
            alert('กรุณาเลือกวันที่');
            return;
        }
        
        show_Report_transport(tid, date,registration);
    });
}

async function show_Report_transport(tid, date,registration) {
    const crit = { tid:tid ,date: date ,registration:registration };

    await save_SaveAndAlert("show_Report_transport", crit, (response) => {
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
    $(`#get${tid}`).on("click", function () {
        const date = $(`#date${tid}`).val();
        
        // ตรวจสอบว่ามีการเลือกวันที่หรือไม่
        if (!date) {
            alert('กรุณาเลือกวันที่');
            return;
        }
        
        show_Report_BOX_transport(tid, date);
    });
}

async function show_Report_BOX_transport(tid, date) {
    const crit = { tid: tid, date: date };

    await save_SaveAndAlert("show_Report_BOX_transport", crit, (response) => {
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
            setTimeout(() => {
                const grid = document.querySelector('#grid' + tid);
                if (grid) {
                    const colgroups = grid.querySelectorAll('colgroup');
                    colgroups.forEach(cg => cg.remove());
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
    $(document).on("click", `#get${tid}`, function () {
        const registration = $(`#registration${tid}`).val();
        const date = $(`#date${tid}`).val();

        if (registration === "") {
            alert("กรุณากรอก ทะเบียน ก่อน!");
            return;
        }

        const tid_a = $(this).attr("tida");
        show_transport(tid, tid_a, registration, date);
    });


    $(document).on('change', '#Car_reg_INSERT_t1', async function () {

        let carReg = $(this).val();

        selectedInvoices_now.clear();

        loadInvoiceList(carReg, '');

        await loadLastKM(carReg);
    });

    async function loadLastKM(carReg) {

        const crit = { carReg: carReg };

        await save_SaveAndAlert("get_last_km", crit,
            function (response) {
                if (response.data && response.data.Mileage) {
                    $('#StartKM_t1').val(response.data.Mileage);
                } else {
                    $('#StartKM_t1').val('');
                }
            }
        );
    }


    // กดปุ่ม Save KM
    $(document).on("click", ".btn-save-km", async function () {

        const recordID = $(this).data("recordid");
        const carReg   = $(this).data("car");
        const tid  = '_t1';
        const date = $(`#date${tid}`).val();
        const tid_a = $(`#get${tid}`).attr("tida");
        let startKM = $(`#start_km_${recordID}`).val();
        let endKM   = $(`#end_km_${recordID}`).val();
        let fuelCosts = $(`#fuel_costs_${recordID}`).val();
        let shippingCosts = $(`#shipping_costs_${recordID}`).val();

        // fallback start KM จาก text
        if (!startKM) {
            startKM = $(`tr[data-recordid="${recordID}"]`).find("td:eq(1)").text().trim();
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
        
        const crit = {
            recordID: recordID,
            car_registration: carReg,
            start_km: startKM,
            end_km: endKM,
            fuel_costs: fuelCosts,
            shipping_costs: shippingCosts,
            date: date
        };

        await save_SaveAndAlert("savekm_transport", crit,
            async function (response) {

                if (response.status === "success") {
                    alert("บันทึกข้อมูลเรียบร้อย");

                    await loadLastKM(carReg);
                    show_transport(tid, tid_a, carReg, date);
                } else {
                    alert("บันทึกไม่สำเร็จ");
                    
                }
            }
        );
    });

    // กดปุ่ม RecordID เพื่อแสดงรายละเอียด
    $(document).on("click", ".btn-show-detail", function () {
        const recordID = $(this).data("recordid");
        const detailRow = $(`#detail-${recordID}`);
        
        if (detailRow.is(":visible")) {
            detailRow.hide();
            return;
        }

        $(".detail-row").hide();
        detailRow.show();
        load_detail_data(tid, recordID);
    });

}

async function show_transport(tid, tid_a, registration, date) {
    const crit = { 
        dtid: tid, 
        allow: tid_a,
        registration: registration,
        date: date,
    };
    
    await save_SaveAndAlert("show_transport", crit, (response) => {
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

let selectedInvoices = {};
let existingInvoices = {};
let deletedInvoices  = {};

/* ===============================
   🔹 ฟังก์ชันดึงข้อมูล Detail
   จาก tblTransportstra_registration_detail
=============================== */
async function load_detail_data(tid, recordID) {

    const crit = { 
        RecordID: recordID,
        };

    await save_SaveAndAlert("show_transport_detail", crit, (response) => {

        if (!response.success) {
            alert("ไม่สามารถโหลดข้อมูลได้");
            return;
        }

        const detailRow = $(`#detail-${recordID}`);
        const detailData = response.detail_data || [];

        // ตรวจสอบว่ามีข้อมูลหรือไม่
        if (detailData.length === 0) {
            detailRow.find("td").html(`
                <div style="padding:30px; text-align:center; color:#999;">
                    <p style="font-size:16px;">📋 ไม่มีข้อมูล Invoice</p>
                    <p style="font-size:14px; margin-top:5px;">ยังไม่มีการบันทึก Invoice สำหรับรายการนี้</p>
                </div>
            `);
            return;
        }

        // 🔹 GROUP DATA BY invoice
        const grouped = {};

        detailData.forEach(row => {
            if (!grouped[row.invoice]) {
                grouped[row.invoice] = {
                    invoice: row.invoice,
                    CustomerName: row.CustomerName,
                    Customer_ID: row.Customer_ID,
                    create_date: row.create_date,
                    items: []
                };
            }

            grouped[row.invoice].items.push({
                item_id: row.item_id,
                qty: row.qty,
                M3: row.M3
            });
        });

        let html = `
        <div style="padding:15px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h4 style="margin:0; color:#333;">📋 รายการ Invoice ที่บันทึกแล้ว</h4>
            </div>
            
            <div style="max-height:400px; overflow:auto; border:1px solid #ddd; border-radius:4px;">
            <table style="width:100%; border-collapse:collapse;">
            <thead style="background:#ffcc00; position:sticky; top:0;">
                <tr>
                    <th style="padding:10px; border:1px solid #ddd; text-align:left;">#</th>
                    <th style="padding:10px; border:1px solid #ddd; text-align:left;">Invoice</th>
                    <th style="padding:10px; border:1px solid #ddd; text-align:left;">Customer</th>
                    <th style="padding:10px; border:1px solid #ddd; text-align:center;">บันทึกเมื่อ</th>
                </tr>
            </thead>
            <tbody>
        `;

        let rowNum = 1;
        Object.values(grouped).forEach(group => {

            // สร้างรายการ items แบบละเอียด
            const itemsHTML = group.items.map(item => {
                const m3Display = item.M3 ? ` | ${item.M3} m³` : '';
                return `<div style="margin:2px 0;">• ${item.item_id} <span style="color:#666;">(${item.qty}${m3Display})</span></div>`;
            }).join('');

            // Format วันที่
            const createDate = group.create_date ? 
                new Date(group.create_date).toLocaleString('th-TH', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }) : '-';

            html += `
            <tr style="background:#f8f9fa;">
                <td style="padding:10px; border:1px solid #ddd; text-align:center; font-weight:bold; color:#2196f3;">
                    ${rowNum}
                </td>
                <td style="padding:10px; border:1px solid #ddd;">
                    <div style="font-weight:bold; color:#333;">${group.invoice}</div>
                    <div style="font-size:11px; color:#999; margin-top:2px;">ID: ${group.Customer_ID}</div>
                </td>
                <td style="padding:10px; border:1px solid #ddd;">
                    ${group.CustomerName}
                </td>
                <td style="padding:10px; border:1px solid #ddd; text-align:center; font-size:12px; color:#666;">
                    ${createDate}
                </td>
            </tr>`;
            
            rowNum++;
        });

        html += `
            </tbody>
            </table>
            </div>
            
            <div style="margin-top:15px; padding:10px; background:#e3f2fd; border-radius:4px; font-size:13px; color:#1976d2;">
                ℹ️ แสดงข้อมูล ${Object.keys(grouped).length} Invoice (${detailData.length} รายการ)
            </div>
        </div>`;

        detailRow.find("td").html(html);
    });
}

let selectedInvoices_now = new Set();
let selectedInvoiceInfo = {};
$(document).ready(function(){

 
    // search invoice
    $(document).on('keyup', '#invoice_search_t1', function(){
        let keyword = $(this).val();
        let reg = $('#Car_reg_INSERT_t1').val();
        loadInvoiceList(reg, keyword);
    });

$(document).on("change", "input[name='invoice_chk']", function () {
    const inv = $(this).val();
    const cust = $(this).data("cust");

    if (this.checked) {
        selectedInvoices_now.add(inv);
        selectedInvoiceInfo[inv] = cust;
    } else {
        selectedInvoices_now.delete(inv);
        delete selectedInvoiceInfo[inv];
    }

    renderSelectedPreview();
});

    function renderSelectedPreview() {
    let html = "";
    let i = 1;

    selectedInvoices_now.forEach(inv => {
        html += `
            <div style="font-weight:bold;color:#2563eb">
                ${i}. ${inv} - ${selectedInvoiceInfo[inv] ?? ""}
            </div>
        `;
        i++;
    });

    $("#selected_invoice_preview").html(html);
}

$(document).on("click", "#save_start_t1", async function () {

    const tid = "_t1";
    const tid_a = $(`#get${tid}`).attr("tida");
    const carReg   = $(`#Car_reg_INSERT${tid}`).val();
    const startKM  = $(`#StartKM${tid}`).val();
    const date     = $(`#date_insert${tid}`).val();
    const hour     = $(`#hour${tid}`).val();
    const minute   = $(`#minute${tid}`).val();

    const datetime = `${date} ${hour}:${minute}:00`;

    /* ดึง invoice ที่ถูกเลือก */
    let invoices = [];
    $(`#invoice${tid} input[type=checkbox]:checked`).each(function () {
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

    const crit = {
        car_registration: carReg,
        start_km: startKM,
        datetime: datetime,
        invoice_list: invoices
    };

    await save_SaveAndAlert("save_start_transport", crit,
        function (response) {

            if (response.status === "success") {
                alert("บันทึก Start Transport เรียบร้อย");

                const $regSelect = $('#registration_t1');
                 if ($regSelect.find(`option[value="${carReg}"]`).length === 0) {
                    $regSelect.append(`<option value="${carReg}">${carReg}</option>`);
                }

                // set ค่า
                $regSelect.val(carReg).trigger('change');
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


async function loadInvoiceList(reg, keyword) {

    const crit = {
        reg: reg,
        invoiceFilter: keyword
    };

    await save_SaveAndAlert("get_last_invoice_id", crit,
        function (response) {

            if (!response.data) return;

            let html = "";

            // -------------------------
            // ส่วนที่ 1 : selected list
            // -------------------------
            selectedInvoices_now.forEach(inv => {
                html += `
                    <label style="display:block;background:#f1f5f9;padding:4px;border-radius:6px;">
                        <input type="checkbox"
                               name="invoice_chk"
                               value="${inv}"
                               data-cust="${selectedInvoiceInfo[inv] ?? ""}"
                               checked>
                        ${inv} - ${selectedInvoiceInfo[inv] ?? ""}
                    </label>
                `;
            });

            // -------------------------
            // ส่วนที่ 2 : search result
            // -------------------------
            response.data.forEach(row => {

                if (selectedInvoices_now.has(row.invoice_id)) return;

                html += `
                    <label style="display:block;">
                        <input type="checkbox"
                               name="invoice_chk"
                               value="${row.invoice_id}"
                               data-cust="${row.CustomerName}">
                        ${row.invoice_id} - ${row.CustomerName}
                    </label>
                `;
            });

            $('#invoice_t1').html(html);
        }
    );
}

function getDatetime(tid){
    const d = document.getElementById("date"+tid).value;
    const h = document.getElementById("hour"+tid).value;
    const m = document.getElementById("minute"+tid).value;

    return `${d} ${h}:${m}:00`;
}


  async function get_ShowAndAlert(type, crit, callback) {
      $.ajax({
          url: prg,
          type: "POST",
          data: {
              type: type,
              crit: crit,
          },
          dataType: "json",
          error: function (jqXHR, status, err) {
              const json = jqXHR.responseJSON;
              if (json === undefined)
                  stp_alert("danger", "Error! Permission denied login and try again.");
              else stp_alert(json.stp_alert.status, json.stp_alert.text);
          },
          success: function (data) {
              if (data.stp_alert)
                  stp_alert(data.stp_alert.status, data.stp_alert.text);
              callback(data);
          },
      });
  }
  
  async function save_SaveAndAlert(type, crit, callback ) {
      $.ajax({
          url: prg,
          type: "POST",
          data: {
              type: type,
              crit: crit,
          },
          dataType: "json",
          error: function (jqXHR, status, err) {
              const json = jqXHR.responseJSON;
              if (json === undefined)
                  stp_alert("danger", "Error! Permission denied login and try again.");
              else stp_alert(json.stp_alert.status, json.stp_alert.text);
          },
          success: function (data) {
              console.log("Response from PHP:", data);  
              
  
              if (data.stp_alert)
                  stp_alert(data.stp_alert.status, data.stp_alert.text);
              callback(data);
          },
      });
  }
