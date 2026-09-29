<?php
/** EyeSense Cloud Portal — Register New Society */
require_once __DIR__ . '/portal_header.php';
?>

<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0">
        <h1 class="font-bold text-base md:text-lg text-textMain truncate">
            <i class="fas fa-building-circle-arrow-right text-primary mr-2"></i>Register New Society
        </h1>
        <p class="text-xs text-textSec hidden sm:block">Create a new society under your account and switch management anytime</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="dashboard.php" class="btn-secondary text-xs px-3 py-1.5 flex items-center gap-1.5 no-underline">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6">
    <div class="max-w-4xl mx-auto">
        <form id="societyForm" class="space-y-6">
            <!-- ── Society Basic Info ── -->
            <div class="glass-panel rounded-xl p-6 border border-border space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-border">
                    <i class="fas fa-city text-primary"></i>
                    <h3 class="text-sm font-bold text-textMain uppercase tracking-wider">Society Details</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Society Name *</label>
                        <input type="text" name="societyName" required
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition"
                            placeholder="e.g. Green Valley Residency">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Society Type *</label>
                        <select name="societyType" required
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition">
                            <option value="">Select society type</option>
                            <option value="RESIDENTIAL_SOCIETY">Residential Society</option>
                            <option value="APARTMENT">Apartment</option>
                            <option value="TOWNSHIP">Township</option>
                            <option value="BOYS_HOSTEL">Boys Hostel</option>
                            <option value="GIRLS_HOSTEL">Girls Hostel</option>
                            <option value="PG_ACCOMMODATION">PG Accommodation</option>
                            <option value="COMMERCIAL_COMPLEX">Commercial Complex</option>
                            <option value="OFFICE_BUILDING">Office Building</option>
                            <option value="INDUSTRIAL_AREA">Industrial Area</option>
                            <option value="SCHOOL">School</option>
                            <option value="COLLEGE">College</option>
                            <option value="HOSPITAL">Hospital</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Address *</label>
                        <textarea name="address" rows="2" required
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition resize-y"
                            placeholder="Street address, building number, locality"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Country *</label>
                        <select name="country" id="countrySelect" required
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition">
                            <option value="India" selected>India</option>
                            <option value="United States">United States</option>
                            <option value="United Arab Emirates">United Arab Emirates</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="Canada">Canada</option>
                            <option value="Australia">Australia</option>
                            <option value="Singapore">Singapore</option>
                            <option value="Germany">Germany</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">State / Region *</label>
                        <select name="state" id="stateSelect" required
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition">
                            <option value="">Select State / Region</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">City *</label>
                        <input type="text" name="city" required
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition"
                            placeholder="e.g. Bengaluru">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Pincode *</label>
                        <input type="text" name="pincode" id="pincodeInput" required maxlength="6" minlength="6"
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition"
                            placeholder="e.g. 560064">
                        <div id="pincodeError" class="text-[11px] text-rose-400 mt-1 hidden"></div>
                    </div>
                </div>
            </div>

            <!-- ── Contact Information ── -->
            <div class="glass-panel rounded-xl p-6 border border-border space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-border">
                    <i class="fas fa-id-card text-primary"></i>
                    <h3 class="text-sm font-bold text-textMain uppercase tracking-wider">Contact Person &amp; Administration</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Contact Person *</label>
                        <input type="text" name="contactPerson" required
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition"
                            placeholder="Full Name">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Mobile Number *</label>
                        <input type="tel" name="mobileNumber" id="mobileNumberInput" required maxlength="10" minlength="10"
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition"
                            placeholder="e.g. 9876543210">
                        <div id="mobileError" class="text-[11px] text-rose-400 mt-1 hidden"></div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Email Address *</label>
                        <input type="email" name="email" required
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition"
                            placeholder="admin@society.com">
                    </div>
                </div>
            </div>

            <!-- ── Society Capacity & Surveillance ── -->
            <div class="glass-panel rounded-xl p-6 border border-border space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-border">
                    <i class="fas fa-video text-primary"></i>
                    <h3 class="text-sm font-bold text-textMain uppercase tracking-wider">Capacity &amp; Infrastructure (Optional)</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Number Of Towers</label>
                        <input type="number" name="numberOfTowers" min="0"
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition"
                            placeholder="e.g. 4">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Number Of Flats</label>
                        <input type="number" name="numberOfFlats" min="0"
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition"
                            placeholder="e.g. 120">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-textSec mb-1.5">Number Of Cameras</label>
                        <input type="number" name="numberOfCameras" min="0"
                            class="w-full text-sm bg-surfaceLight border border-border rounded-lg px-3.5 py-2 text-textMain focus:border-primary outline-none transition"
                            placeholder="e.g. 16">
                    </div>
                </div>
            </div>

            <!-- ── Action & Feedback ── -->
            <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-4">
                <button type="submit" id="submitBtn"
                    class="btn-primary w-full sm:w-auto px-6 py-2.5 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-primary/20 hover:opacity-95 transition">
                    <i class="fas fa-circle-check"></i> Register Society
                </button>
                <div id="result" role="status" class="text-xs font-medium min-h-[1.5rem] flex items-center"></div>
            </div>
        </form>
    </div>
