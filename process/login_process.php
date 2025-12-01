<?php
session_start();
include '../includes/db_connect.php'; // Includes DB + may set $site_status + CSRF token

$traceLog = __DIR__ . '/../logs/login_trace.txt';
$t0 = microtime(true);
file_put_contents($traceLog, "-- LOGIN START " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Only handle POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF (constant-time compare)
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token!";
        header("Location: ../auth/login.php");
        exit();
    }
    file_put_contents($traceLog, "✔ CSRF token passed in " . round((microtime(true) - $t0) * 1000) . "ms\n", FILE_APPEND);

    // Credentials
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Site status (fallback to active if not set)
    $site_status = $site_status ?? 'active';
    if (in_array($site_status, ['maintenance', 'inactive'], true)) {
        $stmt = $conn->prepare("SELECT role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($roleDuringMaintenance);

        if ($stmt->num_rows > 0) {
            $stmt->fetch();
            if ($roleDuringMaintenance !== 'superadmin') {
                $_SESSION['error'] = "Login is restricted to superadmins only during maintenance or inactive mode.";
                header("Location: ../auth/login.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Incorrect email or password.";
            header("Location: ../auth/login.php");
            exit();
        }
        $stmt->close();
    }
    file_put_contents($traceLog, "✔ Site status passed in " . round((microtime(true) - $t0) * 1000) . "ms\n", FILE_APPEND);

    // Fetch user
    $stmt = $conn->prepare("
        SELECT id, name, email, password, role, profile_image, email_verified
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $name, $emailDb, $hashed_password, $role, $profile_image, $email_verified);

    if ($stmt->num_rows > 0) {
        $stmt->fetch();
        file_put_contents($traceLog, "✔ DB fetch done in " . round((microtime(true) - $t0) * 1000) . "ms\n", FILE_APPEND);

        if (password_verify($password, $hashed_password)) {
            file_put_contents($traceLog, "✔ Password verified in " . round((microtime(true) - $t0) * 1000) . "ms\n", FILE_APPEND);

            // If not verified → generate OTP, prime session, redirect to verify page
            if ((int)$email_verified !== 1) {
                try {
                    $otpPlain     = (string)random_int(100000, 999999);
                    $otpHash      = password_hash($otpPlain, PASSWORD_DEFAULT);
                    $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                    $upd = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expires_at = ? WHERE id = ?");
                    $upd->bind_param('ssi', $otpHash, $otpExpiresAt, $id);
                    $upd->execute();
                    $upd->close();

                    // TODO: Send OTP email with your mailer (uncomment and plug in):
                    // send_verification_email($emailDb, $name, $otpPlain);

                    // Prime verify page context (these are what verify-otp.php expects)
                    $_SESSION['user_id_to_verify']    = (int)$id;
                    $_SESSION['user_email_to_verify'] = $emailDb;

                    // Make sure we are NOT in reset mode from any previous flow
                    unset($_SESSION['password_reset_mode'], $_SESSION['user_id_reset']);

                    // Optional: where to go after successful verify (keep or change)
                    // $_SESSION['redirect_after_login'] = 'index.php';

                    $_SESSION['success'] = "We sent a verification code to your email.";
                    header("Location: ../auth/verify-otp.php");
                    exit();
                } catch (Throwable $e) {
                    file_put_contents($traceLog, "✖ OTP gen/send error: " . $e->getMessage() . "\n", FILE_APPEND);
                    $_SESSION['error'] = "Could not initiate verification. Please try again.";
                    header("Location: ../auth/login.php");
                    exit();
                }
            }

            // Verified user → set session & redirect
            $_SESSION['user_id']       = (int)$id;
            $_SESSION['name']          = $name;
            $_SESSION['role']          = $role;
            $_SESSION['profile_image'] = $profile_image;

            session_write_close(); // speed up response

            // Redirect priority: session → cookie → role
            if (!empty($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                header("Location: ../" . ltrim($redirect, '/'));
                exit();
            }

            if (!empty($_COOKIE['redirect_after_login'])) {
                $redirect = $_COOKIE['redirect_after_login'];
                setcookie("redirect_after_login", "", time() - 3600, "/");
                header("Location: ../" . ltrim($redirect, '/'));
                exit();
            }

            switch ($role) {
                case 'buyer':
                    header("Location: ../index.php");
                    break;
                case 'agent':
                case 'owner':
                case 'hotel_owner':
                case 'developer':
                case 'host':
                    header("Location: ../dashboard/agent_dashboard.php");
                    break;
                case 'admin':
                    header("Location: ../dashboard/admin_dashboard.php");
                    break;
                case 'superadmin':
                    header("Location: ../dashboard/superadmin_dashboard.php");
                    break;
                default:
                    $_SESSION['error'] = "Unknown user role.";
                    header("Location: ../auth/login.php");
                    exit();
            }
            exit();
        } else {
            // Generic error (don’t leak which field failed)
            $_SESSION['error'] = "Incorrect email or password.";
            header("Location: ../auth/login.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Incorrect email or password.";
        header("Location: ../auth/login.php");
        exit();
    }
}
