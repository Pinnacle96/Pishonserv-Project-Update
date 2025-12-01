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
    <title>Refund Policy | PishonServ</title>
    <meta name="description" content="PishonServ's policy regarding refunds and cancellations for our services.">
    
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
        <h1 class="text-4xl font-extrabold brand-text">Refund Policy</h1>
        <p class="mt-4 text-xl text-gray-700 max-w-3xl mx-auto">
            Our policy on cancellations and refunds for services and bookings.
        </p>
    </div>

    <div class="bg-white p-8 rounded-lg shadow-md max-w-4xl mx-auto">
        <div class="space-y-6 text-gray-700">
            <h2 class="text-2xl font-bold brand-text">1. General Policy</h2>
            <p>Our refund policy is governed by the specific terms of each service or property listing. The availability of a refund depends on the timing of your cancellation and the individual terms set by the vendor or property owner.</p>

            <h2 class="text-2xl font-bold brand-text mt-6">2. Refunds for Bookings</h2>
            <p>For bookings of serviced apartments, hotels, and short lets, refunds will be issued in accordance with the cancellation policy specified at the time of booking. It is your responsibility to review the individual terms of each listing before confirming a reservation.</p>

            <h2 class="text-2xl font-bold brand-text mt-6">3. Refunds for Products and Services</h2>
            <p>All sales of products, including furniture, fittings, and solar solutions, are final once the order is confirmed and approved. Refunds or exchanges are only granted in cases of verified product defects, damages, or non-delivery, subject to the specific terms of the manufacturer or vendor.</p>
            
            <h2 class="text-2xl font-bold brand-text mt-6">4. Commission and Service Fees</h2>
            <p>Commissions, brokerage fees, and any other service fees paid to PishonServ are non-refundable once the service has been rendered or the transaction is successfully completed.</p>

            <h2 class="text-2xl font-bold brand-text mt-6">5. How to Request a Refund</h2>
            <p>To request a refund, please contact us with your booking reference or order number and a detailed reason for your request. All refund requests will be reviewed on a case-by-case basis. We reserve the right to deny a refund if it does not meet the specified criteria.</p>

            <h2 class="text-2xl font-bold brand-text mt-6">6. Contact Us</h2>
            <p>For more information or to initiate a refund request, please email us at <a href="mailto:pishonserv@pishonserv.com" class="text-blue-600 hover:underline">pishonserv@pishonserv.com</a>.</p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

</body>
</html>
