<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    session_start();
    include 'includes/db_connect.php';
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    $property_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$property_id) {
        throw new Exception("Invalid property ID");
    }
    
    // Fetch property details
    $query = "SELECT p.*, u.name AS agent_name, u.profile_image AS agent_image, u.phone AS agent_phone
              FROM properties p
              JOIN users u ON p.owner_id = u.id
              WHERE p.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $property = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$property) {
        die("Property not found.");
    }

    // Process images
    $images = !empty($property['images']) ? explode(',', $property['images']) : ['default.jpg'];
    $firstImage = 'https://pishonserv.com/public/uploads/' . rawurlencode($images[0]);

    // Check if property is in wishlist
    $in_wishlist = false;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND property_id = ?");
        $stmt->bind_param("ii", $user_id, $property_id);
        $stmt->execute();
        $in_wishlist = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }

    // Get superadmin contact info
    $superadmin = $conn->query("SELECT name, phone, email FROM users WHERE role = 'superadmin' LIMIT 1")->fetch_assoc();

    // Helper function to format description
    function formatDescription($description) {
        $paragraphs = explode("\n\n", trim($description));
        $formatted = '<div class="mt-4 text-gray-700">';
        foreach ($paragraphs as $para) {
            $formatted .= '<p class="mt-2">' . htmlspecialchars($para) . '</p>';
        }
        $formatted .= '</div>';
        return $formatted;
    }

    // Helper function for price label
    function getPriceLabel($listing_type, $price_frequency) {
        if ($listing_type === 'for_sale') return '';
        if (!empty($price_frequency)) return ' per ' . str_replace('_', ' ', $price_frequency);
        return ' per unit';
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary Meta Tags -->
    <title><?php echo htmlspecialchars($property['title']); ?> | PishonServ Properties</title>
    <meta name="description" content="<?php echo htmlspecialchars(substr($property['description'], 0, 160)); ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://pishonserv.com/property.php?id=<?php echo $property['id']; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($property['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr($property['description'], 0, 160)); ?>">
    <meta property="og:image" content="<?php echo $firstImage; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://pishonserv.com/property.php?id=<?php echo $property['id']; ?>">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($property['title']); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars(substr($property['description'], 0, 160)); ?>">
    <meta property="twitter:image" content="<?php echo $firstImage; ?>">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f5f7fa] text-[#092468] min-h-screen">

<?php include 'includes/navbar.php'; ?>

<section class="container mx-auto pt-40 py-12 px-4 md:px-10 lg:px-16">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Image Slider -->
        <div class="relative w-full h-[400px] overflow-hidden rounded-lg shadow-lg">
            <div class="slider" id="property-slider">
                <?php foreach ($images as $index => $image): ?>
                    <img src="public/uploads/<?php echo htmlspecialchars($image); ?>"
                        class="w-full h-[400px] object-cover slider-image <?php echo $index === 0 ? '' : 'hidden'; ?>"
                        alt="<?php echo htmlspecialchars($property['title']); ?>">
                <?php endforeach; ?>
            </div>
            <?php if (count($images) > 1): ?>
                <button class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-gray-800 text-white p-2 rounded-full prev hover:bg-gray-600">‹</button>
                <button class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-gray-800 text-white p-2 rounded-full next hover:bg-gray-600">›</button>
            <?php endif; ?>
        </div>

        <!-- Property Details -->
        <div class="property-details">
            <h1 class="text-3xl font-bold text-[#092468]"><?php echo htmlspecialchars($property['title']); ?></h1>

            <div class="mt-2 flex space-x-2">
                <span class="inline-block bg-[#092468] text-white px-3 py-1 rounded text-sm">
                    <?php echo ucfirst($property['status']); ?>
                </span>
                <span class="inline-block bg-gray-500 text-white px-3 py-1 rounded text-sm">
                    <?php echo ucfirst(str_replace(['for_sale', 'for_rent', 'short_let', 'hotel'], ['Sale', 'Rent', 'Short Let', 'Hotel'], $property['listing_type'])); ?>
                </span>
            </div>

            <p class="text-xl text-[#CC9933] font-semibold mt-2">
                ₦<?php echo number_format($property['price'], 2); ?><?php echo getPriceLabel($property['listing_type'], $property['price_frequency']); ?>
            </p>

            <p class="text-gray-600 mt-2"><?php echo short_location($property['location']); ?></p>

            <?php echo formatDescription($property['description']); ?>

            <!-- Contact Buttons -->
            <div class="mt-6 flex flex-wrap gap-4">
                <!-- Wishlist Button -->
                <button class="wishlist-btn <?php echo $in_wishlist ? 'text-red-500' : 'text-gray-500'; ?> hover:text-red-500 transition text-lg px-4 py-2 border rounded"
                    data-property-id="<?php echo $property['id']; ?>"
                    data-in-wishlist="<?php echo $in_wishlist ? '1' : '0'; ?>">
                    <?php echo $in_wishlist ? '❤️ Added to Wishlist' : '🤍 Add to Wishlist'; ?>
                </button>

                <!-- WhatsApp Button -->
               <!-- WhatsApp Button with improved image handling -->
               
               <a href="https://wa.me/2348111973369?text=<?php
    echo urlencode(
         "Hi, I'm interested in this property:\n\n" .
        " *" . htmlspecialchars($property['title']) . "*\n" .
        " Location:" . short_location($property['location']) . "\n" .
        " Price: ₦" . number_format($property['price'], 2) . 
        ($property['price_frequency'] ? ' ' . getPriceLabel($property['listing_type'], $property['price_frequency']) : '') . "\n\n" .
        "Full Details: https://pishonserv.com/property.php?id=" . $property['id'] ."\n\n" .
         // " *Property Images:* " . $firstImage . "\n\n" .
         "Hello, please confirm if this facility is available for booking from {check_in_date} to {check_out_date}"
    );
?>" 
target="_blank" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded flex items-center gap-2">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
    WhatsApp Agent
</a>
<!--<a href="https://wa.me/2347032078859?text=<?php
    // echo urlencode(
    //     "Hi, I'm interested in this property:\n\n" .
    //     "🏠 *" . $property['title'] . "*\n" .
    //     "📍 Location: " . $property['location'] . "\n" .
    //     "💰 Price: ₦" . number_format($property['price'], 2) . 
    //     ($property['price_frequency'] ? ' ' . getPriceLabel($property['listing_type'], $property['price_frequency']) : '') . "\n" .
    //     "🔗 Full Details: https://pishonserv.com/property.php?id=" . $property['id'] . "\n\n" .
    //     "📸 *Property Images:* " . $firstImage . "\n\n" .
    //     "Please contact me to arrange a viewing. Thank you!"
    // );
?>" 
target="_blank" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded flex items-center gap-2">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
    WhatsApp Agent
</a>-->

                <!-- Call Button -->
                <a href="tel:<?php echo htmlspecialchars($superadmin['phone']); ?>" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                    </svg>
                    Call Now
                </a>
            </div>

            <!-- Property Info -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php
                $info_fields = [
                    'type' => 'Type', 'bedrooms' => 'Bedrooms', 'bathrooms' => 'Bathrooms', 
                    'garage' => 'Garage', 'size' => 'Size', 'furnishing_status' => 'Furnishing',
                    'property_condition' => 'Condition', 'price_frequency' => 'Price Frequency',
                    'minimum_stay' => 'Minimum Stay', 'room_type' => 'Room Type'
                ];
                
                foreach ($info_fields as $key => $label) {
                    if (!empty($property[$key])) {
                        echo "<div class='py-1 border-b border-gray-200'><strong class='text-[#092468]'>{$label}:</strong> " . 
                             "<span class='text-gray-700'>" . htmlspecialchars($property[$key]) . "</span></div>";
                    }
                }
                if (!empty($property['maintenance_fee'])) echo "<div><strong>Maintenance Fee:</strong> ₦" . number_format($property['maintenance_fee'], 2) . "</div>";
                if (!empty($property['agent_fee'])) echo "<div><strong>Agent Fee:</strong> ₦" . number_format($property['agent_fee'], 2) . "</div>";
                if (!empty($property['caution_fee'])) echo "<div><strong>Caution Fee:</strong> ₦" . number_format($property['caution_fee'], 2) . "</div>";
                if (!empty($property['policies'])) echo "<div class='md:col-span-2'><strong>Policies:</strong> " . htmlspecialchars($property['policies'], 2) . "</div>";
                
                if (!empty($property['amenities'])) {
                    echo "<div class='md:col-span-2 py-2'><strong class='text-[#092468]'>Amenities:</strong><div class='flex flex-wrap gap-2 mt-2'>";
                    foreach (explode(',', $property['amenities']) as $amenity) {
                        echo '<span class="bg-gray-100 px-3 py-1 rounded-full text-sm">' . htmlspecialchars(trim($amenity)) . '</span>';
                    }
                    echo "</div></div>";
                }
                 
                ?>
            </div>

            <!-- Booking Section -->
            <?php if (in_array($property['listing_type'], ['short_let', 'hotel'])): ?>
                <form action="book_property.php" method="POST" class="mt-6 bg-white p-6 rounded-lg shadow-md">
                    <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                    
                    <div class="mb-4">
                        <label class="block text-[#092468] font-medium mb-2">Check-in Date</label>
                        <input type="text" id="check_in_date" name="check_in_date" required 
                               class="w-full p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-[#092468]">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-[#092468] font-medium mb-2">Check-out Date</label>
                        <input type="text" id="check_out_date" name="check_out_date" required 
                               class="w-full p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-[#092468]">
                    </div>
                    
                    <button type="submit" class="w-full bg-[#092468] hover:bg-[#0d307e] text-white font-bold py-3 px-4 rounded transition">
                        Book Now
                    </button>
                </form>
            <?php else: ?>
                <a href="book_property.php?property_id=<?php echo $property['id']; ?>"
                    class="mt-6 block bg-[#092468] hover:bg-[#0d307e] text-white text-center font-bold py-3 px-4 rounded transition">
                    Inquire About This Property
                </a>
            <?php endif; ?>

            <!-- Share Buttons -->
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-[#092468] mb-3">Share this property:</h3>
                <div class="flex flex-wrap gap-3">
                    <?php 
                    $share_url = 'https://pishonserv.com/property.php?id=' . $property['id'];
                    $share_text = urlencode('Check out this property: ' . $property['title'] . ' - ' . $share_url);
                    ?>
                    
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($share_url); ?>" 
                       target="_blank" class="bg-[#3b5998] hover:bg-[#2d4373] text-white px-4 py-2 rounded flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/>
                        </svg>
                        Facebook
                    </a>
                    
                    <a href="https://twitter.com/intent/tweet?text=<?php echo $share_text; ?>" 
                       target="_blank" class="bg-[#1da1f2] hover:bg-[#0d8dd8] text-white px-4 py-2 rounded flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M5.026 15c6.038 0 9.341-5.003 9.341-9.334 0-.14 0-.282-.006-.422A6.685 6.685 0 0 0 16 3.542a6.658 6.658 0 0 1-1.889.518 3.301 3.301 0 0 0 1.447-1.817 6.533 6.533 0 0 1-2.087.793A3.286 3.286 0 0 0 7.875 6.03a9.325 9.325 0 0 1-6.767-3.429 3.289 3.289 0 0 0 1.018 4.382A3.323 3.323 0 0 1 .64 6.575v.045a3.288 3.288 0 0 0 2.632 3.218 3.203 3.203 0 0 1-.865.115 3.23 3.23 0 0 1-.614-.057 3.283 3.283 0 0 0 3.067 2.277A6.588 6.588 0 0 1 .78 13.58a6.32 6.32 0 0 1-.78-.045A9.344 9.344 0 0 0 5.026 15z"/>
                        </svg>
                        Twitter
                    </a>
                    
                    <a href="mailto:?body=<?php echo $share_text; ?>" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555ZM0 4.697v7.104l5.803-3.558L0 4.697ZM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757Zm3.436-.586L16 11.801V4.697l-5.803 3.546Z"/>
                        </svg>
                        Email
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Similar Properties -->
<section class="container mx-auto py-12 px-4 md:px-10 lg:px-16">
    <h2 class="text-2xl font-bold text-[#092468] text-center mb-8">Similar Properties</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php
        $query = "SELECT * FROM properties 
                  WHERE admin_approved = 1 
                  AND status = 'available' 
                  AND id != ? 
                  AND listing_type = ?
                  ORDER BY created_at DESC 
                  LIMIT 3";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $property_id, $property['listing_type']);
        $stmt->execute();
        $similar_properties = $stmt->get_result();

        while ($similar = $similar_properties->fetch_assoc()) {
            $imagesArray = explode(',', $similar['images']);
            $thumb = !empty($imagesArray[0]) ? 'public/uploads/' . htmlspecialchars($imagesArray[0]) : 'public/uploads/default.jpg';
            $price_label = getPriceLabel($similar['listing_type'], $similar['price_frequency']);
            
            echo "
            <div class='bg-white rounded-lg overflow-hidden shadow-md hover:shadow-lg transition'>
                <div class='relative h-48 overflow-hidden'>
                    <img src='$thumb' alt='" . htmlspecialchars($similar['title']) . "' class='w-full h-full object-cover'>
                    <div class='absolute top-2 left-2 bg-[#092468] text-white text-xs px-2 py-1 rounded'>
                        " . ucfirst($similar['status']) . "
                    </div>
                </div>
                <div class='p-4'>
                    <h3 class='text-lg font-bold text-[#092468]'>" . htmlspecialchars($similar['title']) . "</h3>
                    <p class='text-[#CC9933] font-semibold mt-1'>₦" . number_format($similar['price'], 2) . $price_label . "</p>
                    <p class='text-gray-600 text-sm mt-1'>" . htmlspecialchars($similar['location']) . "</p>
                    <div class='mt-3 pt-3 border-t border-gray-100 flex justify-between items-center'>
                        <div class='flex items-center text-sm text-gray-500'>
                            <span class='mr-3'><i class='fas fa-bed mr-1'></i> " . ($similar['bedrooms'] ?? '0') . " Beds</span>
                            <span><i class='fas fa-bath mr-1'></i> " . ($similar['bathrooms'] ?? '0') . " Baths</span>
                        </div>
                        <a href='property.php?id={$similar['id']}' class='text-sm text-[#092468] font-medium hover:underline'>
                            View Details
                        </a>
                    </div>
                </div>
            </div>";
        }
        $stmt->close();
        ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image slider functionality
    const images = document.querySelectorAll('.slider-image');
    let currentIndex = 0;
    
    function showImage(index) {
        images.forEach((img, i) => {
            img.classList.toggle('hidden', i !== index);
        });
    }
    
    if (images.length > 1) {
        document.querySelector('.prev').addEventListener('click', () => {
            currentIndex = (currentIndex > 0) ? currentIndex - 1 : images.length - 1;
            showImage(currentIndex);
        });
        
        document.querySelector('.next').addEventListener('click', () => {
            currentIndex = (currentIndex < images.length - 1) ? currentIndex + 1 : 0;
            showImage(currentIndex);
        });
    }
    
    // Date picker for booking form
    if (document.getElementById('check_in_date')) {
        const propertyId = <?php echo $property_id; ?>;
        
        fetch('fetch_booked_dates.php?property_id=' + propertyId)
            .then(response => response.json())
            .then(disabledDates => {
                const checkInPicker = flatpickr("#check_in_date", {
                    disable: disabledDates,
                    minDate: "today",
                    dateFormat: "Y-m-d",
                    onChange: function(selectedDates) {
                        if (selectedDates.length > 0) {
                            const minCheckout = new Date(selectedDates[0]);
                            minCheckout.setDate(minCheckout.getDate() + 1);
                            checkOutPicker.set('minDate', minCheckout);
                            checkOutPicker.clear();
                        }
                    }
                });
                
                const checkOutPicker = flatpickr("#check_out_date", {
                    disable: disabledDates,
                    minDate: "today",
                    dateFormat: "Y-m-d"
                });
            })
            .catch(error => console.error('Error loading booked dates:', error));
    }
    
    // Wishlist functionality
    document.querySelector('.wishlist-btn')?.addEventListener('click', function() {
        const propertyId = this.dataset.propertyId;
        const inWishlist = this.dataset.inWishlist === '1';
        const action = inWishlist ? 'remove' : 'add';
        
        fetch('wishlist_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=${action}&property_id=${propertyId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.dataset.inWishlist = inWishlist ? '0' : '1';
                this.innerHTML = inWishlist ? '🤍 Add to Wishlist' : '❤️ Added to Wishlist';
                this.classList.toggle('text-red-500', !inWishlist);
                this.classList.toggle('text-gray-500', inWishlist);
            }
        })
        .catch(error => console.error('Error:', error));
    });
});
</script>
</body>
</html>