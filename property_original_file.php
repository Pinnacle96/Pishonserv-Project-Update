<?php
session_start();
include 'includes/db_connect.php';

$property_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$property_id) die("Invalid property.");

$query = "SELECT p.*, u.name AS agent_name, u.profile_image AS agent_image, u.phone AS agent_phone
          FROM properties p
          JOIN users u ON p.owner_id = u.id
          WHERE p.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$property) die("Property not found.");

$images = !empty($property['images']) ? explode(',', $property['images']) : ['default.jpg'];
$firstImage = 'https://pishonserv.com/public/uploads/' . rawurlencode($images[0]);


$in_wishlist = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND property_id = ?");
    $stmt->bind_param("ii", $user_id, $property_id);
    $stmt->execute();
    $in_wishlist = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

$superadmin = $conn->query("SELECT name, phone, email FROM users WHERE role = 'superadmin' LIMIT 1")->fetch_assoc();

function formatDescription($description) {
    $paragraphs = explode("\n\n", trim($description));
    $formatted = '<div class="mt-4 text-gray-700">';
    foreach ($paragraphs as $para) {
        $formatted .= '<p class="mt-2">' . htmlspecialchars($para) . '</p>';
    }
    $formatted .= '</div>';
    return $formatted;
}

function getPriceLabel($listing_type, $price_frequency) {
    if ($listing_type === 'for_sale') return '';
    if (!empty($price_frequency)) return ' per ' . str_replace('_', ' ', $price_frequency);
    return ' per unit';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta property="og:image" content="<?php echo $firstImage; ?>">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="800">
    <meta property="og:image:height" content="600">

    <title><?php echo htmlspecialchars($property['title']); ?> | Property Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
                        alt="Property Image">
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

            <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($property['location']); ?></p>

            <?php echo formatDescription($property['description']); ?>

            <!-- Wishlist & Contact -->
            <div class="mt-6 flex space-x-4">
                <button class="wishlist-btn <?php echo $in_wishlist ? 'text-red-500' : 'text-gray-500'; ?> hover:text-red-500 transition text-lg"
                    data-property-id="<?php echo $property['id']; ?>"
                    data-in-wishlist="<?php echo $in_wishlist ? '1' : '0'; ?>">
                    <?php echo $in_wishlist ? '❤️ Added to Wishlist' : '🤍 Add to Wishlist'; ?>
                </button>
                <button onclick="document.getElementById('superadmin-contact').classList.toggle('hidden')"
                    class="bg-[#CC9933] text-white px-4 py-2 rounded hover:bg-[#d88b1c] text-lg">
                    Contact Agent
                </button>
            </div>

            <div id="superadmin-contact" class="hidden mt-2 text-gray-800">
                <p><strong>Name:</strong> <?php echo htmlspecialchars($superadmin['name']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($superadmin['phone']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($superadmin['email']); ?></p>
            </div>

            <!-- Property Info -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php
                $info_fields = [
                    'type' => 'Type', 'bedrooms' => 'Bedrooms', 'bathrooms' => 'Bathrooms', 'garage' => 'Garage',
                    'size' => 'Size', 'furnishing_status' => 'Furnishing', 'property_condition' => 'Condition',
                    'price_frequency' => 'Price Frequency', 'minimum_stay' => 'Minimum Stay',
                    'room_type' => 'Room Type', 'star_rating' => 'Star Rating'
                ];
                foreach ($info_fields as $key => $label) {
                    if (!empty($property[$key]) && $property[$key] !== 'N/A' && $property[$key] != 0) {
                        echo "<div><strong>{$label}:</strong> " . htmlspecialchars($property[$key]) . "</div>";
                    }
                }
                if (in_array($property['listing_type'], ['short_let', 'hotel'])) {
                    if (!empty($property['checkin_time'])) echo "<div><strong>Check-in Time:</strong> " . htmlspecialchars($property['checkin_time']) . "</div>";
                    if (!empty($property['checkout_time'])) echo "<div><strong>Check-out Time:</strong> " . htmlspecialchars($property['checkout_time']) . "</div>";
                }
                if (!empty($property['maintenance_fee'])) echo "<div><strong>Maintenance Fee:</strong> ₦" . number_format($property['maintenance_fee'], 2) . "</div>";
                if (!empty($property['agent_fee'])) echo "<div><strong>Agent Fee:</strong> ₦" . number_format($property['agent_fee'], 2) . "</div>";
                if (!empty($property['caution_fee'])) echo "<div><strong>Caution Fee:</strong> ₦" . number_format($property['caution_fee'], 2) . "</div>";
                if (!empty($property['amenities'])) {
                    echo "<div class='md:col-span-2'><strong>Amenities:</strong><div class='grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 mt-2'>";
                    foreach (explode(',', $property['amenities']) as $amenity) {
                        echo '<div class="bg-gray-100 p-2 rounded text-sm">' . htmlspecialchars(trim($amenity)) . '</div>';
                    }
                    echo "</div></div>";
                }
                if (!empty($property['policies'])) {
                    echo "<div class='md:col-span-2'><strong>Policies:</strong> " . htmlspecialchars($property['policies']) . "</div>";
                }
                ?>
            </div>

            <!-- Booking Logic -->
            <?php if (in_array($property['listing_type'], ['short_let', 'hotel'])): ?>
                <form action="book_property.php" method="POST" class="mt-6 bg-gray-100 p-4 rounded-lg">
                    <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                    <label class="block font-semibold text-[#092468]">Check-in Date:</label>
                    <input type="text" id="check_in_date" name="check_in_date" required class="w-full p-2 border rounded mt-1">

                    <label class="block font-semibold text-[#092468] mt-2">Check-out Date:</label>
                    <input type="text" id="check_out_date" name="check_out_date" required class="w-full p-2 border rounded mt-1">

                    <button type="submit" class="mt-4 bg-[#092468] text-white px-4 py-2 rounded hover:bg-blue-700 w-full">
                        Book Now
                    </button>
                </form>
            <?php else: ?>
                <a href="book_property.php?property_id=<?php echo $property['id']; ?>"
                    class="mt-6 block bg-[#092468] text-white px-4 py-2 rounded hover:bg-blue-700 text-center text-lg">
                    Book Now
                </a>
            <?php endif; ?>

            <!-- Share Buttons -->
            <div class="mt-6 flex flex-wrap gap-4">
                <?php 
                    $url = 'https://pishonserv.com/property.php?id=' . $property['id']; 
                    $message = urlencode('Check out this property: ' . $property['title'] . ' - ' . $url);
                ?>
                <a href="https://wa.me/?text=<?php echo $message; ?>"
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Share on WhatsApp</a>
                <a href="sms:?body=<?php echo $message; ?>"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Share via SMS</a>
                <button onclick="navigator.clipboard.writeText('<?php echo $url; ?>')"
                   class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded">Copy Link</button>
            </div>
        </div>
    </div>
</section>

<!-- Similar Properties Section -->
<section class="container mx-auto py-12 px-4 md:px-10 lg:px-16">
    <h2 class="text-2xl font-bold text-[#092468] text-center">Similar Properties</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
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

            echo "
            <div class='border rounded-lg shadow-lg bg-white hover:shadow-xl transition'>
                <img src='$thumb' class='w-full h-48 object-cover rounded-t-lg'>
                <div class='p-4'>
                    <h3 class='text-[#092468] text-xl font-bold'>" . htmlspecialchars($similar['title']) . "</h3>
                    <p class='text-[#CC9933] font-semibold'>₦" . number_format($similar['price'], 2) . "</p>
                    <p class='text-gray-600'>" . htmlspecialchars($similar['location']) . "</p>
                    <a href='property.php?id={$similar['id']}' class='mt-2 block text-center bg-[#CC9933] text-white px-4 py-2 rounded hover:bg-[#d88b1c]'>
                        View Details
                    </a>
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
document.addEventListener('DOMContentLoaded', function () {
    let images = document.querySelectorAll('.slider-image');
    let index = 0;

    function showImage(i) {
        images.forEach(img => img.classList.add('hidden'));
        images[i].classList.remove('hidden');
    }

    if (images.length > 1) {
        document.querySelector('.prev').addEventListener('click', () => {
            index = (index > 0) ? index - 1 : images.length - 1;
            showImage(index);
        });
        document.querySelector('.next').addEventListener('click', () => {
            index = (index < images.length - 1) ? index + 1 : 0;
            showImage(index);
        });
    }

    const propertyId = <?php echo (int)$property_id; ?>;
    let checkInPicker, checkOutPicker;

    fetch('fetch_booked_dates.php?property_id=' + propertyId)
        .then(response => response.json())
        .then(disabledDates => {
            checkInPicker = flatpickr("#check_in_date", {
                disable: disabledDates,
                dateFormat: "Y-m-d",
                minDate: "today",
                onChange: function (selectedDates) {
                    if (selectedDates.length > 0) {
                        let minCheckoutDate = new Date(selectedDates[0]);
                        minCheckoutDate.setDate(minCheckoutDate.getDate() + 1);
                        checkOutPicker.set('minDate', minCheckoutDate);
                        checkOutPicker.clear();
                    }
                }
            });

            checkOutPicker = flatpickr("#check_out_date", {
                disable: disabledDates,
                dateFormat: "Y-m-d",
                minDate: "today"
            });
        });
});
</script>
</body>
</html>
