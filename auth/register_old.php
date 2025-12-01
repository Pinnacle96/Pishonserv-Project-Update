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

        <input type="text" name="name" placeholder="First Name" required autocomplete="given-name"
               class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
               value="<?php echo isset($_SESSION['form_data']['name']) ? htmlspecialchars($_SESSION['form_data']['name']) : ''; ?>">

        <input type="text" name="lname" placeholder="Last Name" required autocomplete="family-name"
               class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
               value="<?php echo isset($_SESSION['form_data']['lname']) ? htmlspecialchars($_SESSION['form_data']['lname']) : ''; ?>">

        <input type="email" name="email" placeholder="Email" required autocomplete="email"
               class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
               value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>">

        <input type="text" name="phone" placeholder="Phone Number" required autocomplete="tel"
               class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
               value="<?php echo isset($_SESSION['form_data']['phone']) ? htmlspecialchars($_SESSION['form_data']['phone']) : ''; ?>">

        <input type="text" name="address" placeholder="Street Address" required autocomplete="street-address"
               class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
               value="<?php echo isset($_SESSION['form_data']['address']) ? htmlspecialchars($_SESSION['form_data']['address']) : ''; ?>">

        <!-- 🔧 fixed value keys for state & city -->
        <input type="text" name="state" placeholder="State" required
               class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
               value="<?php echo isset($_SESSION['form_data']['state']) ? htmlspecialchars($_SESSION['form_data']['state']) : ''; ?>">

        <input type="text" name="city" placeholder="City" required
               class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
               value="<?php echo isset($_SESSION['form_data']['city']) ? htmlspecialchars($_SESSION['form_data']['city']) : ''; ?>">

        <input type="password" name="nin" id="nin" placeholder="National Identification Number (NIN)"
               maxlength="11" required pattern="[0-9]{11}" title="NIN must be exactly 11 digits"
               class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]"
               value="<?php echo isset($_SESSION['form_data']['nin']) ? htmlspecialchars($_SESSION['form_data']['nin']) : ''; ?>">

        <input type="password" name="password" placeholder="Password" required autocomplete="new-password"
               class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]">

        <select name="role" id="role" required
                class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]">
            <option value="">Select Role</option>
            <option value="buyer"        <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='buyer') ? 'selected' : ''; ?>>Customer</option>
            <option value="agent"        <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='agent') ? 'selected' : ''; ?>>Agent</option>
            <option value="owner"        <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='owner') ? 'selected' : ''; ?>>Property Owner</option>
            <option value="hotel_owner"  <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='hotel_owner') ? 'selected' : ''; ?>>Hotel Owner</option>
            <option value="developer"    <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role']=='developer') ? 'selected' : ''; ?>>Developer</option>
        </select>

        <div class="md:col-span-2">
            <label for="profile_image" class="block font-semibold">Profile Picture:</label>
            <!-- accept only images client-side -->
            <input type="file" name="profile_image" id="profile_image" accept="image/*"
                   class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]">
        </div>

        <?php
        /* list now includes developer so PHP & JS match */
        $needMou = isset($_SESSION['form_data']['role']) && in_array($_SESSION['form_data']['role'], ['agent','owner','hotel_owner','developer']);
        ?>
        <div id="mou-section"
             class="md:col-span-2 mt-4 <?php echo $needMou ? '' : 'hidden'; ?>">
            <label class="block font-semibold mb-2 text-[#092468]">Memorandum of Understanding (MOU)</label>
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

            <div class="mt-3">
                <input type="checkbox" name="agree_mou" id="mou_agree"
                       <?php echo isset($_SESSION['form_data']['agree_mou']) ? 'checked' : ''; ?>>
                <label for="mou_agree" class="text-sm text-gray-700">I have read and agree to the MOU</label>
            </div>
            <input type="hidden" name="signed_name" id="mou_fullname">
        </div>

        <!-- ✅ Google reCAPTCHA widget -->
        <div class="md:col-span-2">
            <div class="g-recaptcha" data-sitekey="6LesimcrAAAAAMv3Pfia3kRlrNve1bpneDasdL1o"></div>
        </div>

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
    const rolesNeedingMou = ['agent','owner','hotel_owner','developer'];
    const need = rolesNeedingMou.includes(e.target.value);
    mouSection.classList.toggle('hidden', !need);
    agreeMou.required = need;
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
