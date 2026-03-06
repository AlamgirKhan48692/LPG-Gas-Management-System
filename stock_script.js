document.addEventListener("DOMContentLoaded", () => {

  const tbody = document.getElementById("tbody");
  const addRowBtn = document.getElementById("addRow");
  const saveBtn = document.getElementById("saveData");
  const loadBtn = document.getElementById("loadData");
  const clearBtn = document.getElementById("clearForm");

  const total15kg = document.getElementById("total15kg");
  const total45kg = document.getElementById("total45kg");
  const grandTotal = document.getElementById("grandTotal");

  function calculateAmounts() {
    const kg15 = Number(document.getElementById("kg15").value) || 0;
    const kg45 = Number(document.getElementById("kg45").value) || 0;
    const rate = Number(document.getElementById("rate").value) || 0;

    const baseRate = rate / 11.8;
    document.getElementById("baseRate").value = baseRate.toFixed(2);

    const cost15 = baseRate * 15;
    const cost45 = baseRate * 45;
    document.getElementById("cost15").value = cost15.toFixed(2);
    document.getElementById("cost45").value = cost45.toFixed(2);

    const amount15 = cost15 * kg15;
    const amount45 = cost45 * kg45;
    const totalAmount = amount15 + amount45;

    document.getElementById("amount15").value = amount15.toFixed(2);
    document.getElementById("amount45").value = amount45.toFixed(2);
    document.getElementById("totalAmount").value = totalAmount.toFixed(2);
  }

  ["kg15", "kg45", "rate"].forEach(id => {
    document.getElementById(id).addEventListener("input", calculateAmounts);
  });

  function getFormValues() {
    return {
      date: document.getElementById("date").value,
      plantName: document.getElementById("plantName").value.trim(),
      kg15: Number(document.getElementById("kg15").value) || 0,
      kg45: Number(document.getElementById("kg45").value) || 0,
      rate: Number(document.getElementById("rate").value) || 0,
      baseRate: Number(document.getElementById("baseRate").value) || 0,
      cost15: Number(document.getElementById("cost15").value) || 0,
      cost45: Number(document.getElementById("cost45").value) || 0,
      amount15: Number(document.getElementById("amount15").value) || 0,
      amount45: Number(document.getElementById("amount45").value) || 0,
      totalAmount: Number(document.getElementById("totalAmount").value) || 0
    };
  }

  async function saveToServer(entry) {
    try {
      const res = await fetch("stock_save.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(entry)
      })

