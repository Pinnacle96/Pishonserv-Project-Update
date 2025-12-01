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
    <title>Terms & Conditions | PishonServ</title>
    <meta name="description" content="PishonServ International Limited Terms and Conditions Policy.">
    
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
        <h1 class="text-4xl font-extrabold brand-text">Terms and Conditions</h1>
        <p class="mt-4 text-xl text-gray-700 max-w-3xl mx-auto">
            Please read our terms of service carefully before using our platform.
        </p>
    </div>

    <div class="bg-white p-8 rounded-lg shadow-md max-w-4xl mx-auto">
        <div class="space-y-6 text-gray-700">
            <h2 class="text-2xl font-bold brand-text">PISHONSERV INTERNATIONAL LIMITED</h2>
            <!--<p><strong>Effective Date:</strong> [Insert Date]</p>-->

            <h3 class="text-xl font-semibold brand-text mt-6">1. Use of Services</h3>
            <p>Users must be at least 18 years old to access or transact on the platform. You agree to provide accurate, up-to-date information during registration and transactions. Your use of the platform must comply with all applicable laws and these Terms. Pishonserv reserves the right to suspend or terminate accounts that breach these conditions.</p>
            
            <h3 class="text-xl font-semibold brand-text mt-6">2. Bookings & Payments</h3>
            <p>All bookings, including serviced apartments, hotels, and short lets, must be made through Pishonserv's official channels. Payment details, including pricing and payment method, are provided per service or listing. Prices are subject to change based on availability and market factors. Refunds and cancellations follow the terms in our Cancellation & Refund Policy, which may vary per service or listing.</p>

            <h3 class="text-xl font-semibold brand-text mt-6">3. Commission Sharing & MOU Obligations</h3>
            <p>Commission-sharing arrangements between Pishonserv and agents/vendors will be executed only on mutual understanding and with an officially prepared and approved Memorandum of Understanding (MOU). Commissions shall only be recognized and paid according to the terms agreed in the signed MOU. Any product, service, or transaction not covered in an existing MOU shall be subject to review and may not proceed until both parties reach written agreement. The MOU represents a binding business instrument and will be enforceable under Nigerian law.</p>

            <h3 class="text-xl font-semibold brand-text mt-6">4. Agent & Vendor Rights and Obligations</h3>
            <p>Agents and vendors are strictly prohibited from carrying out any transaction involving listings on the Pishonserv platform without the prior knowledge and direct involvement of Pishonserv. All activities involving clients, bookings, or inquiries originating from or involving Pishonserv must be formally disclosed to Pishonserv management. Unauthorized or undisclosed transactions will constitute a breach of these Terms and may lead to partnership revocation, legal redress, or blacklisting.</p>
            
            <h3 class="text-xl font-semibold brand-text mt-6">5. Vendor Responsibilities</h3>
            <p>Vendors are required to maintain accurate and real-time information about all products and services listed on the Pishonserv platform. If any product becomes unavailable, vendors must promptly report to Pishonserv for formal approval before removal or update. Vendors must clearly indicate whether listed briefs are direct or semi-direct, and must be prepared to provide additional documentation when requested. Vendors understand that upon agreeing to these Terms, they are entering into a legally binding agreement under the jurisdiction of the Federal Republic of Nigeria.</p>
            
            <h3 class="text-xl font-semibold brand-text mt-6">6. Furniture, Fittings & Solar Energy Services</h3>
            <p>Orders for furniture and solar solutions are subject to confirmation of design, size, technical requirements, and delivery logistics. Product warranties, delivery timelines, and post-sale support are governed by specific agreements and/or manufacturer policies. Changes to custom orders may affect cost or delivery timelines and must be approved in writing.</p>
            
            <h3 class="text-xl font-semibold brand-text mt-6">7. Intellectual Property</h3>
            <p>All content, including text, images, videos, graphics, and software on the platform, is the property of Pishonserv or its licensors. Unauthorized copying, reproduction, or use of any content is strictly prohibited.</p>
            
            <h3 class="text-xl font-semibold brand-text mt-6">8. Limitation of Liability</h3>
            <p>Pishonserv shall not be held responsible for delays, errors, or failures caused by third-party service providers or vendors. While we aim to ensure accuracy of listings, Pishonserv is not liable for misrepresentation or unavailability of third-party content not promptly disclosed to us. Our liability for any claim related to the use of our platform is limited to the amount paid to us for the specific service in question.</p>
            
            <h3 class="text-xl font-semibold brand-text mt-6">9. Privacy Policy</h3>
            <p>We are committed to safeguarding your data. For details on how we collect, use, and protect your personal information, please refer to our Privacy Policy available on our website.</p>
            
            <h3 class="text-xl font-semibold brand-text mt-6">10. Modifications to Terms</h3>
            <p>Pishonserv reserves the right to update or modify these Terms and Conditions at any time. Changes will take effect upon publication on the platform. Continued use of the platform after changes constitutes your agreement to the updated terms.</p>
            
            <h3 class="text-xl font-semibold brand-text mt-6">11. Governing Law</h3>
            <p>These Terms and Conditions are governed by and interpreted in accordance with the laws of the Federal Republic of Nigeria. Disputes shall be resolved in the appropriate courts within the jurisdiction.</p>
            
            <h3 class="text-xl font-semibold brand-text mt-6">12. Contact Information</h3>
            <p class="mt-2">Pishonserv International Limited<br>
            <strong>Website:</strong> www.pishonserv.com<br>
            <strong>Email:</strong> pishonserv@pishonserv.com<br>
            <strong>Address:</strong> Nomadian Tech Hub 3rd Floor 152 Obafemi
                    Awolowo way, opposite Airport hotel near Allen Junction Bus Stop. IKeja<br>
            <strong>Phone:</strong> +2348111973369</p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

</body>
</html>
