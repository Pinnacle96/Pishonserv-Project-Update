<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$base_path = '/';

// Moved includes here as they might be needed for rendering, even if not logged in.
// Also, the db_connect is needed for fetching product details if the session cart is populated
// with only IDs, or for displaying product names/images from the products table.
try {
    if (file_exists('includes/db_connect.php')) {
        include 'includes/db_connect.php';
    } else {
        error_log('Missing db_connect.php in cart.php');
    }
    if (file_exists('includes/navbar.php')) {
        include 'includes/navbar.php';
    }
} catch (Exception $e) {
    error_log('Include error in cart.php: ' . $e->getMessage());
}

// User ID is no longer strictly required for basic cart functionality,
// but it will be available if the user IS logged in.
$user_id = $_SESSION['user_id'] ?? null;

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart = $_SESSION['cart'];

// If the cart is loaded purely from session and is populated,
// we might need to fetch missing product details (like name, image, price)
// if add_to_cart only stored product_id and quantity for some reason,
// though current add_to_cart.php already stores full details.
// This block is largely for robustness or if session data gets corrupted.
if (!empty($cart) && isset($conn)) { // Check if $conn is available before using it
    foreach ($cart as $product_id => $item) {
        // Check if essential product details are missing from session (e.g., after an update)
        if (!isset($item['name']) || !isset($item['price']) || !isset($item['image'])) {
            $stmt = $conn->prepare("SELECT name, sale_price, regular_price, images FROM products WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product_details = $result->fetch_assoc();

            if ($product_details) {
                $_SESSION['cart'][$product_id]['name'] = htmlspecialchars($product_details['name']);
                $_SESSION['cart'][$product_id]['price'] = !empty($product_details['sale_price']) ? $product_details['sale_price'] : $product_details['regular_price'];
                $image_paths = explode(',', $product_details['images']);
                $_SESSION['cart'][$product_id]['image'] = !empty($image_paths[0]) ? trim($image_paths[0]) : 'https://placehold.co/50x50/e0e0e0/555555?text=No+Image';
            } else {
                // Product no longer exists, remove from cart
                unset($_SESSION['cart'][$product_id]);
                error_log("Product ID {$product_id} not found in DB for cart, removed from session.");
            }
        }
    }
    // Re-assign cart after potential updates
    $cart = $_SESSION['cart'];
}


// Handle quantity updates (session only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $product_id_key => $quantity) {
            // Ensure product_id_key is an integer (since cart_key is now just product ID)
            $product_id_key = (int)$product_id_key;

            if (isset($_SESSION['cart'][$product_id_key])) {
                $_SESSION['cart'][$product_id_key]['quantity'] = max(1, (int)$quantity);

                // No database sync here, as the cart is session-only for all users currently.
                // If you reintroduce database persistence for logged-in users, this would be updated.
            }
        }
    }
    header("Location: cart.php");
    exit();
}

// Handle remove (session only)
if (isset($_GET['remove'])) {
    $product_id_to_remove = (int)$_GET['remove']; // Cast to int since key is product ID

    if (isset($_SESSION['cart'][$product_id_to_remove])) {
        // No database delete here, as the cart is session-only.
        unset($_SESSION['cart'][$product_id_to_remove]);
    }
    header("Location: cart.php");
    exit();
}

