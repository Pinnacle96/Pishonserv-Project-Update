<?php
// dashboard/buyer_profile_content.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Require auth
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must log in first.";
    header("Location: ../auth/login.php");
    exit();
}

// $conn is usually included by dashboard_layout.php; if not, uncomment next line:
// require_once '../includes/db_connect.php';

$user_id = (int) $_SESSION['user_id'];

// Fetch fresh user profile from DB (don’t trust session for profile fields)
$stmt = $conn->prepare("SELECT id, name, lname, email, phone, profile_image FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: ../auth/login.php");
    exit();
}

// CSRF token (reuse if already set, else create)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<div class="mt-6">
  <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Profile Settings</h2>
  <p class="text-gray-600 dark:text-gray-400">Update your personal information.</p>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
    <!-- Left: Avatar & Password -->
    <div class="md:col-span-1 space-y-6">
      <!-- Avatar card -->
      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Profile Photo</h3>
        <div class="flex items-center gap-4">
         <?php
// Normalize the stored path to an absolute web path under /public/uploads/
$avatarPath = '';
if (!empty($user['profile_image'])) {
    if (strpos($user['profile_image'], '/public/uploads/') === 0) {
        // already an absolute web path
        $avatarPath = $user['profile_image'];
    } else {
        // stored as filename or relative path — force to /public/uploads/
        $avatarPath = '/public/uploads/' . ltrim($user['profile_image'], '/');
    }
} else {
    $avatarPath = 'https://ui-avatars.com/api/?background=092468&color=fff&name=' . urlencode($user['name'] ?? 'User');
}
?>
<img
  src="<?= htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') ?>"
  alt="Avatar"
  class="w-20 h-20 rounded-full object-cover border"
/>

          <div class="text-sm text-gray-600 dark:text-gray-300">
            JPG/PNG, max 2MB.
          </div>
        </div>
        <form action="../process/update_profile.php" method="POST" enctype="multipart/form-data" class="mt-4">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="upload_avatar">
          <input type="file" name="avatar" accept="image/*"
                 class="block w-full text-sm file:mr-4 file:py-2 file:px-3 file:rounded file:border-0 file:bg-[#F4A124] file:text-white hover:file:bg-[#d88b1c]">
          <button type="submit"
                  class="mt-3 bg-[#F4A124] text-white px-4 py-2 rounded hover:bg-[#d88b1c]">
            Update Photo
          </button>
        </form>
      </div>

      <!-- Password card -->
      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Change Password</h3>
        <form action="../process/update_profile.php" method="POST" class="space-y-3">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="change_password">

          <div>
            <label class="block text-sm text-gray-700 dark:text-gray-300">Current Password</label>
            <input type="password" name="current_password" required
                   class="w-full p-3 border rounded mt-1 dark:bg-gray-900 dark:border-gray-700">
          </div>
          <div>
            <label class="block text-sm text-gray-700 dark:text-gray-300">New Password</label>
            <input type="password" name="new_password" minlength="6" required
                   class="w-full p-3 border rounded mt-1 dark:bg-gray-900 dark:border-gray-700">
          </div>
          <div>
            <label class="block text-sm text-gray-700 dark:text-gray-300">Confirm New Password</label>
            <input type="password" name="confirm_password" minlength="6" required
                   class="w-full p-3 border rounded mt-1 dark:bg-gray-900 dark:border-gray-700">
          </div>

          <button type="submit" class="w-full bg-[#092468] text-white px-4 py-2 rounded hover:bg-[#061a3f]">
            Update Password
          </button>
        </form>
      </div>
    </div>

    <!-- Right: Profile form -->
    <div class="md:col-span-2">
      <form action="../process/update_profile.php" method="POST"
            class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="update_profile">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-gray-700 dark:text-gray-300">First Name</label>
            <input type="text" name="name" value="<?= h($user['name'] ?? '') ?>"
                   class="w-full p-3 border rounded mt-1 dark:bg-gray-900 dark:border-gray-700" required>
          </div>
          <div>
            <label class="block text-gray-700 dark:text-gray-300">Last Name</label>
            <input type="text" name="lname" value="<?= h($user['lname'] ?? '') ?>"
                   class="w-full p-3 border rounded mt-1 dark:bg-gray-900 dark:border-gray-700">
          </div>
          <div>
            <label class="block text-gray-700 dark:text-gray-300">Email</label>
            <input type="email" name="email" value="<?= h($user['email'] ?? '') ?>"
                   class="w-full p-3 border rounded mt-1 dark:bg-gray-900 dark:border-gray-700" required>
          </div>
          <div>
            <label class="block text-gray-700 dark:text-gray-300">Phone</label>
            <input type="text" name="phone" value="<?= h($user['phone'] ?? '') ?>"
                   class="w-full p-3 border rounded mt-1 dark:bg-gray-900 dark:border-gray-700">
          </div>
        </div>

        <button type="submit"
                class="mt-4 bg-[#F4A124] text-white px-6 py-2 rounded hover:bg-[#d88b1c]">
          Save Changes
        </button>
      </form>
    </div>
  </div>
</div>
