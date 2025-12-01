<?php
session_start();
include '../includes/db_connect.php';
include '../includes/config.php'; // ⬅️ Add config for LOCATIONIQ

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header("Location: ../auth/login.php");
    exit();
}

$property_id = intval($_GET['id']);

// Fetch property details
$stmt = $conn->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();
$property = $result->fetch_assoc();

if (!$property) {
    $_SESSION['error'] = "Property not found!";
    header("Location: admin_properties.php");
    exit();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $price = floatval($_POST['price']);
    $location = trim($_POST['location']);
    $type = $_POST['type'];
    $status = $_POST['status'];
    $listing_type = $_POST['listing_type'];
    $description = trim($_POST['description']);
    $bedrooms = intval($_POST['bedrooms']);
    $bathrooms = intval($_POST['bathrooms']);
    $size = trim($_POST['size']);
    $garage = intval($_POST['garage']);
    $admin_approved = intval($_POST['admin_approved']);
    
    // ✅ Block phone numbers & addresses
    $blocked_patterns = '/(\+?\d{2,4}?[-.\s]?\(?\d{2,4}\)?[-.\s]?\d{3,4}[-.\s]?\d{3,4}'
        . '|\bstreet\b|\bst\.\b|\broad\b|\brd\.\b|\bavenue\b|\bave\b'
        . '|\blane\b|\bln\b|\bclose\b|\bcrescent\b|\bhouse\b|\bbuilding\b|\bblock\b)/i';

    if (preg_match($blocked_patterns, $description, $matches)) {
        $_SESSION['error'] = "❌ Description cannot contain: \"" . htmlspecialchars($matches[0]) . "\"";
        header("Location: admin_edit_property.php?id=" . $property_id);
        exit();
    }

    $new_images = [];

    // ✅ Handle image uploads
    if (!empty($_FILES['images']['name'][0])) {
        $target_dir = "../public/uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        if (!empty($property['images'])) {
            $old_images = explode(',', $property['images']);
            foreach ($old_images as $old_image) {
                $old_path = $target_dir . $old_image;
                if (file_exists($old_path)) unlink($old_path);
            }
        }

        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            $image_name = uniqid() . "_" . basename($_FILES['images']['name'][$key]);
            $image_path = $target_dir . $image_name;

            if (compressImage($tmp_name, $image_path, 75)) {
                $new_images[] = $image_name;
            }
        }
    }

    $image_string = empty($new_images) ? $property['images'] : implode(',', $new_images);

    // ✅ Geocode using LocationIQ
    $lat = null;
    $lon = null;
    $apiKey = LOCATIONIQ_API_KEY;
    $encodedLocation = urlencode($location);
    $geoUrl = "https://us1.locationiq.com/v1/search.php?key={$apiKey}&q={$encodedLocation}&format=json";

    $geoResponse = @file_get_contents($geoUrl);
    if ($geoResponse !== false) {
        $geoData = json_decode($geoResponse, true);
        if (isset($geoData[0]['lat']) && isset($geoData[0]['lon'])) {
            $lat = floatval($geoData[0]['lat']);
            $lon = floatval($geoData[0]['lon']);
        }
    }

    // ✅ Update query (with lat/lon if available)
    $updateStmt = $conn->prepare("UPDATE properties 
        SET title=?, price=?, location=?, type=?, status=?, listing_type=?, description=?, bedrooms=?, bathrooms=?, size=?, garage=?, images=?, admin_approved=?, latitude=?, longitude=? 
        WHERE id=?");

    $updateStmt->bind_param(
        "sdsssssiisisiddi",
        $title,
        $price,
        $location,
        $type,
        $status,
        $listing_type,
        $description,
        $bedrooms,
        $bathrooms,
        $size,
        $garage,
        $image_string,
        $admin_approved,
        $lat,
        $lon,
        $property_id
    );

    if ($updateStmt->execute()) {
        $_SESSION['success'] = "✅ Property updated successfully!";
    } else {
        $_SESSION['error'] = "❌ Failed to update property!";
    }

    header("Location: admin_properties.php");
    exit();
}

// ✅ Image Compression Function
function compressImage($source, $destination, $quality)
{
    $info = getimagesize($source);
    if (!$info) return false;

    switch ($info['mime']) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            imagejpeg($image, $destination, $quality);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            imagepng($image, $destination, 9);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            imagegif($image, $destination);
            break;
        default:
            return false;
    }

    imagedestroy($image);
    return true;
}

$page_content = __DIR__ . "/admin_edit_property_content.php";
include 'dashboard_layout.php';
