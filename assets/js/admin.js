/**
 * LabMaster - Admin Dashboard & Result Entry Automation
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Mobile Sidebar Toggle
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.admin-sidebar');

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
      sidebar.classList.toggle('show');
    });

    // Close when clicking outside on mobile
    document.addEventListener('click', function (e) {
      if (window.innerWidth < 992 && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
        sidebar.classList.remove('show');
      }
    });
  }

  // 2. Clinical Result Automation & Range Validation
  const resultInputs = document.querySelectorAll('.param-result-input');

  function calculateFormulas() {
    // Bilirubin calculation
    const tbil = parseFloat(document.querySelector('[data-param-code="TBIL"]')?.value);
    const dbil = parseFloat(document.querySelector('[data-param-code="DBIL"]')?.value);
    const ibilInput = document.querySelector('[data-param-code="IBIL"]');
    if (ibilInput && !isNaN(tbil) && !isNaN(dbil)) {
      const ibil = Math.max(0, tbil - dbil).toFixed(2);
      ibilInput.value = ibil;
      evaluateInput(ibilInput);
    }

    // Protein, Albumin, Globulin, A/G Ratio
    const tp = parseFloat(document.querySelector('[data-param-code="TP"]')?.value);
    const alb = parseFloat(document.querySelector('[data-param-code="ALB"]')?.value);
    const globInput = document.querySelector('[data-param-code="GLOB"]');
    const agRatioInput = document.querySelector('[data-param-code="AGRATIO"]');

    if (globInput && !isNaN(tp) && !isNaN(alb)) {
      const glob = Math.max(0, tp - alb).toFixed(2);
      globInput.value = glob;
      evaluateInput(globInput);

      if (agRatioInput && parseFloat(glob) > 0) {
        agRatioInput.value = (alb / parseFloat(glob)).toFixed(2);
        evaluateInput(agRatioInput);
      }
    }

    // Urea & BUN
    const urea = parseFloat(document.querySelector('[data-param-code="UREA"]')?.value);
    const bunInput = document.querySelector('[data-param-code="BUN"]');
    if (bunInput && !isNaN(urea)) {
      bunInput.value = (urea / 2.14).toFixed(1);
      evaluateInput(bunInput);
    }

    // Lipid Profile: VLDL & Chol/HDL Ratio
    const trig = parseFloat(document.querySelector('[data-param-code="TRIG"]')?.value);
    const chol = parseFloat(document.querySelector('[data-param-code="CHOL"]')?.value);
    const hdl = parseFloat(document.querySelector('[data-param-code="HDL"]')?.value);
    const vldlInput = document.querySelector('[data-param-code="VLDL"]');
    const cholHdlInput = document.querySelector('[data-param-code="CHOLHDL"]');

    if (vldlInput && !isNaN(trig)) {
      vldlInput.value = (trig / 5).toFixed(1);
      evaluateInput(vldlInput);
    }
    if (cholHdlInput && !isNaN(chol) && !isNaN(hdl) && hdl > 0) {
      cholHdlInput.value = (chol / hdl).toFixed(2);
      evaluateInput(cholHdlInput);
    }

    // HbA1c & eAG
    const hba1c = parseFloat(document.querySelector('[data-param-code="HBA1C_VAL"]')?.value);
    const eagInput = document.querySelector('[data-param-code="EAG"]');
    if (eagInput && !isNaN(hba1c)) {
      eagInput.value = ((28.7 * hba1c) - 46.7).toFixed(1);
      evaluateInput(eagInput);
    }
  }

  function evaluateInput(input) {
    const valStr = input.value.trim();
    const flagSelect = document.getElementById('flag_' + input.dataset.paramId);
    const minVal = input.dataset.min ? parseFloat(input.dataset.min) : null;
    const maxVal = input.dataset.max ? parseFloat(input.dataset.max) : null;
    const critLow = input.dataset.critLow ? parseFloat(input.dataset.critLow) : null;
    const critHigh = input.dataset.critHigh ? parseFloat(input.dataset.critHigh) : null;

    if (!valStr || isNaN(valStr)) {
      input.classList.remove('is-abnormal');
      input.classList.remove('is-normal');
      return;
    }

    const val = parseFloat(valStr);
    let flag = 'NORMAL';

    // Check critical limits
    if (critLow !== null && val < critLow) {
      flag = 'CRITICAL_LOW';
    } else if (critHigh !== null && val > critHigh) {
      flag = 'CRITICAL_HIGH';
    } else if (minVal !== null && val < minVal) {
      flag = 'LOW';
    } else if (maxVal !== null && val > maxVal) {
      flag = 'HIGH';
    }

    if (flagSelect) {
      flagSelect.value = flag;
    }

    // Visual styles
    if (flag === 'NORMAL') {
      input.classList.remove('is-abnormal');
      input.classList.add('is-normal');
    } else {
      input.classList.remove('is-normal');
      input.classList.add('is-abnormal');
    }
  }

  resultInputs.forEach(input => {
    input.addEventListener('input', function () {
      evaluateInput(this);
      calculateFormulas();
    });

    // Run initial evaluation on page load
    evaluateInput(input);
  });

  // Calculate formulas initially
  calculateFormulas();
});
