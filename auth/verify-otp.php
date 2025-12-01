<?php
session_start();

// Require a user context created by registration, forgot password, or secure actions
if (!isset($_SESSION['user_id_to_verify'])) {
    $_SESSION['error'] = "You must start from registration, forgot password, or a secure action.";
    header("Location: login.php");
    exit();
}

// detect flow
$isReset = (isset($_GET['mode']) && $_GET['mode'] === 'reset') || !empty($_SESSION['password_reset_mode']);
$isWithdraw = (isset($_GET['mode']) && $_GET['mode'] === 'withdraw') || !empty($_SESSION['withdraw_otp_mode']);

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// helper to mask email a bit (name@do****.com)
function mask_email($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '';
    [$local, $domain] = explode('@', $email);
    $domainParts = explode('.', $domain);
    $maskedDomain = $domainParts[0];
    if (strlen($maskedDomain) > 2) {
        $maskedDomain = substr($maskedDomain, 0, 2) . str_repeat('*', max(0, strlen($maskedDomain)-2));
    }
    $domainParts[0] = $maskedDomain;
    $maskedLocal = strlen($local) <= 2 ? $local : substr($local, 0, 2) . str_repeat('*', strlen($local)-2);
    return $maskedLocal . '@' . implode('.', $domainParts);
}

$toEmail = $_SESSION['user_email_to_verify'] ?? '';
$masked = $toEmail ? mask_email($toEmail) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= $isReset ? 'Reset Password - Verify OTP' : ($isWithdraw ? 'Verify Withdrawal - OTP' : 'Verify Your Account - OTP') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

    <?php if (isset($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?= addslashes($_SESSION['success']); ?>',
            confirmButtonColor: '#092468'
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: '<?= addslashes($_SESSION['error']); ?>',
            confirmButtonColor: '#092468'
        });
    </script>
    <?php unset($_SESSION['error']); endif; ?>

    <main class="flex-grow flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
            <h2 class="text-2xl font-bold text-center text-[#092468] mb-2">
                <?= $isReset ? 'Verify OTP to Reset Password' : ($isWithdraw ? 'Verify OTP to Confirm Withdrawal' : 'Verify Your Account') ?>
            </h2>
            <p class="text-center text-gray-600 mb-2">
                Enter the 6-digit code sent to <?= $masked ? "<strong>{$masked}</strong>" : 'your email' ?>.
            </p>
            <p class="text-center text-gray-500 text-sm mb-6">The code expires in 10 minutes.</p>

            <form action="../process/otp_process.php" method="POST" class="space-y-4" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="flow" value="<?= $isReset ? 'reset' : ($isWithdraw ? 'withdraw' : 'verify') ?>">

                <div>
                    <label class="block text-gray-700 font-semibold" for="otp">Enter OTP</label>
                    <input
                        type="text"
                        inputmode="numeric"
                        name="otp"
                        id="otp"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        required
                        placeholder="6-digit code"
                        class="w-full p-3 border rounded mt-1 focus:outline-none focus:ring-2 focus:ring-[#092468] transition duration-200 tracking-widest text-center text-lg"
                        aria-label="One-time password"
                    >
                    <p class="text-xs text-gray-500 mt-1">Digits only.</p>
                </div>

                <button type="submit"
                        class="w-full bg-[#F4A124] text-white py-3 rounded hover:bg-[#d88b1c] focus:outline-none focus:ring-2 focus:ring-[#F4A124] transition duration-200">
                    <?= $isReset ? 'Verify & Continue' : 'Verify Account' ?>
                </button>
            </form>

            <div class="mt-4 text-center text-sm space-y-2">
                <p>Didn’t get the OTP?
                    <a href="../process/resend_otp_process.php<?= $isReset ? '?mode=reset' : ($isWithdraw ? '?mode=withdraw' : '') ?>" class="text-[#092468] font-semibold hover:underline">Resend OTP</a>
                </p>
                <p class="text-gray-500">Wrong email? <a class="underline text-[#092468]" href="<?= $isReset ? 'forgot_password.php' : ($isWithdraw ? '../dashboard/agent_earnings.php' : 'register.php') ?>">Go back</a></p>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script>
        // digits only, strip non-digits
        const otp = document.getElementById('otp');
        otp.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/\D+/g, '').slice(0, 6);
        });

        $(document).ready(function () {
            $("#mobile-menu-button").click(function () {
                $("#mobile-menu").toggleClass("hidden");
            });
        });
    </script>
</body>
</html>
