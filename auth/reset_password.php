<?php
session_start();

// must come from verified reset flow
if (empty($_SESSION['password_reset_mode']) || empty($_SESSION['user_id_reset'])) {
    $_SESSION['error'] = 'Your reset session has expired. Please start again.';
    header('Location: forgot_password.php');
    exit();
}

// CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<?php if (isset($_SESSION['success'])): ?>
<script>Swal.fire({icon:'success',title:'Success',text:'<?= addslashes($_SESSION['success']); ?>',confirmButtonColor:'#092468'});</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>Swal.fire({icon:'error',title:'Error',text:'<?= addslashes($_SESSION['error']); ?>',confirmButtonColor:'#092468'});</script>
<?php unset($_SESSION['error']); endif; ?>

<div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
    <h2 class="text-3xl font-bold text-center text-[#092468]">Reset Your Password</h2>
    <p class="text-gray-600 text-center mt-2">Enter a new password to secure your account.</p>

    <form action="../process/reset_password_process.php" method="POST" class="mt-6 space-y-4" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="relative">
            <input
                type="password"
                name="password"
                id="password"
                required
                minlength="8"
                class="w-full p-3 border rounded-lg pl-10 focus:ring-2 focus:ring-[#092468]"
                placeholder="New password">
            <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
            <p class="text-xs text-gray-500 mt-1">Use 8+ characters with a mix of letters and numbers.</p>
        </div>

        <div class="relative">
            <input
                type="password"
                name="password_confirm"
                id="password_confirm"
                required
                minlength="8"
                class="w-full p-3 border rounded-lg pl-10 focus:ring-2 focus:ring-[#092468]"
                placeholder="Confirm new password">
            <i class="fas fa-check absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" id="show_pw" class="h-4 w-4">
            <label for="show_pw" class="text-sm text-gray-600">Show passwords</label>
        </div>

        <button type="submit"
            class="w-full bg-[#F4A124] text-white py-3 rounded-lg font-bold hover:bg-[#d88b1c] transition">
            Save New Password
        </button>
    </form>

    <p class="text-center text-gray-600 mt-4">
        Remember your password?
        <a href="login.php" class="text-blue-500 font-semibold">Login</a>
    </p>
</div>

<script>
const pw = document.getElementById('password');
const pw2 = document.getElementById('password_confirm');
const toggle = document.getElementById('show_pw');

toggle.addEventListener('change', () => {
    const type = toggle.checked ? 'text' : 'password';
    pw.type = type; pw2.type = type;
});

// Basic client-side check
document.querySelector('form').addEventListener('submit', (e) => {
    if (pw.value.length < 8) {
        e.preventDefault();
        Swal.fire({icon:'error', title:'Weak password', text:'Password must be at least 8 characters.'});
        return;
    }
    if (pw.value !== pw2.value) {
        e.preventDefault();
        Swal.fire({icon:'error', title:'Mismatch', text:'Passwords do not match.'});
    }
});
</script>
</body>
</html>
