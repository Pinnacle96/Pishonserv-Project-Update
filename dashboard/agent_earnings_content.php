<?php
//session_start();
include '../includes/db_connect.php';
require '../vendor/autoload.php'; // PHPMailer for notifications

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$agent_id = $_SESSION['user_id'];

// ✅ Check if wallet exists for the agent
$stmt = $conn->prepare("SELECT * FROM wallets WHERE user_id = ?");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$wallet = $stmt->get_result()->fetch_assoc();

// ✅ If wallet does NOT exist, create an empty one (This logic is correct for the new schema)
if (!$wallet) {
    $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
    $stmt->bind_param("i", $agent_id);
    $stmt->execute();

    // Fetch wallet again after creation
    $stmt = $conn->prepare("SELECT * FROM wallets WHERE user_id = ?");
    $stmt->bind_param("i", $agent_id);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();
}

// ✅ Ensure balance is available from the wallets table
$wallet_balance = $wallet['balance'] ?? 0;

// ✅ Fetch earnings history
$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$transactions = $stmt->get_result();

// ✅ Fetch Available Bank Accounts from the correct table
$bank_stmt = $conn->prepare("SELECT id, bank_name, account_number FROM bank_accounts WHERE user_id = ?");
$bank_stmt->bind_param("i", $agent_id);
$bank_stmt->execute();
$bank_accounts = $bank_stmt->get_result();
$has_bank_accounts = $bank_accounts->num_rows > 0;
?>

<div class="p-6">
    <h2 class="text-2xl font-bold text-[#092468]">My Earnings</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <script>
            Swal.fire("Success!", "<?php echo $_SESSION['success']; ?>", "success");
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            Swal.fire("Error!", "<?php echo $_SESSION['error']; ?>", "error");
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php
      $ref_count = 0; $ref_commission = 0.0; $ref_code = '';
      $q = $conn->prepare("SELECT COUNT(*) c FROM referrals WHERE referrer_user_id = ?");
      $q->bind_param('i', $agent_id);
      $q->execute();
      $ref_count = (int)($q->get_result()->fetch_assoc()['c'] ?? 0);
      $q->close();
      $t = $conn->prepare("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE user_id = ? AND status='completed' AND type='credit' AND transaction_type='referral_bonus'");
      $t->bind_param('i', $agent_id);
      $t->execute();
      $ref_commission = (float)($t->get_result()->fetch_assoc()['s'] ?? 0.0);
      $t->close();
  $c = $conn->prepare("SELECT referral_code FROM users WHERE id = ? LIMIT 1");
  $c->bind_param('i', $agent_id);
  $c->execute();
  $ref_code = (string)($c->get_result()->fetch_assoc()['referral_code'] ?? '');
  $c->close();

   if ($ref_code === '') {
      $code = '';
      for ($i=0; $i<5 && $code === ''; $i++) {
        $candidate = substr(bin2hex(random_bytes(8)),0,16);
        $chk = $conn->prepare('SELECT id FROM users WHERE referral_code = ?');
        $chk->bind_param('s', $candidate);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
        if (!$exists) $code = $candidate;
      }
      if ($code === '') { $code = 'ps'.dechex($agent_id).substr(bin2hex(random_bytes(3)),0,6); }
      $set = $conn->prepare('UPDATE users SET referral_code = ? WHERE id = ?');
      $set->bind_param('si', $code, $agent_id);
      $set->execute();
      $set->close();
      $ref_code = $code;
   }
    ?>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-300">Wallet Balance</span>
                <span class="text-[#092468]">💼</span>
            </div>
            <div class="mt-2 text-3xl font-bold">₦<?php echo number_format($wallet_balance, 2); ?></div>
        </div>
    </div>

    <div class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow border border-gray-100 dark:border-gray-700">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-bold text-[#092468]">Referral Summary</h3>
            <?php if ($ref_code): ?>
            <button onclick="navigator.clipboard.writeText('https://pishonserv.com/register.php?ref=<?php echo htmlspecialchars($ref_code); ?>').then(()=>Swal.fire('Copied','Referral link copied','success')).catch(()=>Swal.fire('Error','Unable to copy','error'))" class="px-3 py-2 bg-[#092468] text-white rounded">Copy Link</button>
            <?php endif; ?>
        </div>
        <?php if ($ref_code): ?>
        <div class="flex flex-col md:flex-row gap-3">
            <input type="text" readonly value="https://pishonserv.com/register.php?ref=<?php echo htmlspecialchars($ref_code); ?>" class="w-full md:flex-1 p-3 border rounded bg-gray-50 dark:bg-gray-700" />
            <a href="https://pishonserv.com/register.php?ref=<?php echo htmlspecialchars($ref_code); ?>" target="_blank" class="px-4 py-3 bg-[#F4A124] text-white rounded text-center">Open</a>
        </div>
        <?php else: ?>
        <div class="text-sm text-gray-600 dark:text-gray-300">No referral link yet.</div>
        <?php endif; ?>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded">
                <div class="text-sm text-gray-600 dark:text-gray-300">Total Referrals</div>
                <div class="text-2xl font-bold"><?php echo number_format($ref_count); ?></div>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded">
                <div class="text-sm text-gray-600 dark:text-gray-300">Referral Commission</div>
                <div class="text-2xl font-bold">₦<?php echo number_format($ref_commission, 2); ?></div>
            </div>
        </div>
    </div>

    <a href="agent_manage_accounts.php"
        class="mt-6 inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700">
        Manage Bank Accounts
    </a>

    <?php if ($wallet_balance > 0 && $has_bank_accounts): ?>
        <form method="POST" action="agent_withdraw.php" class="mt-6 bg-white p-6 rounded shadow-md">
            <h3 class="text-lg font-bold text-gray-700">Withdraw Funds</h3>

            <label class="block text-gray-700">Select Bank Account</label>
            <select name="bank_id" required class="w-full p-3 border rounded mt-2">
                <?php while ($bank = $bank_accounts->fetch_assoc()): ?>
                    <option value="<?php echo $bank['id']; ?>">
                        <?php echo $bank['bank_name'] . " - " . $bank['account_number']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label class="block text-gray-700 mt-4">Withdraw Amount</label>
            <input type="number" name="amount" min="50" max="<?php echo $wallet_balance; ?>" required
                class="w-full p-3 border rounded mt-2">

            <button type="submit" name="withdraw"
                class="bg-[#F4A124] text-white w-full py-3 rounded mt-4 hover:bg-[#d88b1c]">
                Withdraw
            </button>
        </form>
    <?php elseif ($wallet_balance > 0 && !$has_bank_accounts): ?>
        <p class="mt-6 text-gray-500">You have a balance, but no bank account to withdraw to. Please add a bank account first.</p>
    <?php else: ?>
        <p class="mt-6 text-gray-500">You have no funds available for withdrawal.</p>
    <?php endif; ?>
</div>