// Re-fetch cart for display after any modifications
$cart = $_SESSION['cart'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Your Cart - Vava Furniture</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>public/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: 'Inter', sans-serif; /* A modern, clean font */
            background: #f0f2f5; /* Lighter background for a fresh feel */
            color: #1a202c; /* Darker text for readability */
        }

        /* Custom primary button - using a warm, inviting orange */
        .btn-primary {
            background-color: #F4A124;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 4px 10px rgba(244, 161, 36, 0.2);
        }

        .btn-primary:hover {
            background-color: #d88b1c;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(244, 161, 36, 0.3);
        }

        /* Custom secondary button - using a deep, professional blue */
        .btn-secondary {
            background-color: #092468;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 4px 10px rgba(9, 36, 104, 0.2);
        }

        .btn-secondary:hover {
            background-color: #071a4d;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(9, 36, 104, 0.3);
        }

        /* Quantity input styling */
        .quantity-input {
            width: 70px; /* Adjusted width */
            padding: 0.5rem 0.75rem;
            border: 1px solid #cbd5e0;
            border-radius: 0.375rem;
            text-align: center;
            -moz-appearance: textfield; /* Hide arrows in Firefox */
        }

        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Specific styles for cart item details for better readability */
        .cart-item-name {
            font-weight: 600;
            color: #2d3748;
        }

        .cart-item-price {
            color: #4a5568;
            font-size: 0.95rem;
        }

        .cart-item-subtotal {
            font-weight: 700;
            color: #2d3748;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="min-h-screen pt-28">
    <div class="container mx-auto py-12 px-4 sm:px-6 lg:px-8 xl:px-16 mt-2">
        <h2 class="text-4xl lg:text-5xl font-extrabold text-center mb-10 text-[#092468]">Your Shopping Cart</h2>

        <?php if (empty($cart)): ?>
        <div class="bg-white p-8 rounded-xl shadow-lg text-center max-w-md mx-auto">
            <p class="text-xl text-gray-700 mb-6">Your cart is currently empty. Start exploring our amazing products!</p>
            <a href="furniture.php" class="btn-primary inline-flex items-center space-x-2">
                <i class="fas fa-shopping-bag mr-2"></i> Continue Shopping
            </a>
        </div>
        <?php else: ?>
        <form method="POST" id="cart-form">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-6">
                    <?php $grand_total = 0; ?>
                    <?php foreach ($cart as $id => $item): ?>
                    <?php
                        $subtotal = $item['price'] * $item['quantity'];
                        $grand_total += $subtotal;
                    ?>
                    <div class="flex flex-col sm:flex-row items-center bg-white p-5 rounded-xl shadow-md transition-all duration-200 hover:shadow-lg">
                        <div class="flex-shrink-0 mb-4 sm:mb-0 sm:mr-6">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-28 h-28 object-cover rounded-lg border border-gray-200">
                        </div>
                        <div class="flex-grow text-center sm:text-left">
                            <h3 class="cart-item-name text-lg md:text-xl mb-1"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p class="cart-item-price text-gray-600 mb-3">Unit Price: ₦<?php echo number_format($item['price'], 0); ?></p>
                            <div class="flex items-center justify-center sm:justify-start mb-4">
                                <label for="quantity-<?php echo htmlspecialchars($id); ?>" class="sr-only">Quantity for <?php echo htmlspecialchars($item['name']); ?></label>
                                <input type="number" id="quantity-<?php echo htmlspecialchars($id); ?>" name="quantities[<?php echo htmlspecialchars($id); ?>]"
                                    value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1"
                                    class="quantity-input focus:ring-2 focus:ring-[#F4A124] focus:border-transparent">
                            </div>
                        </div>
                        <div class="flex flex-col items-center sm:items-end mt-4 sm:mt-0 sm:ml-auto">
                            <p class="cart-item-subtotal text-xl mb-3">₦<?php echo number_format($subtotal, 0); ?></p>
                            <button type="button" onclick="confirmRemove('<?php echo htmlspecialchars($id); ?>')"
                                class="text-red-600 hover:text-red-800 transition-colors duration-200 flex items-center">
                                <i class="fas fa-trash-alt mr-1"></i> Remove
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="flex justify-center sm:justify-start">
                        <button type="submit" class="btn-secondary">
                            <i class="fas fa-sync-alt mr-2"></i> Update Quantities
                        </button>
                    </div>
                </div>

                <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-md h-fit sticky top-32">
                    <h3 class="text-2xl font-bold mb-6 text-[#092468] border-b pb-4">Order Summary</h3>
                    <div class="flex justify-between items-center text-lg font-semibold mb-6">
                        <span>Cart Total:</span>
                        <span class="text-2xl font-extrabold text-[#092468]">₦<?php echo number_format($grand_total, 0); ?></span>
                    </div>
                    <p class="text-sm text-gray-500 mb-6">Shipping and taxes calculated at checkout.</p>
                    <a href="checkout.php" class="btn-primary w-full text-center block">
                        <i class="fas fa-credit-card mr-2"></i> Proceed to Checkout
                    </a>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <?php
    try {
        if (file_exists('includes/footer.php')) {
            include 'includes/footer.php';
        } else {
            error_log('Missing footer.php');
        }
    } catch (Exception $e) {
        error_log('Footer error: ' . $e->getMessage());
    }
    ?>

    <script>
    function confirmRemove(productId) {
        Swal.fire({
            title: 'Remove Item?',
            text: "Are you sure you want to remove this item from your cart?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove it!',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'rounded-xl shadow-xl',
                confirmButton: 'px-6 py-2 rounded-lg font-semibold',
                cancelButton: 'px-6 py-2 rounded-lg font-semibold'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `cart.php?remove=${productId}`;
            }
        });
    }
    </script>
</body>

</html>