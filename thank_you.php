<!--<//?php-->
<!--session_start();-->
<!--if (!isset($_SESSION['user_id'])) {-->
<!--    header("Location: auth/login.php");-->
<!--    exit();-->
<!--}-->
<!--include 'includes/navbar.php';-->
<!--?>-->

<!--<!DOCTYPE html>-->
<!--<html lang="en">-->

<!--<head>-->
<!--    <meta charset="UTF-8">-->
<!--    <title>Thank You - PishonServ</title>-->
<!--    <link rel="stylesheet" href="https://cdn.tailwindcss.com">-->
<!--</head>-->

<!--<body class="bg-gray-100 pt-32 min-h-screen text-center px-6">-->
<!--    <div class="max-w-2xl mx-auto bg-white shadow rounded p-8">-->
<!--        <h1 class="text-3xl font-bold text-[#092468] mb-4">Thank You for Your Order!</h1>-->
<!--        <p class="text-gray-700 text-lg mb-4">We've received your payment and your order is being processed.</p>-->
<!--        <p class="text-sm text-gray-500 mb-6">You will receive an email confirmation shortly.</p>-->
<!--        <a href="interior_deco.php"-->
<!--            class="bg-[#F4A124] hover:bg-[#d88b1c] text-white px-6 py-2 rounded font-semibold">Continue Shopping</a>-->
<!--    </div>-->

<!--    <//?php include 'includes/footer.php'; ?>-->
<!--</body>-->

<!--</html>-->

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

include 'includes/navbar.php';

// Get order ID from URL parameter (e.g., thank_you.php?order_id=XYZ)
// You should ensure this is securely passed and validated in a real application
$order_id = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : 'your recent order'; // Fallback text

// Clear the cart after successful checkout (IMPORTANT!)
// This should ideally happen AFTER the order is successfully saved in the DB
// and payment is confirmed in your process_payment.php / success_callback.php
// For simplicity, placing it here for now, but adjust based on your flow.
unset($_SESSION['cart']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - Vava Furniture</title>
    <link rel="icon" href="/public/images/favicon.png"> <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(to bottom right, #f7f9fc, #e0e6ed); /* Soft gradient background */
            color: #1a202c;
        }
        .card {
            background-color: #ffffff;
            border-radius: 1rem; /* More rounded corners */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); /* Enhanced shadow */
            padding: 2.5rem; /* Increased padding */
            animation: fadeIn 0.8s ease-out; /* Simple fade-in animation */
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background-color: #10B981; /* Green color for success */
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1.5rem auto; /* Center the icon */
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-primary {
            background-color: #F4A124;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 4px 10px rgba(244, 161, 36, 0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary:hover {
            background-color: #d88b1c;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(244, 161, 36, 0.3);
        }
        .btn-secondary {
            background-color: #e2e8f0; /* Light gray */
            color: #2d3748;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .btn-secondary:hover {
            background-color: #cbd5e0;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="pt-32 min-h-screen text-center px-6 flex flex-col items-center">
    <?php // The navbar is included here ?>

    <div class="max-w-xl mx-auto card mt-12 mb-auto">
        <div class="icon-circle">
            <i class="fas fa-check"></i>
        </div>
        <h1 class="text-4xl font-extrabold text-[#092468] mb-4">Order Confirmed!</h1>
        <p class="text-gray-700 text-lg mb-2">Thank you for your purchase from PishonServ Furniture.</p>
        <p class="text-gray-700 text-lg mb-4">Your order <span class="font-bold text-[#F4A124]">#<?php echo $order_id; ?></span> has been successfully placed.</p>
        <p class="text-md text-gray-600 mb-6">You will receive an email confirmation with details and tracking information shortly.</p>

        <div class="flex flex-col sm:flex-row justify-center gap-4 mt-8">
            <a href="furniture.php" class="btn-primary">
                <i class="fas fa-shopping-bag mr-2"></i> Continue Shopping
            </a>
            <!--<a href="user/orders.php" class="btn-secondary"> <i class="fas fa-receipt mr-2"></i> View Your Orders-->
            <!--</a>-->
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>

</html>