<?php
/* ----------  BOOTSTRAP  ---------- */
session_start();
include '../includes/db_connect.php';

/* ----- CSRF token (creates once per session) ----- */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register - PISHONSERV</title>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- ✅ Tailwind CDN must be a <script>, not <link> -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>public/images/favicon.png">
</head>

<body class="bg-gray-100">
<?php include '../includes/navbar.php'; ?>

<div class="container mx-auto px-4 py-40">
    <h2 class="text-3xl font-bold text-center text-[#092468]">Register</h2>

    <form action="../process/register_process.php"
          method="POST"
          enctype="multipart/form-data"
          class="max-w-4xl mx-auto mt-6 bg-white p-8 rounded-lg shadow-lg grid grid-cols-1 md:grid-cols-2 gap-6"
          id="registerForm"
          novalidate>

        <!-- —— CSRF hidden field —— -->
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"/>

        <!-- First Name -->
        <div>
            <label for="name" class="block mb-1 font-medium text-gray-700">
                First Name <span class="text-red-500">*</span>
            </label>
            <input type="text" name="name" id="name" required autocomplete="given-name"
                   class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
                   value="<?php echo isset($_SESSION['form_data']['name']) ? htmlspecialchars($_SESSION['form_data']['name']) : ''; ?>">
        </div>

        <!-- Last Name -->
        <div>
            <label for="lname" class="block mb-1 font-medium text-gray-700">
                Last Name <span class="text-red-500">*</span>
            </label>
            <input type="text" name="lname" id="lname" required autocomplete="family-name"
                   class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
                   value="<?php echo isset($_SESSION['form_data']['lname']) ? htmlspecialchars($_SESSION['form_data']['lname']) : ''; ?>">
        </div>

        <!-- Email -->
        <div>
            <label for="email" class="block mb-1 font-medium text-gray-700">
                Email <span class="text-red-500">*</span>
            </label>
            <input type="email" name="email" id="email" required autocomplete="email"
                   class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
                   value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>">
        </div>

        <!-- Phone -->
        <div>
            <label for="phone" class="block mb-1 font-medium text-gray-700">
                Phone Number <span class="text-red-500">*</span>
            </label>
            <input type="text" name="phone" id="phone" required autocomplete="tel"
                   class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
                   value="<?php echo isset($_SESSION['form_data']['phone']) ? htmlspecialchars($_SESSION['form_data']['phone']) : ''; ?>">
        </div>

        <!-- Address -->
        <div>
            <label for="address" class="block mb-1 font-medium text-gray-700">
                Street Address <span class="text-red-500">*</span>
            </label>
            <input type="text" name="address" id="address" required autocomplete="street-address"
                   class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
                   value="<?php echo isset($_SESSION['form_data']['address']) ? htmlspecialchars($_SESSION['form_data']['address']) : ''; ?>">
        </div>

        <!-- State -->
        <div>
            <label for="state" class="block mb-1 font-medium text-gray-700">
                State <span class="text-red-500">*</span>
            </label>
            <input type="text" name="state" id="state" required
                   class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
                   value="<?php echo isset($_SESSION['form_data']['state']) ? htmlspecialchars($_SESSION['form_data']['state']) : ''; ?>">
        </div>

        <!-- City -->
        <div>
            <label for="city" class="block mb-1 font-medium text-gray-700">
                City <span class="text-red-500">*</span>
            </label>
            <input type="text" name="city" id="city" required
                   class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
                   value="<?php echo isset($_SESSION['form_data']['city']) ? htmlspecialchars($_SESSION['form_data']['city']) : ''; ?>">
        </div>

        <!-- NIN -->
       <!-- NIN (Optional now) -->
<div>
    <label for="nin" class="block mb-1 font-medium text-gray-700">
        National Identification Number (NIN) <span class="text-xs text-gray-400 font-normal">(optional)</span>
    </label>
    <input type="password" name="nin" id="nin"
           maxlength="11" pattern="[0-9]{11}" title="NIN must be exactly 11 digits"
           class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
           value="<?php echo isset($_SESSION['form_data']['nin']) ? htmlspecialchars($_SESSION['form_data']['nin']) : ''; ?>">
    <p class="text-xs text-gray-500 mt-1">If provided, must be exactly 11 digits.</p>
</div>

        <!-- Password -->
        <div>
            <label for="password" class="block mb-1 font-medium text-gray-700">
                Password <span class="text-red-500">*</span>
            </label>
            <input type="password" name="password" id="password" required autocomplete="new-password"
                   class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]">
        </div>

       <!-- Role -->
<div>
    <label for="role" class="block mb-1 font-medium text-gray-700">
        Select Role <span class="text-red-500">*</span>
    </label>
    <select name="role" id="role" required
            class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]">
        <option value="">Select Role</option>
        <option value="buyer"        <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='buyer') ? 'selected' : ''; ?>>Customer</option>
        <option value="agent"        <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='agent') ? 'selected' : ''; ?>>Agent</option>
        <option value="owner"        <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='owner') ? 'selected' : ''; ?>>Property Owner</option>
        <option value="hotel_owner"  <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='hotel_owner') ? 'selected' : ''; ?>>Hotel Owner</option>
        <option value="developer"    <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='developer') ? 'selected' : ''; ?>>Developer</option>
        <option value="host"         <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='host') ? 'selected' : ''; ?>>Host</option>
    </select>
