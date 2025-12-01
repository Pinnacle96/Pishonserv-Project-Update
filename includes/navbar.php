<?php
include __DIR__ . '/../includes/db_connect.php'; //nsu
include __DIR__ . '/../includes/secure_headers.php'; // Import Zoho CRM functions re site status is checked before rendering

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $is_auth_page = (strpos($_SERVER['PHP_SELF'], "/auth/") !== false);
    $base_path = $is_auth_page ? "../" : "";
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pishonserv - Buy, Sell & Rent Real Estate Properties</title>
    <meta name="description" content="Pishonserv is your trusted platform to buy, sell, or rent homes, apartments, and commercial properties with ease and security.">
    <meta name="keywords" content="Pishonserv, real estate, property, buy home, rent, sell, apartment, house for sale, commercial property">
    <meta name="author" content="Pishonserv">
    <link rel="canonical" href="https://pishonserv.com<?php echo $_SERVER['REQUEST_URI']; ?>">

    <meta property="og:title" content="Pishonserv - Your Real Estate Partner">
    <meta property="og:description" content="Discover properties for sale or rent on Pishonserv, the easiest way to explore real estate listings.">
    <meta property="og:image" content="<?php echo $base_path; ?>public/images/favicon.png">
    <meta property="og:url" content="https://pishonserv.com<?php echo $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Pishonserv">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Pishonserv - Real Estate Simplified">
    <meta name="twitter:description" content="Browse, list, and close deals on real estate faster with Pishonserv.">
    <meta name="twitter:image" content="<?php echo $base_path; ?>public/images/favicon.png">

    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>public/images/favicon.png">

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        .active-link {
            color: #CC9933 !important;
            font-weight: 600;
        }

        .slider-image {
            transition: opacity 0.5s ease-in-out;
        }

        .property-details p {
            line-height: 1.6;
        }

        .key-features li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>


<body>
    <nav id="navbar" class="fixed top-0 left-0 w-full bg-white shadow-md z-50">
        <div class="container mx-auto flex items-center justify-between py-4 px-6 md:px-10 lg:px-16">
            <a href="<?php echo $base_path; ?>index.php" class="flex items-center space-x-2">
                <img src="<?php echo $base_path; ?>public/images/logo.png" alt="Logo" class="h-12">
            </a>

            <ul class="hidden md:flex space-x-8 text-lg text-[#092468] font-medium">
                <li><a href="<?php echo $base_path; ?>index.php"
                        class="nav-link hover:text-[#CC9933] transition">Home</a></li>
                <li><a href="<?php echo $base_path; ?>properties.php"
                        class="nav-link hover:text-[#CC9933] transition">Listings</a></li>
                <li><a href="<?php echo $base_path; ?>furniture.php" class="nav-link hover:text-[#CC9933] transition">Furnitures</a></li>
                <li><a href="<?php echo $base_path; ?>project.php"
                        class="nav-link hover:text-[#CC9933] transition">Solar & Inverter</a></li>
                <li><a href="<?php echo $base_path; ?>about.php" class="nav-link hover:text-[#CC9933] transition">About
                        Us</a></li>
                <li><a href="<?php echo $base_path; ?>contact.php"
                        class="nav-link hover:text-[#CC9933] transition">Contact</a></li>
                <li><a href="<?php echo $base_path; ?>career.php"
                        class="nav-link hover:text-[#CC9933] transition">Career</a></li>
                <!--<li><a href="<?php echo $base_path; ?>referral-program.php"-->
                <!--        class="nav-link hover:text-[#CC9933] transition">Referral Program</a></li>-->
            </ul>

            <div class="flex items-center space-x-4">
                <div class="relative">
                    <button id="user-menu-btn" class="flex items-center text-[#092468] focus:outline-none">
                        <?php if (isset($_SESSION['user_id']) && isset($_SESSION['name']) && isset($_SESSION['profile_image'])): ?>
                            <img src="<?php echo $base_path . 'public/uploads/' . htmlspecialchars($_SESSION['profile_image']); ?>"
                                alt="Profile" class="w-8 h-8 rounded-full mr-2 object-cover">
                            <span class="text-lg font-medium"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                        <?php else: ?>
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        <?php endif; ?>
                    </button>

                    <div id="user-dropdown"
                        class="hidden absolute right-0 mt-2 w-48 bg-white shadow-lg rounded-lg py-2 z-50">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo $base_path; ?>auth/login.php"
                                class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Sign In</a>
                            <a href="<?php echo $base_path; ?>auth/register.php"
                                class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Sign Up</a>
                        <?php else: ?>
                            <?php
                            $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'buyer'; // Default to 'buyer'
                            if ($user_role === 'buyer') {
                                echo '
                                    <a href="' . $base_path . 'dashboard/buyer_dashboard.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Dashboard</a>
                                    <a href="' . $base_path . 'dashboard/buyer_orders.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">My Orders</a>
                                    <a href="' . $base_path . 'dashboard/buyer_wishlist.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Wishlist</a>
                                    <a href="' . $base_path . 'dashboard/buyer_messages.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Messages</a>
                                    <a href="' . $base_path . 'dashboard/buyer_profile.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Profile</a>
                                    <a href="' . $base_path . 'dashboard/buyer_security.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Security</a>
                                ';
                            } elseif (in_array($user_role, ['agent', 'owner', 'hotel_owner', 'developer'])) {
                                echo '
                                    <a href="' . $base_path . 'dashboard/agent_dashboard.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Dashboard</a>
                                    <a href="' . $base_path . 'dashboard/agent_properties.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Properties</a>
                                    <a href="' . $base_path . 'dashboard/agent_inquiries.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Inquiries</a>
                                    <a href="' . $base_path . 'dashboard/agent_earnings.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Earnings</a>
                                    <a href="' . $base_path . 'dashboard/agent_transaction.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Transactions</a>
                                ';
                            } elseif ($user_role === 'admin') {
                                echo '
                                    <a href="' . $base_path . 'dashboard/admin_dashboard.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Dashboard</a>
                                    <a href="' . $base_path . 'dashboard/admin_users.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Manage Users</a>
                                    <a href="' . $base_path . 'dashboard/admin_properties.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Manage Properties</a>
                                    <a href="' . $base_path . 'dashboard/admin_transactions.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Transactions</a>
                                    <a href="' . $base_path . 'dashboard/admin_messages.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Messages</a>
                                ';
                            } elseif ($user_role === 'superadmin') {
                                echo '
                                    <a href="' . $base_path . 'dashboard/superadmin_dashboard.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Superadmin Dashboard</a>
                                    <a href="' . $base_path . 'dashboard/admin_users.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Manage Users</a>
                                    <a href="' . $base_path . 'dashboard/admin_properties.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Manage Properties</a>
                                    <a href="' . $base_path . 'dashboard/admin_transactions.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Transactions</a>
                                    <a href="' . $base_path . 'dashboard/admin_messages.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Messages</a>
                                    <a href="' . $base_path . 'dashboard/superadmin_manage.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Manage Admins</a>
                                    <a href="' . $base_path . 'dashboard/superadmin_reports.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Reports & Analytics</a>
                                    <a href="' . $base_path . 'dashboard/sync_to_zoho.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">Sync Existing User</a>
                                    <a href="' . $base_path . 'dashboard/superadmin_settings.php" class="block px-4 py-2 text-[#092468] hover:bg-gray-100">System Settings</a>
                                ';
                            }
                            ?>
                            <a href="<?php echo $base_path; ?>process/logout.php"
                                class="block px-4 py-2 text-red-500 hover:bg-gray-100">Logout</a>
                            <?php endif; ?>
                    </div>
                </div>
                <?php
