<?php
session_start();

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
try {
    if (file_exists('includes/db_connect.php')) {
        include 'includes/db_connect.php';
    } else {
        $_SESSION['error'] = "Database connection file missing. Cannot add to cart.";
        // Fallback redirection in case product_id is not yet available
        header("Location: furniture.php"); 
        exit();
    }
} catch (Exception $e) {
    error_log('DB connection error in add_to_cart.php: ' . $e->getMessage());
    $_SESSION['error'] = "A server error occurred. Please try again later.";
    header("Location: furniture.php");
    exit();
}

// Removed the user login check.
// Cart will now be session-based only. If you need persistent carts for guests,
// you'd need a more advanced system (e.g., storing a unique session ID in the DB
// and associating cart_items with that, or creating a temporary guest user).

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Validate inputs
if ($product_id <= 0) {
    $_SESSION['error'] = "Invalid product selected. Please try again.";
    header("Location: furniture.php"); // Redirect to main products page
    exit();
}
if ($quantity <= 0) {
    $_SESSION['error'] = "Quantity must be at least 1.";
    header("Location: product_detail.php?id=" . $product_id);
    exit();
}

// Fetch product details from the database
$stmt = $conn->prepare("SELECT id, name, sale_price, regular_price, images FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    $_SESSION['error'] = "Product not found.";
    header("Location: furniture.php");
    exit();
}

// Determine the actual price to use (sale_price if available, otherwise regular_price)
$price = !empty($product['sale_price']) ? $product['sale_price'] : $product['regular_price'];
if (empty($price)) {
    $_SESSION['error'] = "Product price not available. Cannot add to cart.";
    header("Location: product_detail.php?id=" . $product_id);
    exit();
}

// Get the first image path for the cart item display
$image_paths = explode(',', $product['images']);
$cart_image = !empty($image_paths[0]) ? trim($image_paths[0]) : 'https://placehold.co/50x50/e0e0e0/555555?text=No+Image';


// --- Session Cart Management ---
// Create a unique key for the cart item, just the product ID
$cart_key = $product['id'];

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add or update the item in the session cart
if (isset($_SESSION['cart'][$cart_key])) {
    $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
} else {
    $_SESSION['cart'][$cart_key] = [
        'id' => $product['id'],
        'name' => htmlspecialchars($product['name']),
        'price' => $price, // Use the determined price
        'quantity' => $quantity,
        'image' => htmlspecialchars($cart_image),
    ];
}

// Removed the database synchronization part that relied on user_id.
// The cart will now exclusively use PHP sessions for non-logged-in users.
// If a user *is* logged in, their cart will still be stored in $_SESSION,
// but it won't be persisted to the database.
// To handle logged-in users and guest users in the same DB cart system,
// you would typically add a nullable `user_id` column and use `session_id()`
// for guests, or manage a temporary 'guest' user in the database.
// For the scope of "without login", a session-only cart is the most direct approach.

$_SESSION['success'] = htmlspecialchars($product['name']) . " x" . $quantity . " added to cart successfully!";

// Redirect back to the product detail page, preserving the product ID
header("Location: product_detail.php?id=" . $product_id);
exit();
?>





// <?php
// session_start();
// include 'includes/db_connect.php';

// // ✅ Ensure user is logged in
// if (!isset($_SESSION['user_id'])) {
//     $_SESSION['error'] = "Please log in to add items to your cart.";
//     header("Location: auth/login.php");
//     exit();
// }

// $user_id = $_SESSION['user_id'];
// $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
// $selected_color = isset($_POST['color']) ? trim($_POST['color']) : 'default';

// if ($product_id <= 0) {
//     $_SESSION['error'] = "Invalid product selected.";
//     header("Location: index.php");
//     exit();
// }

// // ✅ Fetch product details
// $stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id = ?");
// $stmt->bind_param("i", $product_id);
// $stmt->execute();
// $result = $stmt->get_result();
// $product = $result->fetch_assoc();

// if (!$product) {
//     $_SESSION['error'] = "Product not found.";
//     header("Location: index.php");
//     exit();
// }

// // ✅ Unique cart key: product + color
// $cart_key = $product_id . '_' . $selected_color;

// // ✅ Update session cart
// if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// if (isset($_SESSION['cart'][$cart_key])) {
//     $_SESSION['cart'][$cart_key]['quantity'] += 1;
// } else {
//     $_SESSION['cart'][$cart_key] = [
//         'id' => $product['id'],
//         'name' => $product['name'],
//         'price' => $product['price'],
//         'image' => $product['image'],
//         'color' => $selected_color,
//         'quantity' => 1
//     ];
// }

// // ✅ Sync to database `cart_items`
// $check = $conn->prepare("SELECT id FROM cart_items WHERE user_id = ? AND product_id = ? AND color = ?");
// $check->bind_param("iis", $user_id, $product_id, $selected_color);
// $check->execute();
// $res = $check->get_result();

// if ($existing = $res->fetch_assoc()) {
//     $update = $conn->prepare("UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?");
//     $update->bind_param("i", $existing['id']);
//     $update->execute();
// } else {
//     $insert = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity, color) VALUES (?, ?, ?, ?)");
//     $qty = 1;
//     $insert->bind_param("iiis", $user_id, $product_id, $qty, $selected_color);
//     $insert->execute();
// }

// $_SESSION['success'] = "Added to cart successfully.";
// header("Location: " . $_SERVER['HTTP_REFERER']);
// exit();