const MAX_CAPACITY = 20;
const MAX_HOURS = 12;

// CONSTANT RATES (from script.js)
const BASE_RATE = 50; // PHP 50.00
const MIN_FEE = 75; // PHP 75.00
const EQUIP_RATE = 150; // PHP 150.00
let reservations = []; // Used for client-side conflict simulation

// --- New Function: Toggle Student ID Visibility ---
/**
 * Hides the Student ID field if the reservation purpose is 'Gathering'
 * and manages the client-side 'required' attribute.
 */
function toggleStudentIdField() {
  const purposeSelect = document.getElementById("reservationPurpose");
  const studentIdInput = document.getElementById("studentId");
  const studentIdGroup = document.getElementById("studentIdGroup");

  if (!purposeSelect || !studentIdGroup || !studentIdInput) return;

  if (purposeSelect.value === "Gathering") {
    studentIdGroup.classList.add("hidden"); // hide without affecting layout
    studentIdInput.removeAttribute("required");
  } else {
    studentIdGroup.classList.remove("hidden"); // show field
    if (purposeSelect.value === "Study") {
      // In HTML, the input is required by default; this ensures the JS reinforces it
      studentIdInput.setAttribute("required", "required");
    } else {
      studentIdInput.removeAttribute("required");
    }
  }
}

// --- GOOGLE MAPS INITIALIZATION (Placeholder) ---
function initMap() {
  // This will only work if a valid Google Maps API Key replaces 'YOUR_API_KEY_HERE'
  const mapElement = document.getElementById("map");
  if (!mapElement) return;

  const batstate = { lat: 14.044880552072451, lng: 121.15529643335249 };
  const map = new google.maps.Map(mapElement, {
    center: batstate,
    zoom: 17,
  });

  const marker = new google.maps.Marker({
    position: batstate,
    map,
    title: "Batangas State University",
  });

  const info = new google.maps.InfoWindow({
    content: "<strong>Batangas State University</strong><br>Malvar, Batangas",
  });

  marker.addListener("click", () => info.open(map, marker));
}

// --- Phone Number Input Formatting/Validation ---
const phoneInput = document.getElementById("phoneNumber");

if (phoneInput) {
  phoneInput.addEventListener("input", function () {
    // Remove non-digit characters
    let cleaned = this.value.replace(/\D/g, "");

    // Limit to 11 digits max
    if (cleaned.length > 11) cleaned = cleaned.slice(0, 11);

    // Always start with 09
    if (!cleaned.startsWith("09") && cleaned.length >= 2) {
      // Keep first digit if it exists, replace next with 9
      cleaned = "09" + cleaned.slice(2);
    } else if (!cleaned.startsWith("09") && cleaned.length < 2) {
      // Ensure it's at least '09' if the user types anything other than 0 or 09
      cleaned = cleaned.startsWith("0") ? cleaned : "";
    } else if (cleaned.length === 1) {
      // Only one digit typed, check if it's 0
      cleaned = cleaned === "0" ? cleaned : "";
    }

    this.value = cleaned;
  });
}

// --- PRICE CALCULATION ---
function calculateEstimate() {
  const hours = parseInt(document.getElementById("numHours")?.value) || 0;
  const persons = parseInt(document.getElementById("numPersons")?.value) || 0;
  const projector = document.getElementById("projector")?.checked || false;
  const speaker = document.getElementById("speakerMic")?.checked || false;

  const totalFeeElement = document.getElementById("total-fee");

  if (!totalFeeElement) {
    return;
  }

  if (hours <= 0 || persons <= 0) {
    totalFeeElement.textContent = "PHP 0.00";
    return;
  }

  let total = BASE_RATE * hours * persons; // PHP 50/hour/person // 1. Minimum Fee Rule (Original)

  if (hours < 2) {
    total += MIN_FEE; // Adds PHP 75.00 if less than 2 hours
  } // 2. NEW SURCHARGE LOGIC

  if (hours > 2) {
    total += 50; // Adds PHP 50.00 if more than 2 hours
  } // 3. Equipment Fee (Original)

  if (projector) total += EQUIP_RATE * hours; // PHP 150/hour
  if (speaker) total += EQUIP_RATE * hours; // PHP 150/hour

  totalFeeElement.textContent = "PHP " + total.toFixed(2);
}

// --- MODAL HANDLING ---
function showModal(message, isError = false) {
  const priceModal = document.getElementById("priceModal");
  if (!priceModal) return;

  const modalContent = priceModal.querySelector(".modal-content");

  // Reset classes
  modalContent.classList.remove("message-error", "message-confirm");

  if (isError) {
    modalContent.classList.add("message-error");
    document.getElementById(
      "modalMessage"
    ).innerHTML = `<h2>Error</h2><p>${message}</p>`;
  } else {
    modalContent.classList.add("message-confirm");
    document.getElementById("modalMessage").innerHTML = message;
  }

  priceModal.style.display = "flex";
}

document.getElementById("closeModal")?.addEventListener("click", function () {
  const priceModal = document.getElementById("priceModal");
  if (priceModal) priceModal.style.display = "none";
});