</div>

        <!-- Profile Picture (Optional) -->
        <div class="md:col-span-2">
            <label for="profile_image" class="block mb-1 font-medium text-gray-700">
                Profile Picture <span class="text-xs text-gray-400 font-normal">(optional)</span>
            </label>
            <input type="file" name="profile_image" id="profile_image" accept="image/*"
                   class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]">
        </div>

        <?php
        /* list now includes developer so PHP & JS match */
        $needMou = isset($_SESSION['form_data']['role']) && in_array($_SESSION['form_data']['role'], ['agent','owner','hotel_owner','developer']);
        ?>
        <!-- MOU Section (conditionally required) -->
        <div id="mou-section"
             class="md:col-span-2 mt-4 <?php echo $needMou ? '' : 'hidden'; ?>">
            <label class="block font-semibold mb-2 text-[#092468]">
                Memorandum of Understanding (MOU)
            </label>
            <div class="h-48 overflow-y-scroll border p-3 text-sm bg-gray-50 rounded">
                <p>By registering as an <strong>Agent</strong>, <strong>Property Owner</strong>,
                   <strong>Hotel Owner</strong> or <strong>Developer</strong> on
                   <strong>PISHONSERV</strong>, you agree to abide by our terms&nbsp;&amp;&nbsp;conditions.</p>
                <p>This MOU outlines your responsibility to provide accurate property details and
                   maintain the highest standards of honesty, transparency, and integrity.</p>
                <p>Failure to comply may result in account suspension or termination.</p>
            </div>
            <a href="../documents/pishonserv_mou_sample.pdf" target="_blank"
               class="inline-block mt-3 bg-[#092468] text-white px-4 py-2 rounded hover:bg-[#051B47] text-sm">
               View Full MOU Document
            </a>

            <div class="mt-3 flex items-center gap-2">
                <input type="checkbox" name="agree_mou" id="mou_agree" class="h-4 w-4"
                       <?php echo isset($_SESSION['form_data']['agree_mou']) ? 'checked' : ''; ?>>
                <label for="mou_agree" class="text-sm text-gray-700">
                    I have read and agree to the MOU
                    <span id="mou-required-star" class="text-red-500 <?php echo $needMou ? '' : 'hidden'; ?>">*</span>
                </label>
            </div>

            <input type="hidden" name="signed_name" id="mou_fullname">
        </div>

        <!-- ✅ Google reCAPTCHA widget -->
        <div class="md:col-span-2">
            <label class="block mb-1 font-medium text-gray-700">reCAPTCHA</label>
            <div class="g-recaptcha" data-sitekey="6LesimcrAAAAAMv3Pfia3kRlrNve1bpneDasdL1o"></div>
        </div>

        <!-- Submit -->
        <div class="md:col-span-2">
            <button type="submit"
                    class="w-full bg-[#CC9933] text-white py-3 rounded hover:bg-[#d88b1c] mt-4">
                Register
            </button>
            <p class="text-center text-gray-600 mt-2">
                Already have an account?
                <a href="login.php" class="text-blue-500 font-semibold">Sign in</a>
            </p>
        </div>
    </form>
</div>

<!-- ——— Flash messages via SweetAlert2 ——— -->
<?php if (isset($_SESSION['error'])): ?>
<script>
Swal.fire({icon:'error', title:'Error!', text:'<?php echo addslashes($_SESSION['error']); ?>',
           confirmButtonColor:'#092468'});
</script>
<?php unset($_SESSION['error']); endif; ?>

<?php if (isset($_SESSION['success'])): ?>
<script>
Swal.fire({icon:'success', title:'Success!', text:'<?php echo addslashes($_SESSION['success']); ?>',
           confirmButtonColor:'#092468'});
</script>
<?php unset($_SESSION['success']); endif; ?>

<script>
/* ——— Dynamic MOU show/hide ——— */
document.getElementById('role').addEventListener('change', e => {
    const mouSection = document.getElementById('mou-section');
    const agreeMou   = document.getElementById('mou_agree');
    const mouStar    = document.getElementById('mou-required-star');
    const rolesNeedingMou = ['agent','owner','hotel_owner','developer'];
    const need = rolesNeedingMou.includes(e.target.value);
    mouSection.classList.toggle('hidden', !need);
    agreeMou.required = need;                  // make checkbox required only when needed
    mouStar.classList.toggle('hidden', !need); // show red * on label when required
});

/* ——— Populate full name for MOU signature ——— */
document.getElementById('registerForm').addEventListener('submit', () => {
    const f = document.querySelector('[name="name"]').value.trim();
    const l = document.querySelector('[name="lname"]').value.trim();
    document.getElementById('mou_fullname').value = `${f} ${l}`;
});

/* ——— NIN mask ——— */
document.getElementById('nin').addEventListener('input', e => {
    e.target.value = e.target.value.replace(/[^0-9]/g,'').slice(0,11);
});

/* ——— ensure correct initial state ——— */
document.getElementById('role').dispatchEvent(new Event('change'));
</script>

<?php include '../includes/footer.php'; ?>
<?php unset($_SESSION['form_data']); ?>
</body>
</html>
