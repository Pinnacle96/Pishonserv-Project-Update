<?php
session_start();
include '../includes/db_connect.php'; // Ensure this path is correct

// Restrict access to admin/superadmin only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    // Redirect to a login page or show an error
    header("Location: ../auth/login.php");
    exit();
}

/*
 * --- REWRITTEN QUERY ---
 * This query is more efficient than the previous version.
 * - It uses JOINs instead of slow correlated subqueries.
 * - It uses GROUP_CONCAT to aggregate categories for each product.
 * - The subquery for 'first_image' efficiently gets the primary image for each product.
 * - The INNER JOIN ensures that only products WITH images are returned.
 */
$query = "
    SELECT
        p.id,
        p.name,
        p.sku,
        p.type,
        p.description,
        p.regular_price,
        p.sale_price,
        first_image.image_url,
        GROUP_CONCAT(DISTINCT pc.category_path SEPARATOR ', ') AS categories
    FROM
        products p
    LEFT JOIN
        product_categories pc ON p.id = pc.product_id
    -- The key change is here: changing LEFT JOIN to INNER JOIN
    INNER JOIN (
        -- This derived table efficiently finds the first image for each product
        -- by selecting the one with the lowest ID.
        SELECT
            product_id,
            image_url
        FROM (
            SELECT
                product_id,
                image_url,
                ROW_NUMBER() OVER(PARTITION BY product_id ORDER BY id ASC) as rn
            FROM
                product_images
        ) AS ranked_images
        WHERE
            rn = 1
    ) AS first_image ON p.id = first_image.product_id
    GROUP BY
        p.id,
        first_image.image_url -- Group by product ID and the already-unique image URL
    ORDER BY
        p.id DESC; -- Order by product ID descending to show newest first
";

// It's good practice to use prepared statements to prevent SQL injection,
// though not strictly necessary here as there's no user input.
$result = $conn->query($query);

// The rest of your file remains the same
$page_content = __DIR__ . "/admin_products_content.php";
include 'dashboard_layout.php';
?>