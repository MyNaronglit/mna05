$(document).ready(function () {
  $("#tabs").tabs();
  prg = "transport01x.php";
  try {
    bind_transport("_t1");
  } catch (a) {}
  try {
    bind_Report_transport("_t2");
  } catch (a) {}
  try {
    bind_Report_BOX_transport("_t3");
  } catch (a) {}
  try {
    bind_Add_license_transport("_t4");
  } catch (a) {}
    try {
    bind_Report_Sum_transport("_t5");
  } catch (a) {}
});
function bind_Add_license_transport(tid) {

   $("#save" + tid).on("click", function () {

    var plate = $("#plate" + tid).val();
    var driver = $("#driver" + tid).val();
    var car_atten = $("#car_atten" + tid).val();

    if (plate === "" || driver === "") {
      alert("กรุณากรอกข้อมูลให้ครบ");
      return;
    }

    var crit = {
      plate: plate,
      driver: driver,
      car_atten: car_atten
    };
    save_SaveAndAlert("Add_license", crit, function (res) {
      if (res.status === "success") {
        alert("บันทึกสำเร็จ");
        location.reload();
        re
      } else {
        alert("บันทึกไม่สำเร็จ");
      }
    });
  });

}




function bind_Report_transport(tid) {
  $("#get" + tid).on("click", function () {
    var date = $("#date" + tid).val();
    var registration = $("#registration" + tid).val();
    var invoice = $("#invoice" + tid).val();
    if (!date && !registration && !invoice) {
      alert("กรุณาระบุอย่างน้อย 1 เงื่อนไข");
      return;
    }
    show_Report_transport(tid, date, registration, invoice);

  });

}
function show_Report_transport(tid, date, registration, invoice) {
  var data = {
    tid: tid,
    date: date,
    registration: registration,
    invoice: invoice
  };
  save_SaveAndAlert("show_Report_transport", data, function (res) {

    if (res.status === "error") {
      alert("ไม่พบข้อมูล");
      return;
    }
    create_fix_tab(
      res.table1.cid,
      res.table1.table,
      res.table1.tid,
      res.table1.tit,
      res.table1.hi,
      res.table1.wi
    );

  });
}
function bind_Report_Sum_transport(tid) {
  $("#get" + tid).on("click", function () {
    var date = $("#date" + tid).val();
    var registration = $("#registration" + tid).val();
    var invoice = $("#invoice" + tid).val();
    if (!date && !registration && !invoice) {
      alert("กรุณาระบุอย่างน้อย 1 เงื่อนไข");
      return;
    }
    show_Report_Sum_transport(tid, date, registration, invoice);

  });

}
function show_Report_Sum_transport(tid, date, registration, invoice) {
  var data = {
    tid: tid,
    date: date,
    registration: registration,
    invoice: invoice
  };
  save_SaveAndAlert("show_Report_Sum_transport", data, function (res) {

    if (res.status === "error") {
      alert("ไม่พบข้อมูล");
      return;
    }
    create_fix_tab(
      res.table1.cid,
      res.table1.table,
      res.table1.tid,
      res.table1.tit,
      res.table1.hi,
      res.table1.wi
    );

  });
}
function bind_Report_BOX_transport(a) {
  $("#get" + a).on("click", function () {
    var b = $("#date" + a).val();
    if (!b) {
      alert("กรุณาเลือกวันที่");
      return;
    }
    show_Report_BOX_transport(a, b);
  });
}
function show_Report_BOX_transport(c, b) {
  var a = { tid: c, date: b };
  save_SaveAndAlert("show_Report_BOX_transport", a, function (d) {
         if (!d || !d.table1 || !d.table1.table ||  Array.isArray(d.table1.table) &&  d.table1.table[0].indexOf("ไม่พบข้อมูล") !== -1) {
      alert("วันที่เลือกไม่มีข้อมูล");
      return;
    }
      create_fix_tab(
        d.table1.cid,
        d.table1.table,
        d.table1.tid,
        d.table1.tit,
        d.table1.hi,
        d.table1.wi,
      );
      setTimeout(function () {
        var e = document.querySelector("#grid" + c);
        if (e) {
          var f = e.querySelectorAll("colgroup");
          f.forEach(function (g) {
            g.remove();
          });
        }
      }, 100);
  });
}
function bind_transport(b) {
  $(document).on("click", "#get" + b, function () {
    var d = $("#registration" + b).val();
    var c = $("#date" + b).val();
    if (d === "") {
      alert("กรุณาเลือก ทะเบียน ก่อน!");
      return;
    }
    var e = $(this).attr("tida");
    show_transport(b, e, d, c);
  });
  $(document).on("change", "#Car_reg_INSERT_t1", function () {
    var c = $(this).val();
    selectedInvoices_now = {};
    loadInvoiceList(c, "");
    a(c);
  });
  function a(d) {
    var c = { carReg: d };
    save_SaveAndAlert("get_last_km", c, function (e) {
      if (e.data && e.data.stop_KM) {
        $("#StartKM_t1").val(e.data.stop_KM);
         $(".integer-format").each(function(){
        formatIntegerInput(this);
      });
      } else {
        $("#StartKM_t1").val("");
      }
    });
  }
$(document).on("blur", ".input-end-km", function () {
    var $input = $(this);
    var h = $(this).data("recordid");
    var g = $(this).data("car");
    var e = "_t1";
    var c = $("#date" + e).val();
    var l = $("#get" + e).attr("tida");

    var i = $(this).data("start") || $("tr[data-recordid='" + h + "']")
        .find("td:eq(1)")
        .text()
        .replace(/,/g, '')
        .trim();

    var j = $(this).val().replace(/,/g, '');
    var k = $("#fuel_costs_" + h).val().replace(/,/g, '') || 0;

    if (j === "") {
        alert("กรุณากรอก End KM!");
        show_transport(e, l, g, c);
        return;
    }

    i = parseInt(i || 0);
    j = parseInt(j || 0);
    k = parseFloat(k || 0);

    if (j <= i) {
        alert("End KM ต้องมากกว่า Start KM!");
        show_transport(e, l, g, c);
        return;
    }

    var f = {
        recordID: h,
        car_registration: g,
        start_km: i,
        end_km: j, 
        fuel_costs: k,
        date: c,
    };

    save_SaveAndAlert("savekm_transport", f, function (m) {
        if (m.status === "success") {
            var alertBox = $("<div>บันทึกสำเร็จ</div>").css({
                position: "fixed",
                top: "20px",
                right: "20px",
                background: "#16a34a",
                color: "#fff",
                padding: "10px 16px",
                borderRadius: "8px",
                zIndex: 9999,
                boxShadow: "0 4px 12px rgba(0,0,0,0.2)"
            });

            $("body").append(alertBox);

            setTimeout(function () {
                alertBox.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 1500);

            a(g);
            show_transport(e, l, g, c);
        } else {
            alert("บันทึกไม่สำเร็จ");
        }
    });
});

$(document).on("blur", ".input-fuel-costs", function () {
    var h = $(this).data("recordid");
    var g = $(this).data("car");
    var e = "_t1";
    var c = $("#date" + e).val();
    var l = $("#get" + e).attr("tida");
    var i = $(this).data("start") || $("tr[data-recordid='" + h + "']").find("td:eq(1)").text().trim();
    var j = $("#end_km_" + h).val().replace(/,/g, '');
    var k = $(this).val().replace(/,/g, '');

    if (j === "" || j == 0) {
        alert("กรุณากรอก End KM ก่อน!");
        return;
    }

    i = parseFloat(i || 0);
    j = parseFloat(j);
    k = parseFloat(k || 0);

    var f = {
        recordID: h,
        car_registration: g,
        start_km: i,
        end_km: j,
        fuel_costs: k.toFixed(4),
        date: c,
    };

    save_SaveAndAlert("savekm_transport", f, function (m) {
        if (m.status === "success") {
            var alertBox = $("<div>บันทึกสำเร็จ</div>").css({
                position: "fixed",
                top: "20px",
                right: "20px",
                background: "#16a34a",
                color: "#fff",
                padding: "10px 16px",
                borderRadius: "8px",
                zIndex: 9999,
                boxShadow: "0 4px 12px rgba(0,0,0,0.2)"
            });

            $("body").append(alertBox);

            setTimeout(function () {
                alertBox.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 1500);

            a(g);
            show_transport(e, l, g, c);
        } else {
            alert("บันทึกไม่สำเร็จ");
        }
    });
});

  $(document).on("click", ".btn-show-detail", function () {
    var c = $(this).data("recordid");
    var d = $("#detail-" + c);
    if (d.is(":visible")) {
      d.hide();
      return;
    }
    $(".detail-row").hide();
    d.show();
    load_detail_data(b, c);
  });
}
function show_transport(e, d, c, b) {
  var a = { dtid: e, allow: d, registration: c, date: b };
  save_SaveAndAlert("show_transport", a, function (f) {
    create_fix_tab(
      f.table1.cid,
      f.table1.table,
      f.table1.tid,
      f.table1.tit,
      f.table1.hi,
      f.table1.wi,
    );
     $(".integer-format").each(function(){
        formatIntegerInput(this);
    });
    $(".number-format").each(function () {
    formatNumberInput(this);
    });
    $(".number-format").each(function () {
    formatNumberText(this);
    });
  });
}
var selectedInvoices = {};
var existingInvoices = {};
var deletedInvoices = {};

function load_detail_data(c, b) {
  var a = { RecordID: b };

  save_SaveAndAlert("show_transport_detail", a, function (e) {
    var g = $("#detail-" + b);

    if (!e.detail || e.detail.length === 0) {
      g.find("td").html(
        "<div style='padding:20px; text-align:center; color:#999;'>ไม่มีข้อมูล Invoice</div>"
      );
      return;
    }

    var d = e.detail;
    var h = {};

    d.forEach(function (m) {
      if (!h[m.invoice]) {
        h[m.invoice] = {
          invoice: m.invoice,
          Customer_ID: m.Customer_ID,
          CustomerName: m.CustomerName,
          stop_time: m.stop_time,
          Shipping: parseFloat(m.shipping || m.Shipping || 0)
        };
      }

      // ถ้ามี record ไหนมี shipping > 0 ให้ใช้ค่านั้น
      if (parseFloat(m.shipping || 0) > 0) {
        h[m.invoice].Shipping = parseFloat(m.shipping);
      }
    });

    var f =
      "<div style='padding:15px; font-family:Arial; background:#fff; border-radius:8px;'>";

    f += "<div style='margin-bottom:10px; font-size:16px; font-weight:bold; color:#1e40af;'>รายการ Invoice</div>";

    f += "<div style='max-height:350px; overflow-y:auto; border:1px solid #ddd; border-radius:4px;'>";
    f += "<table style='width:100%; border-collapse:collapse;'>";

    f += "<thead style='background:#1e3a8a; color:#fff; position:sticky; top:0;'>";
    f += "<tr>";
    f += "<th style='width:20%; border:1px solid #ddd; text-align:center;'>ลำดับ</th>";
    f += "<th style='width:70%;padding:10px; border:1px solid #ddd;'>Invoice</th>";
    f += "<th style='width:80%;padding:10px; border:1px solid #ddd;'>Customer ID</th>";
    f += "<th style='width:60%;padding:10px; border:1px solid #ddd;'>Shipping costs</th>";
    f += "<th style='width:50%;padding:10px; border:1px solid #ddd; text-align:center;'>Stop Time</th>";
    f += "</tr>";
    f += "</thead>";

    f += "<tbody>";

    var i = 1;

    for (var l in h) {
      if (h.hasOwnProperty(l)) {
        var k = h[l];

        var hasShipping = k.Shipping && parseFloat(k.Shipping) > 0;

        var j = k.stop_time
          ? new Date(k.stop_time).toLocaleString("th-TH", {
              year: "numeric",
              month: "short",
              day: "numeric",
              hour: "2-digit",
              minute: "2-digit",
            })
          : "-";
        var shippingValue = k.Shipping
          ? Number(k.Shipping).toFixed(2)
          : "";
        f += "<tr style='background:#f8f9fa;'>";
        f += "<td style='padding:10px; border:1px solid #ddd; text-align:center; font-weight:bold; color:#2196f3;'>" + i + "</td>";

        f += "<td style='padding:10px; border:1px solid #ddd;'>";
        f += "<div style='font-weight:bold; color:#333;'>" + k.invoice + "</div>";
        f += "</td>";

        f += "<td style='padding:10px; border:1px solid #ddd;'>" + (k.CustomerName || "-") + "</td>";

        f += "<td style='padding:10px; border:1px solid #ddd; text-align:center;'>";

        f += "<input type='text' class='shipping-input number-format shipping-auto-save' ";
        f += "data-invoice='" + k.invoice + "' ";
        f += "data-recordid='" + b + "' ";
        f += "style='width: 80px; padding:5px; text-align:right;' ";
        f += "value='" + shippingValue + "' ";
        f += "placeholder='ค่าขนส่ง'/>";

        f += "</td>";

        f += "<td style='padding:10px; border:1px solid #ddd; text-align:center; font-size:12px; color:#666;'>" + j + "</td>";
        f += "</tr>";

        i++;
      }
    }

    f += "</tbody></table></div>";

    f += "<div style='padding:10px; background:#e3f2fd; border-radius:4px; font-size:13px; color:#1976d2;'>";
    f += "ℹ️ แสดงข้อมูล " + Object.keys(h).length + " Invoice (" + d.length + " รายการ)";
    f += "</div>";
    f += "</div>";

    g.find("td").html(f);
    g.find(".number-format").each(function () {
        formatNumberInput(this);
    });
  });
}
$(document).on("blur", ".shipping-auto-save", function () {

    var recordID = $(this).data("recordid");
    var invoice = $(this).data("invoice");
    var shipping = $(this).val().replace(/,/g, '');
    var g = $(this).data("car");
    var e = "_t1";
    var c = $("#date" + e).val();
    var l = $("#get" + e).attr("tida");
    if (shipping === "") shipping = 0;

    shipping = parseFloat(shipping);

    // คำนวณ total ใหม่
    var totalShipping = 0;
    $(".shipping-auto-save").each(function () {
        var v = $(this).val().replace(/,/g, '');
        if (v === "") v = 0;
        totalShipping += parseFloat(v);
    });

    var payload = {
        RecordID: recordID,
        invoice: invoice,
        shipping_costs: shipping.toFixed(4),
        total_shipping: totalShipping.toFixed(4),
    };

    save_SaveAndAlert("save_shipping", payload, function (res) {
       if (res.status === "success") {
            var alertBox = $("<div>บันทึกสำเร็จ</div>").css({
                position: "fixed",
                top: "20px",
                right: "20px",
                background: "#16a34a",
                color: "#fff",
                padding: "10px 16px",
                borderRadius: "8px",
                zIndex: 9999,
                boxShadow: "0 4px 12px rgba(0,0,0,0.2)"
            });

            $("body").append(alertBox);

            setTimeout(function () {
                alertBox.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 1500);

        } else {
            alert("บันทึกไม่สำเร็จ");
        }
    });

});


var selectedInvoices_now = {};
var selectedInvoiceInfo = {};
var selectedInvoiceCustID = {};
var selectedInvoiceSales = {};
$(document).ready(function () {
  $(document).on("keyup", "#invoice_search_t1", function () {
    var b = $(this).val();
    var c = $("#Car_reg_INSERT_t1").val();
    loadInvoiceList(c, b);
  });
$(document).on("change", "input[name='invoice_chk']", function () {
    var b = $(this).val();
    var c = $(this).data("custname");
    var custid = $(this).data("custid");
    var sales = $(this).data("sales");
    if (this.checked) {
      selectedInvoices_now[b] = true;
      selectedInvoiceInfo[b] = c;
      selectedInvoiceCustID[b] = custid;
      selectedInvoiceSales[b] = sales;
    } else {
      delete selectedInvoices_now[b];
      delete selectedInvoiceInfo[b];
      delete selectedInvoiceCustID[b];
      delete selectedInvoiceSales[b];
    }
    a();
});
function a() {

  let html = `
    <div style="display:grid;grid-auto-flow:column;grid-template-rows:repeat(2,auto);grid-auto-columns:140px;gap:6px;overflow-x:auto;font-size:11px;
    ">
  `;

  Object.keys(selectedInvoices_now).forEach((key, i) => {
    html += `
      <div style="background:#f1f5f9;padding:4px 6px;border-radius:4px; line-height:1; width: 180px;
      ">
        <b>${i + 1}. ${key}</b><br>
        <span style="color:#555;">
          ${selectedInvoiceInfo[key] || ""}
        </span>
      </div>
    `;
  });

  html += "</div>";

  $("#selected_invoice_preview").html(html);
}
  $(document).on("click", "#save_start_t1", function () {
    var d = "_t1";
    var k = $("#get" + d).attr("tida");
    var h = $("#Car_reg_INSERT" + d).val();
    var j = $("#StartKM" + d).val().replace(/,/g, '');
    var b = $("#date_insert" + d).val();
    var e = $("#hour" + d).val();
    var c = $("#minute" + d).val();
    var i = b + " " + e + ":" + c + ":00";
    var g = [];
    $("#invoice" + d + " input[type=checkbox]:checked").each(function () {
      g.push($(this).val());
    });
    if (!h) {
      alert("กรุณาเลือกทะเบียนรถ");
      return;
    }
    if (!b) {
      alert("กรุณาเลือกวันที่");
      return;
    }
    if (g.length === 0) {
      alert("กรุณาเลือก invoice อย่างน้อย 1 รายการ");
      return;
    }
    var f = { car_registration: h, start_km: j, datetime: i, invoice_list: g };
    save_SaveAndAlert("save_start_transport", f, function (l) {
        if (l.status === "error") {
        alert(l.message);
        return;
      }
      if (l.status === "success") {
        alert("บันทึก Start Transport เรียบร้อย");
        var m = $("#registration_t1");
        if (m.find("option[value='" + h + "']").length === 0) {
          m.append("<option value='" + h + "'>" + h + "</option>");
        }
        m.val(h).trigger("change");
        loadInvoiceList(h, "");
        show_transport(d, k, h, b);
        
      } else {
        alert("บันทึกไม่สำเร็จ");
      }
    });
  });
});
function loadInvoiceList(c, b) {
  var a = { reg: c, invoiceFilter: b };
  save_SaveAndAlert("get_last_invoice_id", a, function (e) {
    if (!e.data) {
      return;
    }
    var f = "";

    for (var d in selectedInvoices_now) {
      if (selectedInvoices_now.hasOwnProperty(d)) {
        f +=
          "<label style='display:block;background:#f1f5f9;padding:4px;border-radius:6px;'>";
        f += "<input type='checkbox' name='invoice_chk' class='invoice_chk' " +
          "value='" + d + "' " +
          "data-custid='" + (selectedInvoiceCustID[d] || "") + "' " +
          "data-custname='" + (selectedInvoiceInfo[d] || "") + "' " +
          "data-sales='" + (selectedInvoiceSales[d] || 0) + "' " +
          "checked>";
        f += d + " - " + (selectedInvoiceInfo[d] || "");
        f += "</label>";
      }
    }
e.data.forEach(function (g) {

  if (selectedInvoices_now[g.invoice_id]) {
    return;
  }

  f += "<label style='display:block;border-bottom:1px solid #eee;padding:4px;'>";

  f += "<input type='checkbox'  name='invoice_chk' class='invoice_chk' " +
       "value='" + g.invoice_id + "' " +
       "data-custid='" + g.Customer_ID + "' " +
       "data-custname='" + g.CustomerName + "' " +
       "data-sales='" + g.Sales_amt + "'>";

  f += " " + g.invoice_id +
       " - " + g.CustomerName + " "


  f += "</label>";
});
    $("#invoice_t1").html(f);
  });
}

$(document).on("change", ".invoice_chk", function () {

  var summary = {};

  $(".invoice_chk:checked").each(function () {

    var custId   = $(this).data("custid");
    var custName = $(this).data("custname");
    var sales    = parseFloat($(this).data("sales"));

    if (!summary[custId]) {
      summary[custId] = {
        name: custName,
        total: 0
      };
    }

    summary[custId].total += sales;
  });

var html = "";

for (var key in summary) {

  var formatted = Number(summary[key].total)
    .toLocaleString("en-US", {
      minimumFractionDigits: 0,
      maximumFractionDigits: 4
    });

html += "<div style='display:flex; justify-content:space-between; padding:6px; border-bottom:1px solid #ddd;'>";
html += "<div>" + summary[key].name + "</div>";
html += "<div style='font-weight:bold;'>" + formatted + "</div>";
html += "</div>";
}

$("#invoice02_t1").html(html);

});
function formatNumberInput(el) {
    var value = $(el).val();

    // ❌ ลบ comma
    value = value.replace(/,/g, '');

    // ❌ ห้ามตัวอักษร อนุญาตแค่ตัวเลขกับจุด
    value = value.replace(/[^0-9.]/g, '');

    // ❌ ให้มีจุดได้แค่ตัวเดียว
    var parts = value.split('.');
    if (parts.length > 2) {
        value = parts[0] + '.' + parts[1];
        parts = value.split('.');
    }

    if (parts[0] !== '') {
        parts[0] = Number(parts[0]).toLocaleString('en-US');
    }

    if (parts.length > 1) {
        parts[1] = parts[1].replace(/[^0-9]/g, ''); // กันตัวอักษรหลังจุดอีกชั้น
        parts[1] = parts[1].replace(/0+$/, '');
        if (parts[1] === '') {
            parts.pop();
        }
    }

    $(el).val(parts.join('.'));
}
// 🔥 ดักตอนพิมพ์ทันที
$(document).on("input", ".number-format", function () {
    var value = $(this).val();

    // ลบ comma
    value = value.replace(/,/g, '');

    // ห้ามตัวอักษร อนุญาตเฉพาะตัวเลข + จุด
    value = value.replace(/[^0-9.]/g, '');

    // ให้มีจุดได้แค่ 1 ตัว
    var parts = value.split('.');
    if (parts.length > 2) {
        value = parts[0] + '.' + parts[1];
    }

    $(this).val(value);
});

function formatNumberText(el) {
    var value = $(el).text();

    value = value.replace(/,/g, '');
    value = value.replace(/[^0-9.]/g, '');

    if (value === '') return;

    var num = parseFloat(value);

    if (!isNaN(num)) {
        $(el).text(
            num.toLocaleString('en-US', {
                maximumFractionDigits: 2
            }).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')
        );
    }
}

function formatIntegerInput(el) {
    var value = $(el).val();

    // ❌ ลบ comma
    value = value.replace(/,/g, '');

    // ❌ อนุญาตเฉพาะตัวเลขเท่านั้น
    value = value.replace(/[^0-9]/g, '');

    if (value === '') {
        $(el).val('');
        return;
    }

    $(el).val(Number(value).toLocaleString('en-US'));
}
// 🔒 integer input — ห้ามตัวอักษร
$(document).on("input", ".integer-format", function () {
    var value = $(this).val();

    value = value.replace(/,/g, '');
    value = value.replace(/[^0-9]/g, '');

    $(this).val(value);
});


function getDatetime(c) {
  var e = document.getElementById("date" + c).value;
  var b = document.getElementById("hour" + c).value;
  var a = document.getElementById("minute" + c).value;
  return e + " " + b + ":" + a + ":00";
}
function get_ShowAndAlert(b, a, c) {
  $.ajax({
    url: prg,
    type: "POST",
    data: { type: b, crit: a },
    dataType: "json",
    error: function (g, d, f) {
      var e = g.responseJSON;
      if (e === undefined) {
        stp_alert("danger", "Error! Permission denied login and try again.");
      } else {
        stp_alert(e.stp_alert.status, e.stp_alert.text);
      }
    },
    success: function (d) {
      if (d.stp_alert) {
        stp_alert(d.stp_alert.status, d.stp_alert.text);
      }
      c(d);
    },
  });
}
function save_SaveAndAlert(b, a, c) {
  $.ajax({
    url: prg,
    type: "POST",
    data: { type: b, crit: a },
    dataType: "json",
    error: function (g, d, f) {
      var e = g.responseJSON;
      if (e === undefined) {
        stp_alert("danger", "Error! Permission denied login and try again.");
      } else {
        stp_alert(e.stp_alert.status, e.stp_alert.text);
      }
    },
    success: function (d) {
      if (d.stp_alert) {
        stp_alert(d.stp_alert.status, d.stp_alert.text);
      }
      c(d);
    },
  });
}
/////////////////ดีที่สุด///////////////////