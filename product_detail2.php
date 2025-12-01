<?php
session_start();

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Base path - Adjust if your base directory is different (e.g., '/')
$base_path = '/'; 

// Load DB and navbar
try {
    if (file_exists('includes/db_connect.php')) {
        include 'includes/db_connect.php';
    } else {
        exit("<p>Error: Database connection file missing. Please ensure 'includes/db_connect.php' exists.</p>");
    }

    if (file_exists('includes/navbar.php')) {
        include 'includes/navbar.php';
    }
} catch (Exception $e) {
    error_log('Include error: ' . $e->getMessage());
    exit("<p>Internal error loading essential files. Please try again later.</p>");
}

// --- Fetch Product Details ---
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$product_category_path = ''; // To store the category path for "Back to Products" link

if ($product_id > 0) {
    // Join with product_categories to get a specific category_path if available
    // and select p.categories for display on the detail page as before.
    $stmt = $conn->prepare("SELECT 
                                p.id, 
                                p.name, 
                                p.description, 
                                p.sale_price, 
                                p.regular_price, 
                                p.images,
                                p.categories AS product_category_path_display,
                                pc.category_path AS filter_category_path -- To reconstruct the back link for filtering
                            FROM products p
                            LEFT JOIN product_categories pc ON p.id = pc.product_id
                            WHERE p.id = ?
                            LIMIT 1"); // Limit 1 as we expect only one product by ID
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    // If product found, store its category path for the back link
    if ($product && !empty($product['filter_category_path'])) {
        $product_category_path = $product['filter_category_path'];
    }
}

// Prepare image path
$main_image = 'https://placehold.co/600x400/e0e0e0/555555?text=No+Image';
if ($product && !empty($product['images'])) {
    $image_paths = explode(',', $product['images']);
    if (!empty($image_paths[0])) {
        $main_image = trim($image_paths[0]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo $product ? htmlspecialchars($product['name']) : 'Product Not Found'; ?> - Vava Furniture</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" crossorigin="anonymous" />

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            color: #1a202c; /* Default text color for Tailwind */
        }

        .hero-bg {
            background: linear-gradient(to bottom, rgba(9, 36, 104, 0.8), rgba(9, 36, 104, 0.5)),
                url('https://placehold.co/1200x400/3b82f6/ffffff?text=Product+Banner'); /* Placeholder banner */
            background-size: cover;
            background-position: center;
        }

        .hero-content {
            min-height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .animate-hero-title,
        .animate-hero-text,
        .animate-section-title,
        .animate-card {
            opacity: 0;
            animation: fadeInUp 0.6s ease-out forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .product-card {
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: scale(1.01);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .btn-primary,
        .btn-secondary {
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #F4A124; /* Yellow-Orange */
            color: white;
        }

        .btn-primary:hover {
            background: #d88b1c;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #2563eb; /* Blue-600 */
            color: white;
        }

        .btn-secondary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .color-swatch {
            width: 32px; /* Slightly smaller for better fit */
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: 0.2s;
            display: flex; /* For centering checkmark */
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .color-swatch.active {
            border-color: #F4A124;
            box-shadow: 0 0 0 2px rgba(244, 161, 36, 0.5); /* Outer glow */
        }
        .color-swatch:hover {
            border-color: #F4A124;
        }

        /* Checkmark for selected color */
        .color-swatch.active::after {
            content: '✔';
            position: absolute;
            color: white;
            font-size: 14px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        /* Specific filter styles for image manipulation based on color */
        /* These filters are a visual trick and assume a neutral base image */
        .filter-grey { filter: grayscale(50%) brightness(0.9); }
        .filter-beige { filter: hue-rotate(30deg) brightness(1.1) saturate(0.8); }
        .filter-navy { filter: hue-rotate(220deg) brightness(0.7) saturate(1.2); }
        .filter-black { filter: brightness(0.4) grayscale(80%); }
        .filter-white { filter: brightness(1.5) saturate(0.5); }
        .filter-red { filter: hue-rotate(0deg) saturate(1.5) brightness(0.9); }
        .filter-green { filter: hue-rotate(120deg) saturate(1.2) brightness(0.9); }
        .filter-blue { filter: hue-rotate(200deg) saturate(1.3) brightness(0.8); }
        .filter-brown { filter: hue-rotate(20deg) saturate(1.0) brightness(0.7); }
        .filter-cream { filter: hue-rotate(40deg) brightness(1.2) saturate(0.7); }

        #product-image {
            transition: transform 0.3s ease, filter 0.3s ease;
        }

        #product-image.zoomed {
            transform: scale(1.05); /* Slightly less aggressive zoom */
        }
    </style>
</head>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo $_SESSION['success']; ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?php echo $_SESSION['error']; ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['error']); endif; ?>
</script>

<body class="min-h-screen">
    <section class="hero-bg content-start relative w-full min-h-[300px] flex items-center justify-center overflow-hidden">
        <div class="hero-content relative z-10 text-white text-center px-4 py-16 md:py-24">
            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold animate-hero-title">
                <?php echo $product ? htmlspecialchars($product['name']) : 'Product Not Found'; ?>
            </h1>
            <p class="text-sm sm:text-lg mt-4 max-w-2xl mx-auto animate-hero-text">
                <?php echo $product ? 'Experience comfort and style with this exquisite piece.' : 'Sorry, the product you are looking for is not available.'; ?>
            </p>
        </div>
    </section>

    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <?php if ($product): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 bg-white rounded-xl shadow-lg p-6 md:p-10 animate-card">
            <div class="flex flex-col items-center">
                <img id="product-image" src="<?php echo htmlspecialchars($main_image); ?>"
                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                    class="w-full h-80 sm:h-96 object-contain rounded-md mb-4 border border-gray-200" loading="lazy"
                    onerror="this.src='https://placehold.co/600x400/e0e0e0/555555?text=Image+Error';">

                <div class="flex flex-wrap gap-3 mt-4 justify-center">
                    <span class="text-gray-700 font-medium mr-2 self-center">Color:</span>
                    <?php
                        // These are just example colors for the CSS filters.
                        // In a real system, you'd fetch available colors/variants from the DB.
                        $colors = ['grey', 'beige', 'navy', 'black', 'white', 'red', 'green', 'blue', 'brown', 'cream'];
                        $colorHex = [
                            'grey' => '#6B7280', 'beige' => '#F5F5DC', 'navy' => '#1E3A8A', 'black' => '#000',
                            'white' => '#FFF', 'red' => '#EF4444', 'green' => '#10B981', 'blue' => '#3B82F6',
                            'brown' => '#8B4513', 'cream' => '#FFFDD0'
                        ];
                        // Default selected color
                        $default_color = 'grey'; // You might want to get this from product data if available

                        foreach ($colors as $color):
                    ?>
                    <div class="color-swatch <?php echo $color === $default_color ? 'active' : ''; ?>"
                        data-color="<?php echo $color; ?>"
                        style="background-color: <?php echo $colorHex[$color]; ?><?php echo $color === 'white' ? '; border: 1px solid #ccc;' : ''; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex flex-col justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($product['name']); ?></h2>
                    <p class="text-sm text-gray-600 mb-4">
                        Category: <?php echo htmlspecialchars($product['product_category_path_display'] ?? 'N/A'); ?>
                    </p>

                    <div class="text-3xl font-bold mb-4 flex items-baseline">
                        <?php if (!empty($product['sale_price'])): ?>
                            <span class="text-red-600">₦<?php echo number_format($product['sale_price'], 0); ?></span>
                            <span class="text-gray-500 line-through ml-3 text-lg">₦<?php echo number_format($product['regular_price'], 0); ?></span>
                        <?php elseif (!empty($product['regular_price'])): ?>
                            <span class="text-gray-900">₦<?php echo number_format($product['regular_price'], 0); ?></span>
                        <?php else: ?>
                            <span class="text-gray-500 text-xl">Price not available</span>
                        <?php endif; ?>
                    </div>

                    <p class="text-gray-700 leading-relaxed mb-6">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?>
                    </p>
                </div>

                <div class="mt-auto pt-6 border-t border-gray-200">
                    <form action="add_to_cart.php" method="POST" class="flex flex-col sm:flex-row items-center gap-4">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                        <input type="hidden" name="selected_color" id="selected-color" value="<?php echo htmlspecialchars($default_color); ?>">

                        <div class="flex items-center space-x-2">
                            <label for="quantity" class="text-gray-700 font-medium">Quantity:</label>
                            <input type="number" name="quantity" id="quantity" value="1" min="1" max="99"
                                class="w-20 p-2 border border-gray-300 rounded-md text-center focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <button type="submit" class="btn-secondary px-6 py-3 rounded-lg font-semibold w-full sm:w-auto flex items-center justify-center gap-2">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                    </form>

                    <div class="mt-4">
                        <a href="furniture.php<?php echo !empty($product_category_path) ? '?category=' . urlencode($product_category_path) : ''; ?>"
                            class="btn-primary inline-block px-6 py-3 rounded-lg font-semibold text-center w-full sm:w-auto">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="text-center bg-white p-10 rounded-xl shadow-lg animate-card">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Product Not Found</h2>
            <p class="text-gray-600 mb-6">Sorry, the product you are looking for does not exist or has been removed.</p>
            <a href="furniture.php" class="btn-primary inline-block px-6 py-3 rounded-lg font-semibold">
                <i class="fas fa-home"></i> Go to Products Page
            </a>
        </div>
        <?php endif; ?>
    </section>

    <section class="relative text-white text-center py-16 bg-cover bg-center"
        style="background-image: url('https://placehold.co/1200x300/1e3a8a/ffffff?text=Complete+Your+Home');">
        <div class="absolute inset-0 bg-blue-900 bg-opacity-70"></div>
        <div class="relative z-10 px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold animate-section-title mb-4">Complete Your Home</h2>
            <p class="text-lg mt-4 max-w-2xl mx-auto animate-card">
                Add this piece to your cart or explore our full collection to furnish your dream space.
            </p>
            <a href="furniture.php"
                class="mt-6 inline-block btn-primary px-8 py-3 rounded-lg font-semibold animate-card"
                style="animation-delay: 0.2s;">
                <i class="fas fa-box"></i> Explore More Furniture
            </a>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Color swatch filter
        const swatches = document.querySelectorAll('.color-swatch');
        const productImage = document.getElementById('product-image');
        const selectedColorInput = document.getElementById('selected-color');

        swatches.forEach(swatch => {
            swatch.addEventListener('click', function() {
                // Remove all active states
                swatches.forEach(s => s.classList.remove('active'));
                this.classList.add('active');

                // Remove all previous filter classes
                const allFilters = [
                    'filter-grey', 'filter-beige', 'filter-navy', 'filter-black',
                    'filter-white', 'filter-red', 'filter-green', 'filter-blue',
                    'filter-brown', 'filter-cream'
                ];
                productImage.classList.remove(...allFilters);

                // Add new filter and a temporary zoom effect
                const newFilter = 'filter-' + this.dataset.color;
                productImage.classList.add(newFilter, 'zoomed');
                
                // Update hidden input for selected color
                selectedColorInput.value = this.dataset.color;

                setTimeout(() => productImage.classList.remove('zoomed'), 500);
            });
        });
    });
    </script>
</body>
</html>