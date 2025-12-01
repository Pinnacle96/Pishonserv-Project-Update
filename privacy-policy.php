<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'includes/db_connect.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Get superadmin contact info for the public page if needed
$superadmin_info = $conn->query("SELECT name, phone, email FROM users WHERE role = 'superadmin' LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | PishonServ</title>
    <meta name="description" content="Our Privacy Policy details how PishonServ collects, uses, and protects your personal information.">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .brand-text { color: #092468; }
        .brand-gold { color: #CC9933; }
        .brand-bg { background-color: #092468; }
        .brand-bg-hover:hover { background-color: #0d307e; }
        .brand-border { border-color: #092468; }
    </style>
</head>
<body class="bg-[#f5f7fa] text-brand-text min-h-screen">

<?php include 'includes/navbar.php'; ?>

<section class="container mx-auto pt-40 py-12 px-4 md:px-10 lg:px-16">
    <div class="text-center mb-12">
        <h1 class="text-4xl font-extrabold brand-text">Privacy Policy</h1>
        <p class="mt-4 text-xl text-gray-700 max-w-3xl mx-auto">
            Your privacy is important to us. This policy explains how we handle your data.
        </p>
    </div>

    <div class="bg-white p-8 rounded-lg shadow-md max-w-4xl mx-auto">
        <div class="space-y-6 text-gray-700">
            <h2 class="text-2xl font-bold brand-text">1. Information We Collect</h2>
            <p>We collect information to provide and improve our services to you. This includes personal information you provide directly (such as name, email, phone number) when you register, make a booking, or contact us. We also collect usage data automatically as you interact with our platform.</p>

            <h2 class="text-2xl font-bold brand-text mt-6">2. How We Use Your Information</h2>
            <p>We use your information to:</p>
            <ul class="list-disc list-inside ml-4">
                <li>Provide and manage your access to our services.</li>
                <li>Process transactions and bookings.</li>
                <li>Communicate with you about your account and services.</li>
                <li>Improve our platform and services.</li>
                <li>Prevent fraudulent activities and ensure security.</li>
                <li>Comply with legal obligations.</li>
            </ul>

            <h2 class="text-2xl font-bold brand-text mt-6">3. Data Security</h2>
            <p>We are committed to protecting your data. We implement a variety of security measures to maintain the safety of your personal information when you enter, submit, or access your data. However, no method of transmission over the internet is 100% secure.</p>

            <h2 class="text-2xl font-bold brand-text mt-6">4. Third-Party Services</h2>
            <p>We may share your information with trusted third-party partners and vendors to help us operate our business, such as payment processors and property owners. These third parties are bound by confidentiality agreements and are prohibited from using your data for any other purpose.</p>
            
            <h2 class="text-2xl font-bold brand-text mt-6">5. Your Rights</h2>
            <p>You have the right to access, update, or delete your personal information. You can also request a copy of the data we hold about you. For any such requests, please contact us using the information below.</p>

            <h2 class="text-2xl font-bold brand-text mt-6">6. Contact Us</h2>
            <p>If you have any questions about this Privacy Policy, please contact us at:</p>
            <p class="mt-2">Pishonserv International Limited<br>
            <strong>Website:</strong> www.pishonserv.com<br>
            <strong>Email:</strong> pishonserv@pishonserv.com</p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

</body>
</html>
