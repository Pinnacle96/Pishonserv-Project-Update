<?php
// Ensure session is started. This check prevents errors if it's already started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch user details from the session
$user_role = $_SESSION['role'] ?? 'buyer';
$current_page = basename($_SERVER['PHP_SELF']);

// Define navigation links for each user role in a single, organized array.
// This makes the code much cleaner and easier to manage.
$nav_links = [
    'buyer' => [
        ['href' => 'buyer_dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['href' => 'buyer_orders.php', 'icon' => 'fas fa-shopping-cart', 'text' => 'My Orders'],
        ['href' => 'buyer_wishlist.php', 'icon' => 'fas fa-heart', 'text' => 'Wishlist'],
        ['href' => 'buyer_messages.php', 'icon' => 'fas fa-comments', 'text' => 'Messages'],
        ['href' => 'buyer_profile.php', 'icon' => 'fas fa-user', 'text' => 'Profile'],
        ['href' => 'buyer_security.php', 'icon' => 'fas fa-lock', 'text' => 'Security'],
        ['href' => 'agent_earnings.php', 'icon' => 'fas fa-wallet', 'text' => 'Earnings'],
        ['href' => 'agent_transaction.php', 'icon' => 'fas fa-file-invoice-dollar', 'text' => 'Transactions'],
    ],
    'agent' => [
        ['href' => 'agent_dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['href' => 'agent_mou.php', 'icon' => 'fas fa-envelope', 'text' => 'Download MOU'],
        ['href' => 'agent_properties.php', 'icon' => 'fas fa-building', 'text' => 'Manage Properties'],
        ['href' => 'agent_inquiries.php', 'icon' => 'fas fa-envelope', 'text' => 'Inquiries'],
        ['href' => 'agent_earnings.php', 'icon' => 'fas fa-wallet', 'text' => 'Earnings'],
        ['href' => 'agent_transaction.php', 'icon' => 'fas fa-file-invoice-dollar', 'text' => 'Transactions'],
    ],
    // 'owner', 'hotel_owner', and 'developer' roles can share the 'agent' links
    'owner' => [
        ['href' => 'agent_dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['href' => 'agent_mou.php', 'icon' => 'fas fa-envelope', 'text' => 'Download MOU'],
        ['href' => 'agent_properties.php', 'icon' => 'fas fa-building', 'text' => 'Manage Properties'],
        ['href' => 'agent_inquiries.php', 'icon' => 'fas fa-envelope', 'text' => 'Inquiries'],
        ['href' => 'agent_earnings.php', 'icon' => 'fas fa-wallet', 'text' => 'Earnings'],
        ['href' => 'agent_transaction.php', 'icon' => 'fas fa-file-invoice-dollar', 'text' => 'Transactions'],
    ],
    'hotel_owner' => [
        ['href' => 'agent_dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['href' => 'agent_mou.php', 'icon' => 'fas fa-envelope', 'text' => 'Download MOU'],
        ['href' => 'agent_properties.php', 'icon' => 'fas fa-building', 'text' => 'Manage Properties'],
        ['href' => 'agent_inquiries.php', 'icon' => 'fas fa-envelope', 'text' => 'Inquiries'],
        ['href' => 'agent_earnings.php', 'icon' => 'fas fa-wallet', 'text' => 'Earnings'],
        ['href' => 'agent_transaction.php', 'icon' => 'fas fa-file-invoice-dollar', 'text' => 'Transactions'],
    ],
    'host' => [
        ['href' => 'agent_dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['href' => 'agent_mou.php', 'icon' => 'fas fa-envelope', 'text' => 'Download MOU'],
        ['href' => 'agent_properties.php', 'icon' => 'fas fa-building', 'text' => 'Manage Properties'],
        ['href' => 'agent_inquiries.php', 'icon' => 'fas fa-envelope', 'text' => 'Inquiries'],
        ['href' => 'agent_earnings.php', 'icon' => 'fas fa-wallet', 'text' => 'Earnings'],
        ['href' => 'agent_transaction.php', 'icon' => 'fas fa-file-invoice-dollar', 'text' => 'Transactions'],
    ],
    'developer' => [
        ['href' => 'agent_dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['href' => 'agent_mou.php', 'icon' => 'fas fa-envelope', 'text' => 'Download MOU'],
        ['href' => 'agent_properties.php', 'icon' => 'fas fa-building', 'text' => 'Manage Properties'],
        ['href' => 'agent_inquiries.php', 'icon' => 'fas fa-envelope', 'text' => 'Inquiries'],
        ['href' => 'agent_earnings.php', 'icon' => 'fas fa-wallet', 'text' => 'Earnings'],
        ['href' => 'agent_transaction.php', 'icon' => 'fas fa-file-invoice-dollar', 'text' => 'Transactions'],
    ],
    'admin' => [
        ['href' => 'admin_dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['href' => 'admin_users.php', 'icon' => 'fas fa-users', 'text' => 'Manage Users'],
        ['href' => 'admin_properties.php', 'icon' => 'fas fa-building', 'text' => 'Manage Properties'],
        ['href' => 'admin_transactions.php', 'icon' => 'fas fa-money-bill', 'text' => 'Transactions'],
        ['href' => 'admin_products.php', 'icon' => 'fas fa-boxes', 'text' => 'Manage Products'],
        ['href' => 'admin_messages.php', 'icon' => 'fas fa-comments', 'text' => 'Messages'],
    ],
    'superadmin' => [
        ['href' => 'superadmin_dashboard.php', 'icon' => 'fas fa-user-shield', 'text' => 'Superadmin Dashboard'],
        ['href' => 'admin_users.php', 'icon' => 'fas fa-users', 'text' => 'Manage Users'],
        ['href' => 'admin_properties.php', 'icon' => 'fas fa-building', 'text' => 'Manage Properties'],
        ['href' => 'admin_transactions.php', 'icon' => 'fas fa-money-bill', 'text' => 'Transactions'],
         ['href' => 'agent_earnings.php', 'icon' => 'fas fa-wallet', 'text' => 'Earnings'],
        ['href' => 'admin_products.php', 'icon' => 'fas fa-boxes', 'text' => 'Manage Products'],
        ['href' => 'admin_messages.php', 'icon' => 'fas fa-comments', 'text' => 'Messages'],
        ['href' => 'superadmin_manage.php', 'icon' => 'fas fa-users-cog', 'text' => 'Manage Admins'],
        ['href' => 'superadmin_reports.php', 'icon' => 'fas fa-chart-line', 'text' => 'Reports & Analytics'],
        ['href' => 'sync_to_zoho.php', 'icon' => 'fas fa-cogs', 'text' => 'Sync Existing User'],
        ['href' => '../api/documentation.html', 'icon' => 'fas fa-book', 'text' => 'API Documentation'],
        ['href' => 'superadmin_settings.php', 'icon' => 'fas fa-cogs', 'text' => 'System Settings'],
    ],
];

// Get the correct set of links based on the user's role
$links_to_display = $nav_links[$user_role] ?? $nav_links['buyer'];
?>

<!-- Dynamic Navigation Links -->
<?php foreach ($links_to_display as $link) { ?>
    <?php
        // Determine if the current link is the active page
        $is_active = ($current_page === $link['href']);
        $link_class = 'flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition duration-200';
        if ($is_active) {
            $link_class .= ' bg-gray-200 dark:bg-gray-700 font-semibold text-blue-600 dark:text-blue-400';
        }
    ?>
    <li>
        <a href="<?php echo htmlspecialchars($link['href']); ?>" class="<?php echo $link_class; ?>">
            <i class="<?php echo htmlspecialchars($link['icon']); ?>"></i>
            <span><?php echo htmlspecialchars($link['text']); ?></span>
        </a>
    </li>
<?php } ?>

<!-- Logout link is always displayed -->
<li>
    <a href="../process/logout.php"
        class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition duration-200 text-red-500">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</li>

