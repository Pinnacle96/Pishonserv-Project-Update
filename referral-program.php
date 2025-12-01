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

// You can add more backend logic here if needed, but for the public page, this is sufficient.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Program | PishonServ</title>
    <meta name="description" content="Earn rewards by referring new clients and agents to PishonServ. Join our referral program today!">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://pishonserv.com/referral-program.php">
    <meta property="og:title" content="PishonServ Referral Program">
    <meta property="og:description" content="Earn rewards by referring new clients and agents to PishonServ. Join our referral program today!">
    <meta property="og:image" content="https://pishonserv.com/public/images/referral-banner.jpg">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
        <h1 class="text-4xl font-extrabold brand-text">PishonServ Referral Program</h1>
        <p class="mt-4 text-xl text-gray-700 max-w-3xl mx-auto">
            Share the PishonServ experience with your network and earn rewards. It's simple, transparent, and rewarding!
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
        <div class="bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-3xl font-bold brand-text mb-6">How It Works 🚀</h2>
            <div class="space-y-6 text-gray-700">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full brand-bg text-white">1</span>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-xl font-semibold brand-text">Get Your Link</h3>
                        <p class="mt-1">
                            Once you're a registered user, you get a unique referral link. You'll find it on your personalized dashboard after you join the program.
                        </p>
                    </div>
                </div>
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full brand-bg text-white">2</span>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-xl font-semibold brand-text">Share with Friends</h3>
                        <p class="mt-1">
                            Share your link with friends, family, and colleagues who might be looking to rent, buy, or list a property.
                        </p>
                    </div>
                </div>
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full brand-bg text-white">3</span>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-xl font-semibold brand-text">Earn Rewards</h3>
                        <p class="mt-1">
                            You get rewarded when your referrals complete a qualifying action, such as a successful booking or property listing.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-3xl font-bold brand-text mb-6">Rewards & Benefits ✨</h2>
            <div class="space-y-6">
                <div class="p-4 bg-[#f5f7fa] rounded-lg">
                    <h3 class="text-xl font-semibold brand-gold">For Clients:</h3>
                    <p class="mt-2 text-gray-700">
                        When a friend books an accommodation through your link, you receive a **5% cashback** on the total booking amount credited to your PishonServ Wallet.
                    </p>
                </div>
                <div class="p-4 bg-[#f5f7fa] rounded-lg">
                    <h3 class="text-xl font-semibold brand-gold">For Agents:</h3>
                    <p class="mt-2 text-gray-700">
                        When a new agent signs up and lists their first property through your link, you receive a **₦5,000 bonus** credited to your PishonServ Wallet.
                    </p>
                </div>
            </div>
            
            <div class="mt-8 text-center">
                <a href="register.php" class="inline-block brand-bg brand-bg-hover text-white font-bold py-3 px-8 rounded-full text-lg transition">Join Now</a>
            </div>
        </div>
    </div>

    <div class="text-center mt-12 bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold brand-text mb-4">Have Questions?</h2>
        <p class="text-gray-700">
            Reach out to our support team for more information about the referral program.
        </p>
        <?php if ($superadmin_info): ?>
            <a href="tel:<?php echo htmlspecialchars($superadmin_info['phone']); ?>" 
               class="inline-flex items-center gap-2 mt-4 text-white brand-bg px-6 py-3 rounded-full font-bold">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                </svg>
                Call Support
            </a>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

</body>
</html>
