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
$additional_images = []; // To store other images for a gallery
if ($product && !empty($product['images'])) {
    $image_paths = array_map('trim', explode(',', $product['images']));
    if (!empty($image_paths[0])) {
        $main_image = $image_paths[0];
        // If there are more images, add them to additional_images
        $additional_images = array_slice($image_paths, 1);
    }
}

// Calculate discount percentage if applicable
$discount_percentage = 0;
if ($product && !empty($product['sale_price']) && !empty($product['regular_price']) && $product['regular_price'] > $product['sale_price']) {
    $discount_percentage = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100);
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" crossorigin="anonymous" />

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            color: #1a202c;
        }

        /* Custom styles for consistency with WooCommerce look */
        .woocommerce-price ins .amount {
            color: #1a202c; /* Ensure price color is visible */
            font-weight: 600;
        }
        .woocommerce-price del .amount {
            color: #6b7280; /* Gray for strikethrough price */
        }

        .product-gallery-thumbnail.active {
            border-color: #2563eb; /* Active thumbnail border */
            box-shadow: 0 0 0 2px #2563eb;
        }

        /* Animations */
        .animate-fadeIn {
            animation: fadeIn 0.8s ease-out forwards;
        }

        .animate-slideInUp {
            animation: slideInUp 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Modern button styles */
        .btn-primary {
            background-color: #F4A124; /* Yellow-Orange */
            color: white;
            transition: all 0.2s ease-in-out;
        }
        .btn-primary:hover {
            background-color: #d88b1c; /* Darker yellow-orange */
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-secondary {
            background-color: #2563eb; /* Blue-600 */
            color: white;
            transition: all 0.2s ease-in-out;
        }
        .btn-secondary:hover {
            background-color: #1d4ed8; /* Darker blue */
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Discount Badge */
        .discount-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background-color: #ef4444; /* Red-500 */
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem; /* rounded-md */
            font-weight: 700; /* font-bold */
            font-size: 0.875rem; /* text-sm */
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Styles for the rendered HTML content (h3, ul, li) within the description */
        /* These are basic styles if you don't use @tailwindcss/typography plugin */
        .prose h3 {
            font-size: 1.5rem; /* Equivalent to text-2xl */
            font-weight: 700; /* Equivalent to font-bold */
            margin-top: 1.5em; /* Spacing above headings */
            margin-bottom: 0.5em; /* Spacing below headings */
            color: #1a202c;
        }

        .prose ul {
            list-style-type: disc; /* Default bullet points */
            margin-left: 1.25em; /* Indent lists */
            padding-left: 0;
            margin-top: 1em;
            margin-bottom: 1em;
        }

        .prose ul li {
            margin-bottom: 0.5em; /* Space between list items */
        }

        .prose p {
            margin-bottom: 1em; /* Space between paragraphs */
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
    <?php include 'includes/navbar.php'; // Ensure navbar is included here for all pages ?>

    <section class="container mx-auto py-8 px-4 sm:px-6 lg:px-8 mt-4">
        <?php if ($product): ?>
        <nav class="woocommerce-breadcrumb text-sm text-gray-600 mb-6 flex items-center space-x-2 animate-fadeIn">
            <a href="<?php echo $base_path; ?>" class="hover:text-blue-600 transition duration-200">Home</a>
            <span class="text-gray-400">/</span>
            <a href="furniture.php<?php echo !empty($product_category_path) ? '?category=' . urlencode($product_category_path) : ''; ?>" class="hover:text-blue-600 transition duration-200">
                <?php echo htmlspecialchars($product['product_category_path_display'] ?? 'Products'); ?>
            </a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($product['name']); ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 bg-white rounded-xl shadow-lg p-6 md:p-10 animate-fadeIn">
            <div class="woocommerce-product-gallery relative flex flex-col md:flex-row gap-4">
                <?php if ($discount_percentage > 0): ?>
                    <div class="discount-badge">
                        -<?php echo $discount_percentage; ?>%
                    </div>
                <?php endif; ?>
                <div class="flex-shrink-0 order-2 md:order-1 md:w-24 flex md:flex-col gap-2 overflow-x-auto md:overflow-y-auto pb-2 md:pb-0">
                    <img src="<?php echo htmlspecialchars($main_image); ?>" alt="Main Product Image"
                        class="product-gallery-thumbnail w-20 h-20 object-cover rounded-md border-2 border-blue-600 cursor-pointer active shadow-sm transition-all duration-200 hover:scale-105"
                        onclick="changeMainImage(this)" loading="lazy"
                        onerror="this.src='https://placehold.co/200x200/e0e0e0/555555?text=Image+Error';">
                    <?php foreach ($additional_images as $img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="Product Thumbnail"
                            class="product-gallery-thumbnail w-20 h-20 object-cover rounded-md border-2 border-transparent hover:border-blue-300 cursor-pointer shadow-sm transition-all duration-200 hover:scale-105"
                            onclick="changeMainImage(this)" loading="lazy"
                            onerror="this.src='https://placehold.co/200x200/e0e0e0/555555?text=Image+Error';">
                    <?php endforeach; ?>
                </div>
                <div class="flex-grow order-1 md:order-2 flex justify-center items-center relative">
                    <img id="product-main-image" src="<?php echo htmlspecialchars($main_image); ?>"
                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                        class="w-full max-w-lg h-auto max-h-[500px] object-contain rounded-lg border border-gray-200 shadow-md transition-transform duration-300" loading="lazy"
                        onerror="this.src='https://placehold.co/600x400/e0e0e0/555555?text=Image+Error';">
                </div>
            </div>

            <div class="summary entry-summary flex flex-col justify-start space-y-4 lg:pl-8 animate-slideInUp">
                <h1 class="product_title entry-title text-4xl font-extrabold text-gray-900 mb-2 leading-tight">
                    <?php echo htmlspecialchars($product['name']); ?>
                </h1>

                <div class="woocommerce-product-rating flex items-center text-sm text-gray-600">
                    <div class="star-rating" role="img" aria-label="Rated 4 out of 5">
                        <span style="width:80%;" class="text-yellow-400">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i>
                        </span> </div>
                    <a href="#reviews" class="woocommerce-review-link ml-2 text-blue-600 hover:underline transition duration-200">(<span class="count">0</span> customer reviews)</a>
                </div>

                <p class="price woocommerce-price text-4xl font-extrabold mb-4 flex items-baseline">
                    <?php if (!empty($product['sale_price']) && $product['sale_price'] < $product['regular_price']): ?>
                        <del aria-hidden="true" class="mr-3 text-gray-500 line-through text-2xl">
                            <span class="woocommerce-Price-amount amount">₦<?php echo number_format($product['regular_price'], 0); ?></span>
                        </del>
                        <ins>
                            <span class="woocommerce-Price-amount amount text-red-600">₦<?php echo number_format($product['sale_price'], 0); ?></span>
                        </ins>
                    <?php elseif (!empty($product['regular_price'])): ?>
                        <span class="woocommerce-Price-amount amount text-gray-900">₦<?php echo number_format($product['regular_price'], 0); ?></span>
                    <?php else: ?>
                        <span class="text-gray-500 text-xl">Price not available</span>
                    <?php endif; ?>
                </p>

                <div class="woocommerce-product-details__short-description text-gray-700 leading-relaxed mb-6 prose max-w-none">
                    <?php echo nl2br(html_entity_decode($product['description'] ?? 'No description available.', ENT_QUOTES, 'UTF-8')); ?>
                </div>

                <form class="cart flex flex-col sm:flex-row items-stretch sm:items-center gap-4 woocommerce-add-to-cart" action="add_to_cart.php" method="POST">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">

                    <div class="quantity flex items-center border border-gray-300 rounded-lg overflow-hidden shadow-sm">
                        <button type="button" class="minus bg-gray-100 hover:bg-gray-200 p-3 text-gray-700 font-bold text-lg transition duration-200" onclick="changeQuantity(-1)">-</button>
                        <input type="number" id="quantity" class="input-text qty text-center w-20 p-2 font-semibold text-gray-800 focus:ring-blue-500 focus:border-blue-500 border-x border-gray-300" step="1" min="1" max="99" name="quantity" value="1" title="Qty" size="4" inputmode="numeric">
                        <button type="button" class="plus bg-gray-100 hover:bg-gray-200 p-3 text-gray-700 font-bold text-lg transition duration-200" onclick="changeQuantity(1)">+</button>
                    </div>
                   
                    <button type="submit" name="add-to-cart" value="<?php echo htmlspecialchars($product['id']); ?>"
                        class="single_add_to_cart_button button alt btn-secondary px-8 py-3 rounded-lg font-bold text-lg w-full sm:w-auto flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                </form>

                <div class="product_meta text-sm text-gray-600 mt-4">
                    <span class="posted_in">Category: <a href="furniture.php?category=<?php echo urlencode($product['product_category_path_display'] ?? ''); ?>" rel="tag" class="text-blue-600 hover:underline transition duration-200"><?php echo htmlspecialchars($product['product_category_path_display'] ?? 'N/A'); ?></a></span>
                </div>

                <div class="woocommerce-share text-gray-700 mt-6 pt-4 border-t border-gray-100">
                    <p class="font-semibold mb-3 text-lg">Share this product:</p>
                    <div class="flex space-x-4">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="text-gray-500 hover:text-blue-700 transform hover:scale-110 transition duration-200" title="Share on Facebook"><i class="fab fa-facebook-f text-2xl"></i></a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=Check%20out%20this%20product:%20<?php echo urlencode($product['name']); ?>" target="_blank" class="text-gray-500 hover:text-blue-400 transform hover:scale-110 transition duration-200" title="Share on Twitter"><i class="fab fa-twitter text-2xl"></i></a>
                        <a href="https://pinterest.com/pin/create/button/?url=<?php echo urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&media=<?php echo urlencode($main_image); ?>&description=<?php echo urlencode($product['name']); ?>" target="_blank" class="text-gray-500 hover:text-red-600 transform hover:scale-110 transition duration-200" title="Share on Pinterest"><i class="fab fa-pinterest text-2xl"></i></a>
                        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&title=<?php echo urlencode($product['name']); ?>&summary=<?php echo urlencode(strip_tags($product['description'])); ?>" target="_blank" class="text-gray-500 hover:text-blue-800 transform hover:scale-110 transition duration-200" title="Share on LinkedIn"><i class="fab fa-linkedin-in text-2xl"></i></a>
                        <a href="mailto:?subject=Check%20out%20this%20product&body=I%20found%20this%20amazing%20product:%20<?php echo urlencode($product['name']); ?>%20-%20<?php echo urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" class="text-gray-500 hover:text-gray-800 transform hover:scale-110 transition duration-200" title="Share via Email"><i class="fas fa-envelope text-2xl"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="woocommerce-tabs wc-tabs-wrapper mt-12 bg-white rounded-xl shadow-lg p-6 md:p-10 animate-slideInUp" style="animation-delay: 0.3s;">
            <ul class="tabs wc-tabs flex border-b border-gray-200">
                <li class="description_tab active mr-4">
                    <a href="#tab-description" class="block py-3 px-4 text-gray-700 font-bold border-b-2 border-blue-600 -mb-px hover:text-blue-700 transition duration-200">Description</a>
                </li>
                </ul>
            <div class="woocommerce-Tabs-panel woocommerce-Tabs-panel--description panel entry-content wc-tab" id="tab-description" role="tabpanel" aria-labelledby="tab-title-description">
                <h2 class="text-2xl font-bold text-gray-900 mt-6 mb-4">Product Details</h2>
                <div class="text-gray-700 leading-relaxed prose max-w-none">
                    <?php echo nl2br(html_entity_decode($product['description'] ?? 'No detailed description available.', ENT_QUOTES, 'UTF-8')); ?>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="text-center bg-white p-10 rounded-xl shadow-lg animate-fadeIn">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Product Not Found</h2>
            <p class="text-gray-600 mb-6">Sorry, the product you are looking for does not exist or has been removed.</p>
            <a href="furniture.php" class="btn-primary inline-block px-8 py-3 rounded-lg font-semibold text-lg hover:shadow-lg transition duration-200">
                <i class="fas fa-home mr-2"></i> Go to Products Page
            </a>
        </div>
        <?php endif; ?>
    </section>

    <section class="relative text-white text-center py-16 bg-cover bg-center"
        style="background-image: url('https://placehold.co/1200x300/1e3a8a/ffffff?text=Explore+More+Furniture');">
        <div class="absolute inset-0 bg-blue-900 bg-opacity-70"></div>
        <div class="relative z-10 px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-extrabold animate-fadeIn mb-4">Discover More Furniture</h2>
            <p class="text-lg mt-4 max-w-2xl mx-auto animate-fadeIn" style="animation-delay: 0.2s;">
                Find the perfect additions to your home from our extensive collection.
            </p>
            <a href="furniture.php"
                class="mt-6 inline-block bg-yellow-500 hover:bg-yellow-600 text-white px-8 py-3 rounded-lg font-bold text-lg animate-fadeIn shadow-md hover:shadow-lg transition duration-200"
                style="animation-delay: 0.4s;">
                <i class="fas fa-box mr-2"></i> View All Furniture
            </a>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script>
        function changeMainImage(thumbnail) {
            const mainImage = document.getElementById('product-main-image');
            mainImage.src = thumbnail.src;

            // Remove active class from all thumbnails
            document.querySelectorAll('.product-gallery-thumbnail').forEach(thumb => {
                thumb.classList.remove('active', 'border-blue-600');
                thumb.classList.add('border-transparent', 'hover:border-blue-300');
            });
            // Add active class to the clicked thumbnail
            thumbnail.classList.add('active', 'border-blue-600');
            thumbnail.classList.remove('border-transparent', 'hover:border-blue-300');
        }

        function changeQuantity(change) {
            const quantityInput = document.getElementById('quantity');
            let currentQuantity = parseInt(quantityInput.value);
            let newQuantity = currentQuantity + change;

            if (newQuantity < 1) {
                newQuantity = 1;
            } else if (newQuantity > 99) { // Set a reasonable max quantity
                newQuantity = 99;
            }
            quantityInput.value = newQuantity;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initial active state for the first thumbnail if there are multiple images
            const firstThumbnail = document.querySelector('.product-gallery-thumbnail');
            if (firstThumbnail) {
                firstThumbnail.classList.add('active', 'border-blue-600');
                firstThumbnail.classList.remove('border-transparent', 'hover:border-blue-300');
            }
        });
    </script>
</body>
</html>