</div>

<script>
const countryStates = {
    "India": [
        "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat",
        "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh",
        "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab",
        "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh",
        "Uttarakhand", "West Bengal", "Andaman and Nicobar Islands", "Chandigarh",
        "Dadra and Nagar Haveli and Daman and Diu", "Delhi", "Jammu and Kashmir", "Ladakh",
        "Lakshadweep", "Puducherry"
    ],
    "United States": [
        "Alabama", "Alaska", "Arizona", "Arkansas", "California", "Colorado", "Connecticut",
        "Delaware", "District of Columbia", "Florida", "Georgia", "Hawaii", "Idaho", "Illinois",
        "Indiana", "Iowa", "Kansas", "Kentucky", "Louisiana", "Maine", "Maryland", "Massachusetts",
        "Michigan", "Minnesota", "Mississippi", "Missouri", "Montana", "Nebraska", "Nevada",
        "New Hampshire", "New Jersey", "New Mexico", "New York", "North Carolina", "North Dakota",
        "Ohio", "Oklahoma", "Oregon", "Pennsylvania", "Rhode Island", "South Carolina",
        "South Dakota", "Tennessee", "Texas", "Utah", "Vermont", "Virginia", "Washington",
        "West Virginia", "Wisconsin", "Wyoming"
    ],
    "United Arab Emirates": [
        "Abu Dhabi", "Ajman", "Dubai", "Fujairah", "Ras Al Khaimah", "Sharjah", "Umm Al Quwain"
    ],
    "United Kingdom": [
        "England", "Northern Ireland", "Scotland", "Wales"
    ],
    "Canada": [
        "Alberta", "British Columbia", "Manitoba", "New Brunswick", "Newfoundland and Labrador",
        "Northwest Territories", "Nova Scotia", "Nunavut", "Ontario", "Prince Edward Island",
        "Quebec", "Saskatchewan", "Yukon"
    ],
    "Australia": [
        "Australian Capital Territory", "New South Wales", "Northern Territory", "Queensland",
        "South Australia", "Tasmania", "Victoria", "Western Australia"
    ],
    "Singapore": [
        "Central Region", "East Region", "North Region", "North-East Region", "West Region"
    ],
    "Germany": [
        "Baden-Württemberg", "Bavaria", "Berlin", "Brandenburg", "Bremen", "Hamburg",
        "Hesse", "Lower Saxony", "Mecklenburg-Vorpommern", "North Rhine-Westphalia",
        "Rhineland-Palatinate", "Saarland", "Saxony", "Saxony-Anhalt", "Schleswig-Holstein", "Thuringia"
    ],
    "Other": [
        "Other / General Region"
    ]
};

const countrySelect = document.getElementById('countrySelect');
const stateSelect = document.getElementById('stateSelect');

function populateStates(country, selectedState = '') {
    if (!stateSelect) return;
    stateSelect.innerHTML = '<option value="">Select State / Region</option>';
    const states = countryStates[country] || ["Other / General Region"];
    states.forEach(st => {
        const opt = document.createElement('option');
        opt.value = st;
        opt.textContent = st;
        if (selectedState && st.toLowerCase() === selectedState.toLowerCase()) {
            opt.selected = true;
        }
        stateSelect.appendChild(opt);
    });
}

const pincodeInput = document.getElementById('pincodeInput');
const pincodeError = document.getElementById('pincodeError');

function configurePincodeRules() {
    if (!pincodeInput) return;
    const country = countrySelect ? countrySelect.value : 'India';
    if (country === 'India') {
        pincodeInput.maxLength = 6;
        pincodeInput.minLength = 6;
        pincodeInput.placeholder = 'e.g. 560064';
    } else if (country === 'United States') {
        pincodeInput.maxLength = 5;
        pincodeInput.minLength = 5;
        pincodeInput.placeholder = 'e.g. 90210';
    } else {
        pincodeInput.maxLength = 10;
        pincodeInput.minLength = 3;
        pincodeInput.placeholder = 'e.g. 10001';
    }
    if (pincodeInput.value) {
        validatePincode();
    }
}

function validatePincode() {
    if (!pincodeInput || !pincodeError) return true;
    const val = pincodeInput.value.trim();
    const country = countrySelect ? countrySelect.value : 'India';

    if (!val) {
        pincodeError.classList.add('hidden');
        pincodeInput.classList.remove('border-rose-500');
        return false;
    }

    if (country === 'India') {
        if (!/^\d{6}$/.test(val)) {
            pincodeError.textContent = 'Pincode must be exactly 6 digits for India';
            pincodeError.classList.remove('hidden');
            pincodeInput.classList.add('border-rose-500');
            return false;
        }
    } else if (country === 'United States') {
        if (!/^\d{5}$/.test(val)) {
            pincodeError.textContent = 'Zip code must be exactly 5 digits for USA';
            pincodeError.classList.remove('hidden');
            pincodeInput.classList.add('border-rose-500');
            return false;
        }
    } else {
        if (val.length < 3 || val.length > 10) {
            pincodeError.textContent = 'Postal code must be between 3 and 10 characters';
            pincodeError.classList.remove('hidden');
            pincodeInput.classList.add('border-rose-500');
            return false;
        }
    }

    pincodeError.classList.add('hidden');
    pincodeInput.classList.remove('border-rose-500');
    return true;
}

