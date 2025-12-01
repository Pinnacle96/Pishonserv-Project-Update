<?php

// ----------------------------------------------------
// !!! WARNING: BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT !!!
// This script is for debugging the migration loop.
// ----------------------------------------------------

// Include your database connection file
include 'includes/db_connect.php';

// Set script to run indefinitely for large datasets
set_time_limit(0);

echo "<h1>Image Migration Debug Script</h1>";
echo "<p>Starting debug migration. This script will log every step.</p>";
echo "<pre>"; // Use <pre> tag for monospaced, formatted output

$migrated_count = 0;
$skipped_count = 0;
$error_count = 0;

// 1. Select products that have images but no entries in product_images
$query = "
    SELECT
        p.id,
        p.name,
        p.images
    FROM
        products p
    LEFT JOIN
        product_images pi ON p.id = pi.product_id
    WHERE
        p.images IS NOT NULL AND p.images != '' AND pi.product_id IS NULL;
";

$result = $conn->query($query);

if (!$result) {
    die("Error selecting products: " . $conn->error);
}

echo "Query returned " . $result->num_rows . " products to process.\n\n";

if ($result->num_rows > 0) {
    while ($product = $result->fetch_assoc()) {
        $product_id = $product['id'];
        $product_name = htmlspecialchars($product['name']);
        $image_urls_string = $product['images'];

        echo "--- Processing Product: '{$product_name}' (ID: {$product_id}) ---\n";
        
        $image_urls = explode(',', $image_urls_string);

        foreach ($image_urls as $image_url) {
            $image_url = trim($image_url);

            if (empty($image_url)) {
                echo "  -> Skipping empty URL.\n";
                continue;
            }

            try {
                // Check if image already exists to prevent duplication
                $check_stmt = $conn->prepare("SELECT id FROM product_images WHERE product_id = ? AND image_url = ?");
                if ($check_stmt === false) {
                    throw new Exception("Check statement preparation failed: " . $conn->error);
                }
                $check_stmt->bind_param("is", $product_id, $image_url);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows === 0) {
                    // Insert the new image record
                    $insert_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
                    if ($insert_stmt === false) {
                        throw new Exception("Insert statement preparation failed: " . $conn->error);
                    }
                    $insert_stmt->bind_param("is", $product_id, $image_url);
                    if ($insert_stmt->execute()) {
                        echo "  -> Inserted image: {$image_url}\n";
                        $migrated_count++;
                    } else {
                        throw new Exception("Insert failed: " . $insert_stmt->error);
                    }
                    $insert_stmt->close();
                } else {
                    echo "  -> Skipped existing image: {$image_url}\n";
                    $skipped_count++;
                }
                $check_stmt->close();

            } catch (Exception $e) {
                echo "  -> <span style='color: red;'>ERROR: {$e->getMessage()}</span>\n";
                $error_count++;
            }
        }
        echo "--------------------------------------------------------\n\n";
    }
} else {
    echo "No products found that need image migration.\n";
}

echo "</pre>";
echo "<hr>";
echo "<h3>Migration Summary</h3>";
echo "<p>New images inserted: <strong>" . $migrated_count . "</strong></p>";
echo "<p>Existing images skipped: <strong>" . $skipped_count . "</strong></p>";
echo "<p>Errors encountered: <strong style='color: red;'>" . $error_count . "</strong></p>";

$conn->close();
?>