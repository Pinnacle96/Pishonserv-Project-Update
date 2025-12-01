<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// echo ""; // Debug point 1

$base_path = '/';

if (!isset($_SESSION['user_id'])) {
    // echo ""; // Debug point 2
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: auth/login.php");
    exit();
}

// echo ""; // Debug point 3

$cart = $_SESSION['cart'] ?? [];
// echo ""; // Debug point 4
//echo '<pre>'; var_dump($cart); echo '</pre>'; // Check what's actually in $cart

if (empty($cart)) {
   // echo ""; // Debug point 5
    $_SESSION['error'] = "Your cart is empty. Please add items before checking out.";
    header("Location: cart.php");
    exit();
}

// echo ""; // Debug point 6

include 'includes/db_connect.php';
// Add a check for db_connect just in case
if (!isset($conn)) {
    //echo ""; // Debug point 7
    // You might want to display an error or redirect here
    // For now, let's just log and continue to see if other parts load
    error_log("DB connection \$conn is not set in checkout.php after db_connect.php include.");
}

include 'includes/navbar.php';
// echo ""; // Debug point 8


// // ... rest of your original code ...

// // Get delivery fees from DB
// // ... (your existing PHP logic) ...

// // ... (your HTML structure) ...

// echo ""; // Debug point 9


// Fetch user's existing details if available (for pre-filling form)
$user_id = $_SESSION['user_id'];
$user_email = '';
$user_name = '';
$user_phone = ''; // Assuming phone might be stored in user table

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT email, name, phone FROM users WHERE id = ?"); // Adjust 'fullname' and 'phone' to your actual user table columns
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    if ($user_row = $user_result->fetch_assoc()) {
        $user_email = htmlspecialchars($user_row['email'] ?? '');
        $user_name = htmlspecialchars($user_row['name'] ?? '');
        $user_phone = htmlspecialchars($user_row['phone'] ?? '');
    }
}

// Get delivery fees from DB
$delivery_fees = [];
if (isset($conn)) {
    $delivery_result = $conn->query("SELECT state, fee FROM delivery_fees ORDER BY state ASC");
    if ($delivery_result) {
        while ($row = $delivery_result->fetch_assoc()) {
            $delivery_fees[htmlspecialchars($row['state'])] = (float)$row['fee'];
        }
    } else {
        error_log("Failed to fetch delivery fees: " . $conn->error);
    }
} else {
    error_log("Database connection not established for delivery fees.");
}


// Calculate subtotal
$subtotal = 0;
foreach ($cart as $item) {
    // Ensure numeric values before multiplication
    $price = is_numeric($item['price']) ? (float)$item['price'] : 0;
    $quantity = is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
    $subtotal += $price * $quantity;
}


