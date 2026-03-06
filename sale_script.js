document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("tbody");
  const addRowBtn = document.getElementById("addRow");
  const saveBtn = document.getElementById("saveData");
  const loadBtn = document.getElementById("loadData");
  const clearBtn = document.getElementById("clearForm");

  function calculateRemaining() {
    const total = Number(document.getElementById("total").value) || 0;
    const received = Number(document.getElementById("receivedAmount").value) || 0;
    const kg15 = Number(document.getElementById("kg15").value) || 0;
    const kg45 = Number(document.getElementById("kg45").value) || 0;
    const kg15Rec = Number(document.getElementById("kg15Received").value) || 0;
    const kg45Rec = Number(document.getElementById("kg45Received").value) || 0;

    document.getElementById("amountRemaining").value = total - received;
    document.getElementById("totalCylinderRemaining").value =
      kg15 + kg45 - (kg15Rec + kg45Rec);
    document.getElementById("totalAmountRemaining").value = total - received;
  }

  ["total", "receivedAmount", "kg15", "kg45", "kg15Received", "kg45Received"]
    .forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener("input", calculateRemaining);
    });

  function getValues() {
    return {
      name: document.getElementById("name").value.trim(),
      date: document.getElementById("date").value || new Date().toISOString().split("T")[0],
      kg15: Number(document.getElementById("kg15").value) || 0,
      kg45: Number(document.getElementById("kg45").value) || 0,
      total: Number(document.getElementById("total").value) || 0,
      receivedAmount: Number(document.getElementById("receivedAmount").value) || 0,
      amountRemaining: Number(document.getElementById("amountRemaining").value) || 0,
      kg15Received: Number(document.getElementById("kg15Received").value) || 0,
      kg45Received: Number(document.getElementById("kg45Received").value) || 0,
      totalCylinderRemaining: Number(document.getElementById("totalCylinderRemaining").value) || 0,
      totalAmountRemaining: Number(document.getElementById("totalAmountRemaining").value) || 0
    };
  }

  function addRow(data) {
    const row = document.createElement("tr");
    const fields = [
      "name", "date", "kg15", "kg45", "total", "receivedAmount",
      "amountRemaining", "kg15Received", "kg45Received",
      "totalCylinderRemaining", "totalAmountRemaining"
    ];

    fields.forEach(key => {
      const td = document.createElement("td");
      td.textContent = data[key];
      row.appendChild(td);
    });

    tbody.appendChild(row);
  }

  async function saveToServer(entry) {
    try {
      const res = await fetch("sale_save.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(entry)
      });

      const result = await res.json();
      if (result.status === "success") {
        alert("✅ Saved successfully!");
      } else {
        alert("❌ Error: " + (result.message || "Unknown error"));
      }

    } catch (err) {
      console.error(err);
      alert("⚠️ Server connection error.");
    }
  }

  addRowBtn.addEventListener("click", () => {
    const data = getValues();
    if (!data.name || !data.date) {
      alert("Please enter name and date");
      return;
    }
    addRow(data);
  });

  saveBtn.addEventListener("click", async () => {
    const entry = getValues();
    await saveToServer(entry);
    await updateSaleTotals();  // 🔥 auto-refresh totals after saving
  });

  loadBtn.addEventListener("click", async () => {
    try {
      const res = await fetch("sale_load.php");
      const data = await res.json();
      tbody.innerHTML = "";
      data.forEach(addRow);
      alert("📂 Data loaded!");
    } catch (err) {
      alert("⚠️ Could not load data");
    }
  });

  clearBtn.addEventListener("click", () => {
    document.querySelectorAll(".form-section input").forEach(input => {
      input.value = "";
    });
  });
});

/* 
💡 NOTE:
You MUST create/update the function updateSaleTotals()
in another JS file or inside this one.
*/
async function updateSaleTotals() {
  try {
    const res = await fetch("sale_totals.php");
    const data = await res.json();

    document.getElementById("totalSale").textContent =
      "Rs. " + Number(data.sale || 0).toLocaleString();

    document.getElementById("totalReceived").textContent =
      "Rs. " + Number(data.received || 0).toLocaleString();

    document.getElementById("totalRemaining").textContent =
      "Rs. " + Number(data.remaining || 0).toLocaleString();

  } catch (err) {
    console.error("❌ Could not update totals", err);
  }
}