if (pincodeInput) {
    pincodeInput.addEventListener('input', (e) => {
        const country = countrySelect ? countrySelect.value : 'India';
        if (country === 'India' || country === 'United States') {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, e.target.maxLength);
        }
        validatePincode();
    });
    pincodeInput.addEventListener('blur', validatePincode);
}

const mobileNumberInput = document.getElementById('mobileNumberInput');
const mobileError = document.getElementById('mobileError');

function configureMobileRules() {
    if (!mobileNumberInput) return;
    const country = countrySelect ? countrySelect.value : 'India';
    if (country === 'India') {
        mobileNumberInput.maxLength = 10;
        mobileNumberInput.minLength = 10;
        mobileNumberInput.placeholder = 'e.g. 9876543210';
    } else if (country === 'United States' || country === 'Canada') {
        mobileNumberInput.maxLength = 10;
        mobileNumberInput.minLength = 10;
        mobileNumberInput.placeholder = 'e.g. 5551234567';
    } else {
        mobileNumberInput.maxLength = 15;
        mobileNumberInput.minLength = 7;
        mobileNumberInput.placeholder = 'e.g. 9876543210';
    }
    if (mobileNumberInput.value) {
        validateMobileNumber();
    }
}

function validateMobileNumber() {
    if (!mobileNumberInput || !mobileError) return true;
    const val = mobileNumberInput.value.trim();
    const country = countrySelect ? countrySelect.value : 'India';

    if (!val) {
        mobileError.classList.add('hidden');
        mobileNumberInput.classList.remove('border-rose-500');
        return false;
    }

    if (country === 'India') {
        if (!/^[6-9]\d{9}$/.test(val)) {
            mobileError.textContent = 'Mobile number must be exactly 10 digits starting with 6, 7, 8, or 9';
            mobileError.classList.remove('hidden');
            mobileNumberInput.classList.add('border-rose-500');
            return false;
        }
    } else if (country === 'United States' || country === 'Canada') {
        if (!/^\d{10}$/.test(val.replace(/\D/g, ''))) {
            mobileError.textContent = 'Mobile number must be exactly 10 digits';
            mobileError.classList.remove('hidden');
            mobileNumberInput.classList.add('border-rose-500');
            return false;
        }
    } else {
        if (val.length < 7 || val.length > 15) {
            mobileError.textContent = 'Mobile number must be between 7 and 15 digits';
            mobileError.classList.remove('hidden');
            mobileNumberInput.classList.add('border-rose-500');
            return false;
        }
    }

    mobileError.classList.add('hidden');
    mobileNumberInput.classList.remove('border-rose-500');
    return true;
}

if (mobileNumberInput) {
    mobileNumberInput.addEventListener('input', (e) => {
        const country = countrySelect ? countrySelect.value : 'India';
        if (country === 'India' || country === 'United States' || country === 'Canada') {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, e.target.maxLength);
        }
        validateMobileNumber();
    });
    mobileNumberInput.addEventListener('blur', validateMobileNumber);
}

if (countrySelect && stateSelect) {
    countrySelect.addEventListener('change', () => {
        populateStates(countrySelect.value);
        configurePincodeRules();
        configureMobileRules();
    });
    // Initial populate
    populateStates(countrySelect.value || 'India', 'Karnataka');
    configurePincodeRules();
    configureMobileRules();
}

const form = document.getElementById('societyForm');
const result = document.getElementById('result');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!validatePincode()) {
        result.style.color = '#ef4444';
        result.textContent = 'Please provide a valid pincode.';
        if (pincodeInput) pincodeInput.focus();
        return;
    }

    if (!validateMobileNumber()) {
        result.style.color = '#ef4444';
        result.textContent = 'Please provide a valid mobile number.';
        if (mobileNumberInput) mobileNumberInput.focus();
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
    result.style.color = '#fbbf24';
    result.textContent = 'Creating society and provisioning administrator account...';

    try {
        const response = await fetch('../society/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.fromEntries(new FormData(form)))
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Registration failed.');
        }

        result.style.color = '#10b981';
        result.textContent = `${data.message} Code: ${data.societyCode}. Redirecting to dashboard...`;
        setTimeout(() => {
            window.location.href = 'dashboard.php';
        }, 1200);
    } catch (error) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-circle-check"></i> Register Society';
        result.style.color = '#ef4444';
        result.textContent = error.message;
    }
});
</script>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