function log_payment_error($message)
{
    $log_file = __DIR__ . '/payment/payment_error.log';
    $date = date('[Y-m-d H:i:s]');
    file_put_contents($log_file, "$date $message\n", FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Checkout - Vava Furniture</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?php echo $base_path; ?>public/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5; /* Light gray background */
            color: #1a202c; /* Dark text for readability */
        }

        .input-field {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0; /* Light gray border */
            border-radius: 0.5rem; /* Rounded corners */
            font-size: 1rem;
            line-height: 1.5;
            color: #2d3748;
            background-color: #ffffff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: #F4A124; /* Primary color on focus */
            box-shadow: 0 0 0 3px rgba(244, 161, 36, 0.2); /* Light shadow on focus */
        }

        /* Styling for payment method radio buttons */
        .payment-method-label {
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-center: center;
            cursor: pointer;
            background-color: #ffffff;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .payment-method-label:hover {
            border-color: #F4A124; /* Hover state for border */
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .payment-method-label.selected {
            border-color: #F4A124; /* Primary color when selected */
            box-shadow: 0 0 0 4px rgba(244, 161, 36, 0.3); /* Stronger shadow for selected */
        }

        .payment-method-label input[type="radio"]:checked + img {
            filter: grayscale(0) brightness(1.05); /* Slightly brighter when selected */
            transform: scale(1.05); /* Slight scale animation */
        }

        .payment-method-label img {
            max-height: 40px;
            width: auto;
            object-fit: contain;
            filter: grayscale(0.5); /* Slightly desaturated when not selected */
            transition: all 0.2s ease-in-out;
        }

        .btn-primary {
            background-color: #F4A124;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 4px 10px rgba(244, 161, 36, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary:hover {
            background-color: #d88b1c;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(244, 161, 36, 0.3);
        }

        .summary-card {
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); /* Softer, deeper shadow */
            padding: 2rem;
        }

        /* Ensure sticky summary works well */
        @media (min-width: 1024px) { /* On large screens and up */
            .sticky-summary {
                position: sticky;
                top: 8rem; /* Adjust based on navbar height */
            }
        }
    </style>
</head>

<body class="min-h-screen pt-28">
    <div class="container mx-auto px-4 lg:px-8 xl:px-16 py-10">
        <h1 class="text-4xl lg:text-5xl font-extrabold text-center text-[#092468] mb-12">Secure Checkout</h1>

        <form id="checkout-form" onsubmit="initiatePayment(event)">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <div class="lg:col-span-2 space-y-8">
                    <div class="summary-card">
                        <h2 class="text-2xl font-bold mb-6 text-[#092468] border-b pb-4">Delivery Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="delivery_name" class="block text-gray-700 text-sm font-semibold mb-2">Full Name</label>
                                <input type="text" id="delivery_name" name="delivery_name" placeholder="John Doe" value="<?php echo $user_name; ?>" required
                                    class="input-field">
                            </div>
                            <div>
                                <label for="delivery_phone" class="block text-gray-700 text-sm font-semibold mb-2">Phone Number</label>
                                <input type="tel" id="delivery_phone" name="delivery_phone" placeholder="e.g., 08012345678" value="<?php echo $user_phone; ?>" required
                                    class="input-field">
                            </div>
                            <div class="md:col-span-2">
                                <label for="delivery_email" class="block text-gray-700 text-sm font-semibold mb-2">Email Address (for order updates)</label>
                                <input type="email" id="delivery_email" name="delivery_email" placeholder="you@example.com" value="<?php echo $user_email; ?>"
                                    class="input-field">
                            </div>
                            <div class="md:col-span-2">
                                <label for="delivery_address" class="block text-gray-700 text-sm font-semibold mb-2">Delivery Address</label>
                                <input type="text" id="delivery_address" name="delivery_address" placeholder="Street Address, Apt/Suite, etc." required
                                    class="input-field">
                            </div>
                            <div>
                                <label for="delivery_city" class="block text-gray-700 text-sm font-semibold mb-2">City</label>
                                <input type="text" id="delivery_city" name="delivery_city" placeholder="Lagos" required
                                    class="input-field">
                            </div>
                            <div>
                                <label for="delivery_state" class="block text-gray-700 text-sm font-semibold mb-2">State</label>
                                <select name="delivery_state" id="delivery_state" required class="input-field"
                                    onchange="updateDeliveryFee()">
                                    <option value="">Select State</option>
                                    <?php foreach ($delivery_fees as $state => $fee): ?>
                                        <option value="<?php echo $state; ?>" data-fee="<?php echo $fee; ?>"><?php echo $state; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="delivery_country" class="block text-gray-700 text-sm font-semibold mb-2">Country</label>
                                <input type="text" id="delivery_country" name="delivery_country" value="Nigeria" readonly required
                                    class="input-field bg-gray-100 cursor-not-allowed">
                            </div>
                        </div>
                    </div>

                    <div class="summary-card">
                        <h2 class="text-2xl font-bold mb-6 text-[#092468] border-b pb-4">Choose Payment Method</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <?php
                            $gateways = [
                                "paystack" => "paystack.png",
                                "flutterwave" => "flutterwave.png"
                                // Add more as needed: "opay" => "opay.png", "palmpay" => "palmpay.png"
                            ];
                            foreach ($gateways as $key => $icon): ?>
                                <label class="payment-method-label">
                                    <input type="radio" name="payment_method" value="<?php echo $key; ?>" class="hidden">
                                    <img src="<?php echo $base_path . 'public/icons/' . $icon; ?>"
                                        alt="<?php echo ucfirst($key); ?>" title="<?php echo ucfirst($key); ?>">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="summary-card sticky-summary">
                        <h2 class="text-2xl font-bold mb-6 text-[#092468] border-b pb-4">Order Summary</h2>

                        <div class="mb-6 max-h-60 overflow-y-auto pr-2">
                            <?php foreach ($cart as $item_id => $item): ?>
                                <div class="flex items-start mb-4 pb-2 border-b border-gray-100 last:border-b-0 last:pb-0">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"
                                        class="w-16 h-16 object-cover rounded mr-4 flex-shrink-0">
                                    <div class="flex-grow">
                                        <p class="font-semibold text-gray-800 text-md leading-tight"><?php echo htmlspecialchars($item['name']); ?></p>
                                        <p class="text-sm text-gray-600">Qty: <?php echo htmlspecialchars($item['quantity']); ?> x ₦<?php echo number_format($item['price'], 0); ?></p>
                                        <p class="font-bold text-gray-800">₦<?php echo number_format($item['price'] * $item['quantity'], 0); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="space-y-3 text-lg text-gray-800">
                            <div class="flex justify-between">
                                <span>Subtotal:</span>
                                <span class="font-semibold">₦<?php echo number_format($subtotal, 0); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Delivery Fee:</span>
                                <span class="font-semibold">₦<span id="delivery-fee">0</span></span>
                            </div>
                            <input type="hidden" id="delivery_fee_input" name="delivery_fee" value="0">
                            <hr class="my-4 border-gray-200">
                            <div class="flex justify-between items-center text-xl font-bold text-[#092468]">
                                <span>Total:</span>
                                <span>₦<span id="grand-total"><?php echo number_format($subtotal, 0); ?></span></span>
                            </div>
                        </div>

                        <input type="hidden" name="order_description" value="Vava Furniture Order Payment">

                        <button type="submit" id="pay-button"
                            class="btn-primary w-full mt-8 py-3 text-lg">
                            <i class="fas fa-money-check-alt mr-2"></i> Pay Now
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php try {
        if (file_exists('includes/footer.php')) include 'includes/footer.php';
    } catch (Exception $e) {
        error_log($e->getMessage());
    } ?>
    <script src="https://checkout.flutterwave.com/v3.js"></script>
    <script>
        const baseSubtotal = <?php echo $subtotal; ?>;
        const deliveryFees = <?php echo json_encode($delivery_fees); ?>;
        const payButton = document.getElementById('pay-button');

        function updateDeliveryFee() {
            const stateSelect = document.getElementById("delivery_state");
            const selectedState = stateSelect.value;
            const fee = deliveryFees[selectedState] !== undefined ? deliveryFees[selectedState] : 0;

            document.getElementById("delivery-fee").textContent = fee.toLocaleString();
            document.getElementById("delivery_fee_input").value = fee;
            document.getElementById("grand-total").textContent = (baseSubtotal + fee).toLocaleString();
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initial update of delivery fee on page load if a state is pre-selected
            updateDeliveryFee();

            const radios = document.querySelectorAll('input[name="payment_method"]');
            const form = document.getElementById('checkout-form');

            // Add change listener to all radio buttons
            radios.forEach(input => {
                input.addEventListener('change', function() {
                    // Remove 'selected' class from all labels
                    document.querySelectorAll('.payment-method-label').forEach(label =>
                        label.classList.remove('selected')
                    );
                    // Add 'selected' class to the parent label of the checked radio
                    this.closest('label').classList.add('selected');
                });
            });

            // Handle form submission and payment initiation
            form.addEventListener('submit', function(e) {
                const selected = document.querySelector('input[name="payment_method"]:checked');
                if (!selected) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Payment Method Required',
                        text: 'Please choose a payment option before continuing.',
                        confirmButtonColor: '#F4A124'
                    });
                }
                 // Basic client-side validation for required fields
                 const requiredFields = form.querySelectorAll('[required]');
                for (const field of requiredFields) {
                    if (!field.value.trim()) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Missing Information',
                            text: `Please fill in your ${field.previousElementSibling ? field.previousElementSibling.textContent.toLowerCase() : field.placeholder.toLowerCase()}.`,
                            confirmButtonColor: '#F4A124'
                        });
                        field.focus();
                        return;
                    }
                }
            });
        });

        async function initiatePayment(event) {
            event.preventDefault(); // Prevent default form submission

            payButton.disabled = true;
            payButton.textContent = 'Processing...';
            payButton.classList.add('opacity-75', 'cursor-not-allowed');

            const form = document.getElementById('checkout-form');
            const formData = new FormData(form);

            try {
                const res = await fetch('payment/process_payment.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                console.log('Payment Response:', data);

                if (!data.success) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Payment initialization failed.'
                    });
                    return;
                }

                if (data.payment_method === 'paystack') {
                    const handler = PaystackPop.setup({
                        key: data.public_key,
                        email: data.email,
                        amount: data.amount, // in kobo
                        currency: "NGN",
                        ref: data.reference,
                        metadata: {
                            custom_fields: [{
                                display_name: "Customer Name",
                                variable_name: "delivery_name",
                                value: data.name || 'Guest'
                            }],
                            delivery_address: data.delivery_address, // Pass full address
                            delivery_phone: data.delivery_phone,
                            delivery_city: data.delivery_city,
                            delivery_state: data.delivery_state,
                        },
                        callback: function(response) {
                            window.location.href = 'payment/success_callback.php?ref=' +
                                encodeURIComponent(response.reference) + '&gateway=paystack';
                        },
                        onClose: function() {
                            Swal.fire({
                                icon: 'info',
                                title: 'Payment Cancelled',
                                text: 'You closed the payment window. You can try again.',
                                confirmButtonColor: '#F4A124'
                            });
                        }
                    });
                    handler.openIframe();
                } else if (data.payment_method === 'flutterwave') {
                    if (!data.public_key || !data.reference) {
                        console.error("Missing required Flutterwave values", data);
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Payment Info',
                            text: 'Flutterwave credentials are incomplete or invalid.'
                        });
                        return;
                    }

                    const flutterAmount = parseFloat(data.amount); // amount already in Naira

                    console.log("Flutterwave Payload", {
                        key: data.public_key,
                        tx_ref: data.reference,
                        amount: flutterAmount,
                        email: data.email,
                        name: data.name
                    });

                    try {
                        FlutterwaveCheckout({
                            public_key: data.public_key,
                            tx_ref: data.reference,
                            amount: flutterAmount,
                            currency: "NGN",
                            customer: {
                                email: data.email,
                                name: data.name || 'Guest',
                                phone_number: data.phone // Added phone number
                            },
                            customizations: {
                                title: "Vava Furniture Payment", // Updated title
                                description: data.description,
                                logo: "<?php echo $base_path; ?>public/images/favicon.png" // Use your actual logo path
                            },
                            callback: function(response) {
                                if (response.status === "successful") {
                                    window.location.href = 'payment/success_callback.php?ref=' +
                                        encodeURIComponent(response.tx_ref) + '&gateway=flutterwave';
                                } else {
                                    window.location.href = 'payment/failure_callback.php?ref=' +
                                        encodeURIComponent(response.tx_ref) + '&gateway=flutterwave';
                                }
                            },
                            onclose: function() {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Payment Cancelled',
                                    text: 'You closed the payment window. You can try again.',
                                    confirmButtonColor: '#F4A124'
                                });
                            }
                        });
                    } catch (err) {
                        console.error("⚠️ FlutterwaveCheckout failed to initialize", err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Payment Initialization Error',
                            text: err.message || 'Something went wrong initializing payment. Please try again later.'
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Unsupported Method',
                        text: 'This payment method is not yet implemented or selected.'
                    });
                }
            } catch (err) {
                console.error('Payment Init Error:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Unable to connect to server. Please check your internet connection and try again.'
                });
            } finally {
                payButton.disabled = false;
                payButton.textContent = 'Pay Now';
                payButton.classList.remove('opacity-75', 'cursor-not-allowed');
            }
        }
    </script>

</body>

</html>