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

// Ensure user is logged in for persistent cart storage
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to add items to your cart.";
    header("Location: auth/login.php"); // Redirect to your login page
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
// Removed $selected_color as it's no longer used
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
// We now use sale_price and regular_price as per your products table structure
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
// Create a unique key for the cart item, now just the product ID
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
        // Removed 'color' field
    ];
}


// --- Database Cart Synchronization (`cart_items` table) ---
// First, check if the item already exists for this user in the database
// The 'color' column is now removed from the WHERE clause
$check_stmt = $conn->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
$check_stmt->bind_param("ii", $user_id, $product_id);
$check_stmt->execute();
$db_result = $check_stmt->get_result();

if ($existing_item = $db_result->fetch_assoc()) {
    // If it exists, update the quantity
    $update_stmt = $conn->prepare("UPDATE cart_items SET quantity = quantity + ? WHERE id = ?");
    $update_stmt->bind_param("ii", $quantity, $existing_item['id']);
    $update_stmt->execute();
    if ($update_stmt->error) {
        error_log("DB update error: " . $update_stmt->error);
        $_SESSION['error'] = "Failed to update cart in database.";
    }
    $update_stmt->close();
} else {
    // If it does not exist, insert a new record
    // The 'color' column is now removed from the INSERT statement
    $insert_stmt = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
    $insert_stmt->execute();
    if ($insert_stmt->error) {
        error_log("DB insert error: " . $insert_stmt->error);
        $_SESSION['error'] = "Failed to add item to database cart.";
    }
    $insert_stmt->close();
}
$check_stmt->close();

$_SESSION['success'] = htmlspecialchars($product['name']) . " x" . $quantity . " added to cart successfully!";

// Redirect back to the product detail page, preserving the product ID
header("Location: product_detail.php?id=" . $product_id);
exit();