// --- SUMMARY UPDATE FUNCTION (Added for static HTML functionality) ---
function updateSummary() {
  const dateInput = document.getElementById("reservationDate")?.value;
  const timeInput = document.getElementById("startTime")?.value;
  const hoursInput = document.getElementById("numHours")?.value;
  const personsInput = document.getElementById("numPersons")?.value;
  const purposeSelect = document.getElementById("reservationPurpose");
  const purposeText = purposeSelect.selectedOptions[0]?.text || "---";
  const projectorChecked = document.getElementById("projector")?.checked;
  const speakerChecked = document.getElementById("speakerMic")?.checked;

  document.getElementById("summary-date").textContent = dateInput || "---";
  document.getElementById("summary-time").textContent = timeInput || "---";
  document.getElementById("summary-hours").textContent = hoursInput
    ? `${hoursInput} hour(s)`
    : "---";
  document.getElementById("summary-persons").textContent =
    personsInput || "---";
  document.getElementById("summary-purpose").textContent = purposeText;

  let equipmentList = [];
  if (projectorChecked) equipmentList.push("Projector");
  if (speakerChecked) equipmentList.push("Speaker/Mic");
  document.getElementById("summary-equipment").textContent =
    equipmentList.length > 0 ? equipmentList.join(", ") : "None";
}

// --- ROOM AVAILABILITY CHECK (Client-side simulation) ---
function checkConflict(date, time, hours, persons, purpose) {
  // NOTE: This client-side check is primarily for demonstration/UX.
  // The PHP script's check against a database is the authoritative one.
  let startTime = new Date(`${date} ${time}`);
  let endTime = new Date(startTime.getTime() + hours * 60 * 60 * 1000);

  let existingPersonsInOverlap = 0;

  for (let r of reservations) {
    let rStart = new Date(`${r.date} ${r.time}`);
    let rEnd = new Date(rStart.getTime() + r.hours * 60 * 60 * 1000);

    let overlap = startTime < rEnd && endTime > rStart;

    if (overlap) {
      if (r.purpose === "Gathering") {
        return "BLOCKED_EVENT";
      }
      if (purpose === "Gathering") {
        return "BLOCKED_BY_STUDY";
      }

      existingPersonsInOverlap += r.persons;
    }
  }

  let totalPersons = existingPersonsInOverlap + persons;

  if (totalPersons > MAX_CAPACITY) {
    let remaining = MAX_CAPACITY - existingPersonsInOverlap;
    if (remaining < 0) remaining = 0;
    return `ONLY_${remaining}_LEFT`;
  }

  return "OK";
}

// --- OPERATING HOURS VALIDATION ---
function checkTimeRange(date, time, hours) {
  // Uses 24-hour clock
  const ONE_AM_NEXT_DAY = 25; // Represents 01:00 on the 25-hour clock (next day)
  const ONE_PM_CURRENT_DAY = 13 ; // Represents 13:00

  // The client side Date object uses local time, which can lead to timezone issues
  // The PHP server used a specific timezone 'Asia/Manila' and 'T' format for robustness.
  const reservationDateTime = new Date(`${date}T${time}:00`);
  const now = new Date();

  if (isNaN(reservationDateTime.getTime())) {
    // Handle invalid date/time format
    return false;
  }

  if (reservationDateTime < now) {
    return false;
  }

  const [startHourStr, startMinuteStr] = time.split(":");
  const startHour = parseInt(startHourStr);
  const startMinute = parseInt(startMinuteStr);

  const startTimeHours = startHour + startMinute / 60;
  const endTimeHours = startTimeHours + hours;

  // NOTE: This client-side check works on a single-day 24h clock basis for simplicity.
  // The PHP's check is more robust across midnight.

  if (startHour < ONE_PM_CURRENT_DAY && startHour >= 1) {
    // Cannot start between 1:00 AM and 12:59 PM (excluding 1 AM start time if reservation is for next day)
    return false;
  }

  // Check if reservation is for the next day's 1:00 AM
  const isNextDayOneAM =
    startHour === 1 &&
    startMinute === 0 &&
    reservationDateTime.getDate() !== now.getDate();

  if (startTimeHours < ONE_PM_CURRENT_DAY && !isNextDayOneAM) {
    return false;
  }

  // Simplified check for ending time on a single 24-hour day for client-side demo
  // 1:00 AM on next day is 25:00 on a single day scale (or 1:00 AM after midnight)
  if (endTimeHours > ONE_AM_NEXT_DAY) {
    return false;
  }

  return true;
}

/********************************************
 * SUBMIT RESERVATION
 ********************************************/