$cart_count = 0;

if (!empty($_SESSION['cart'])) {
    // Count items in session cart (for both guests and logged-in users)
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
    }
} elseif (isset($_SESSION['user_id'])) {
    // If logged in and session cart is empty, fallback to database
    $user_id = $_SESSION['user_id'];
    include_once __DIR__ . '/../includes/db_connect.php'; // Adjust path if needed

    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($cart_count);
    $stmt->fetch();
    $stmt->close();
}
?>

<a href="<?php echo $base_path; ?>cart.php"
    class="relative text-[#092468] hover:text-[#CC9933] transition">
    <i class="fa fa-shopping-cart text-2xl"></i>
    <?php if ($cart_count > 0): ?>
        <span class="absolute -top-2 -right-2 bg-[#F4A124] text-white text-xs rounded-full px-2 py-0.5">
            <?php echo $cart_count; ?>
        </span>
    <?php endif; ?>
</a>



                <a href="<?php echo $base_path; ?>dashboard/agent_properties.php"
                    class="hidden md:inline-block bg-[#CC9933] text-white px-5 py-3 rounded-lg hover:bg-[#d88b1c] transition">
                    Create Listing +
                </a>
            </div>

            <button id="mobile-menu-btn" class="md:hidden text-[#092468] focus:outline-none">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7">
                    </path>
                </svg>
            </button>
        </div>

        <div id="mobile-menu" class="hidden absolute top-full left-0 w-full bg-white shadow-md md:hidden">
            <ul class="text-center text-[#092468] text-lg">
                <li class="py-3 border-b"><a href="<?php echo $base_path; ?>index.php"
                        class="hover:text-[#CC9933]">Home</a></li>
                <li class="py-3 border-b"><a href="<?php echo $base_path; ?>properties.php"
                        class="hover:text-[#CC9933]">Listings</a></li>
                <li class="py-3 border-b"><a href="<?php echo $base_path; ?>furniture.php" class="hover:text-[#CC9933]">Furnitures</a></li>
                <li class="py-3 border-b"><a href="<?php echo $base_path; ?>project.php"
                        class="hover:text-[#CC9933]">Solar & Inverter</a></li>
                <li class="py-3 border-b"><a href="<?php echo $base_path; ?>about.php"
                        class="hover:text-[#CC9933]">About Us</a></li>
                <li class="py-3 border-b"><a href="<?php echo $base_path; ?>contact.php"
                        class="hover:text-[#CC9933]">Contact</a></li>
                <li class="py-3 border-b"><a href="<?php echo $base_path; ?>career.php"
                        class="hover:text-[#CC9933]">Career</a></li>
                <!--<li class="py-3 border-b"><a href="<?php echo $base_path; ?>referral-program.php"-->
                <!--        class="hover:text-[#CC9933]">Referral Program</a></li>-->
                <li class="py-3"><a href="<?php echo $base_path; ?>dashboard/agent_properties.php"
                        class="bg-[#CC9933] text-white px-6 py-3 rounded hover:bg-[#d88b1c]">Create Listing +</a></li>
            </ul>
        </div>
    </nav>
</body>

</html>