function submitReservation(e) {
  e.preventDefault(); // Prevent default form submission to the missing PHP server

  const fullName = document.getElementById("fullName")?.value.trim();
  const email = document.getElementById("email")?.value.trim();
  const phoneNumber = document.getElementById("phoneNumber")?.value.trim();
  const date = document.getElementById("reservationDate")?.value;
  const time = document.getElementById("startTime")?.value;
  const hours = parseInt(document.getElementById("numHours")?.value);
  const persons = parseInt(document.getElementById("numPersons")?.value);
  const purposeElement = document.getElementById("reservationPurpose");
  const purpose = purposeElement?.value;
  const purposeText = purposeElement?.selectedOptions[0]?.text;
  const totalFee = document.getElementById("total-fee")?.textContent;

  if (
    !fullName ||
    !email ||
    !date ||
    !time ||
    !hours ||
    !persons ||
    !purpose ||
    !phoneNumber
  ) {
    showModal("❗ Please fill in all required fields.", true);
    return;
  }

  const studentIdInput = document.getElementById("studentId");
  if (
    purpose === "Study" &&
    studentIdInput &&
    studentIdInput.value.trim() === ""
  ) {
    showModal("❗ Student ID is required for Study purposes.", true);
    return;
  }

  if (persons > MAX_CAPACITY) {
    showModal(`❗ Maximum allowed persons is ${MAX_CAPACITY}.`, true);
    return;
  }

  if (hours > MAX_HOURS) {
    showModal(`❗ Maximum allowed hours is ${MAX_HOURS}.`, true);
    return;
  }

  if (phoneNumber.length !== 11 || !phoneNumber.startsWith("09")) {
    showModal(
      "❗ Phone Number Error: Please enter a valid 11-digit mobile number starting with '09' (e.g., 09xxxxxxxxx).",
      true
    );
    return;
  }

  if (!checkTimeRange(date, time, hours)) {
    showModal(
      "❗ The requested time slot is outside the cafe's operating hours (1:00 PM - 1:00 AM next day). Reservation must be for a future time.",
      true
    );
    return;
  }

  let conflict = checkConflict(date, time, hours, persons, purpose);

  if (conflict === "BLOCKED_EVENT") {
    showModal(
      "❗ This time is already reserved for an <b>event</b>. No bookings allowed.",
      true
    );
    return;
  }
  if (conflict === "BLOCKED_BY_STUDY") {
    showModal("❗ A study booking exists. <b>Events</b> cannot overlap.", true);
    return;
  }
  if (conflict.startsWith("ONLY_")) {
    let left = conflict.split("_")[1];
    showModal(
      `❗ Only <b>${left}</b> person slots are available for this schedule.`,
      true
    );
    return;
  }

  // **********************************************
  // SUCCESS (Simulate database saving)
  // **********************************************
  reservations.push({
    fullName,
    date,
    time,
    hours,
    persons,
    purpose,
  });

  // FIX: SIMPLIFIED MESSAGE FOR MODAL POPUP
  const message = `
        <p>Hello <strong>${fullName}</strong>,</p>
        <p>Your <strong>${purposeText}</strong> reservation on <strong>${date}</strong> at <strong>${time}</strong> was successfully received.</p>
        <div style="border-top: 1px dashed #C9B8A5; padding-top: 10px; margin-top: 15px;">
            <p style="font-size: 1.1rem; display: flex; justify-content: space-between; font-weight: bold;">
                <span>Total Estimated Fee:</span> 
                <span style="color: var(--color-accent);">${totalFee}</span>
            </p>
        </div>
        <p style="margin-top: 20px;">Our staff will contact you for confirmation at <strong>${phoneNumber}</strong>.</p>
    `;

  showModal(message);

  document.getElementById("reservation-form")?.reset();
  calculateEstimate();
  toggleStudentIdField(); // Reset field visibility after form reset
  updateSummary(); // Clear/reset summary
}

// --- INITIALIZATION ---
document.addEventListener("DOMContentLoaded", () => {
  // --- 1. HAMBURGER MENU TOGGLE ---
  const menuToggle = document.querySelector(".menu-toggle");
  const navLinks = document.getElementById("main-nav");

  if (menuToggle && navLinks) {
    menuToggle.addEventListener("click", () => {
      navLinks.classList.toggle("is-open");
      const isExpanded =
        menuToggle.getAttribute("aria-expanded") === "true" || false;
      menuToggle.setAttribute("aria-expanded", !isExpanded);
    });
  }

  // --- Toggle Student ID Field Logic ---
  const purposeSelect = document.getElementById("reservationPurpose");
  if (purposeSelect) {
    purposeSelect.addEventListener("change", toggleStudentIdField);
    purposeSelect.addEventListener("change", updateSummary);
  }

  // --- Event Listeners for Estimate and Summary ---
  const elementsToListen = [
    "numHours",
    "numPersons",
    "projector",
    "speakerMic",
    "reservationDate",
    "startTime",
  ];
  elementsToListen.forEach((id) => {
    const el = document.getElementById(id);
    if (el) {
      // Use 'input' for number/date/time and 'change' for checkboxes/selects
      const eventType =
        id === "projector" || id === "speakerMic" ? "change" : "input";
      el.addEventListener(eventType, () => {
        calculateEstimate();
        updateSummary();
      });
    }
  });

  // Initial calls
  toggleStudentIdField();
  calculateEstimate();
  updateSummary(); // Populate summary with initial default values
});

// Expose functions globally (just in case they are needed for old-style event handlers)
window.submitReservation = submitReservation;
window.calculateEstimate = calculateEstimate;
window.toggleStudentIdField = toggleStudentIdField;
