<?php
/* --- Base Variables --- */
if (!isset($signed_name) || !$signed_name) $signed_name = '_________________________';
if (!isset($role) || !$role) $role = 'user';
$role = strtolower($role);
$date = date('F j, Y');

/* Optional org/address for Party B (can be same as signed name) */
$partyB_org     = $partyB_org     ?? $signed_name;
$partyB_address = $partyB_address ?? '';

/* Embed logo as data URI (Dompdf-friendly) */
$logo_path = realpath(__DIR__ . '/../public/images/logo.png');
$logo_data = ($logo_path && file_exists($logo_path))
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path))
    : '';

/* Role → partial file map (clause-only) */
$role_map = [
    'agent'       => __DIR__ . '/../mou_content/agent.php',
    'owner'       => __DIR__ . '/../mou_content/owner.php',
    'hotel_owner' => __DIR__ . '/../mou_content/hotel_owner.php',
    'developer'   => __DIR__ . '/../mou_content/developer.php',
    'host'        => __DIR__ . '/../mou_content/host.php',
];
$role_section = $role_map[$role] ?? null;

/* Capture role-specific HTML (clauses only) */
ob_start();
if ($role_section && file_exists($role_section)) {
    // Available to partials: $signed_name, $partyB_org, $partyB_address, $role, $date
    include $role_section;
} else {
    ?>
    <h3>Purpose</h3>
    <p>This Memorandum of Understanding (“MoU”) sets out the responsibilities of the <?= ucfirst($role) ?> in relation to PISHONSERV’s platform for advertising, listing, marketing, and payment facilitation.</p>
    <h3>General Obligations</h3>
    <ul>
        <li>Provide accurate, current, and verifiable information at all times.</li>
        <li>Act with integrity, transparency, and professionalism in all dealings.</li>
        <li>Comply with applicable laws, platform policies, and directives.</li>
    </ul>
    <?php
}
$role_html = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PISHONSERV - MOU</title>
    <style>
        @page { margin: 40px; }
        body { font-family: Arial, sans-serif; font-size: 13px; line-height: 1.55; color: #222; }
        h1, h2, h3, h4 { color: #092468; margin: 0.6em 0 0.4em; }
        h1 { text-align: center; font-size: 22px; letter-spacing: 0.5px; }
        h3 { font-size: 15px; }
        p { margin: 0 0 10px; }
        ul { margin: 0 0 12px 18px; }
        hr { border: 0; border-top: 1px solid #e5e7eb; margin: 14px 0; }
        .logo { text-align: center; margin-bottom: 12px; }
        .logo img { height: 70px; }
        .watermark { position: fixed; top: 38%; left: 18%; transform: rotate(-45deg); opacity: 0.06; font-size: 90px; color: #000; z-index: 0; }
        .banner { text-align: center; font-size: 12px; margin: 10px 0 16px; }
        .prepared-by { text-align: center; font-size: 11px; color: #444; margin: 8px 0 12px; }
        .section { margin-top: 12px; }
        .signature { margin-top: 28px; }
        .sig-grid { width: 100%; margin-top: 16px; }
        .sig-col { width: 48%; display: inline-block; vertical-align: top; }
        .sig-line { border-top: 1px solid #111; margin-top: 40px; padding-top: 4px; font-size: 12px; }
        .footer { margin-top: 24px; font-size: 11px; color: #777; text-align: center; }
    </style>
</head>
<body>

<div class="watermark">CONFIDENTIAL</div>

<div class="logo">
    <?php if ($logo_data): ?>
        <img src="<?= $logo_data ?>" alt="PISHONSERV Logo">
    <?php else: ?>
        <h2>PISHONSERV</h2>
    <?php endif; ?>
</div>

<h1>MEMORANDUM OF UNDERSTANDING</h1>

<div class="banner">
    BETWEEN<br><br>
    <strong>PISHONSERV INTERNATIONAL LTD (PISHONSERV.COM)</strong><br><br>
    AND<br><br>
    <strong><?= strtoupper($partyB_org) ?></strong><br><br>
    IN RESPECT OF PISHONSERV’S ROLE AS AN ADVERTISING PLATFORM FOR LISTING PROPERTIES, SALES, MARKETING AND FACILITATING PAYMENTS.
</div>

<div class="prepared-by">
    PREPARED BY: OMALE PHILIP, ESQ., FREED SOLICITORS,<br>
    NO. 1, GOVERNMENT HOUSE ROAD, IDOWU ADEJUMO HOUSE, OPP. STELLA OBASANJO LIBRARY, LOKOJA, KOGI STATE. 08037981535
</div>

<hr>

<div class="section">
    <h3>Recitals</h3>
    <p>This MoU is made on <strong><?= $date ?></strong> between PISHONSERV INTERNATIONAL LTD (the “Platform”) and <strong><?= ucwords($partyB_org) ?></strong> (the “Partner”).</p>
    <p>The Platform provides an advertising, listing, marketing, and payment-facilitation service for properties. The Partner participates on the Platform in the capacity of <strong><?= ucfirst($role) ?></strong>.</p>
</div>

<hr>

<!-- Role-specific clauses ONLY -->
<div class="section">
    <?= $role_html ?>
</div>

<hr>

<!-- Global boilerplate (printed once) -->
<div class="section">
    <h3>Confidentiality</h3>
    <p>Each party shall keep confidential all non-public information obtained in connection with this MoU and use it solely for the purposes contemplated herein, except as required by law or with prior written consent.</p>

    <h3>Notices</h3>
    <p>Notices shall be in writing and deemed served (i) on delivery if hand-delivered; (ii) five (5) days after deposit with a recognized courier; or (iii) upon transmission with electronic delivery confirmation for email.</p>

    <h3>Entire Agreement</h3>
    <p>This MoU constitutes the entire understanding between the parties regarding its subject matter and supersedes all prior agreements or understandings, whether written or oral.</p>

    <h3>Assignment</h3>
    <p>Neither party may assign its rights or obligations under this MoU without the prior written consent of the other party.</p>

    <h3>Variation and Amendment</h3>
    <p>No amendment or variation of this MoU shall be effective unless made in a written instrument signed by duly authorized representatives of both parties.</p>

    <h3>Dispute Resolution</h3>
    <p>The parties shall use reasonable endeavours to resolve disputes amicably within fourteen (14) days. Failing settlement, a dispute may be referred to arbitration before a sole arbitrator appointed by mutual agreement, or failing such agreement, by a competent court. The arbitration shall be conducted in English. Each party shall bear its own costs and contribute equally to the arbitrator’s fees.</p>

    <h3>Governing Law</h3>
    <p>This MoU shall be governed by and construed in accordance with the laws of the Federal Republic of Nigeria.</p>
</div>

<div class="signature">
    <table class="sig-grid">
        <tr>
            <td class="sig-col">
                <div class="sig-line">For PISHONSERV INTERNATIONAL LTD (PISHONSERV.COM)</div>
                <div>Director / Secretary</div>
            </td>
            <td class="sig-col">
                <div class="sig-line">For <?= strtoupper($partyB_org) ?></div>
                <div>Authorized Signatory</div>
            </td>
        </tr>
    </table>

    <p style="margin-top: 18px;">
        <strong>Signed:</strong> <?= ucwords($signed_name) ?><br>
        <strong>Role:</strong> <?= ucfirst($role) ?><br>
        <strong>Date:</strong> <?= $date ?>
    </p>
</div>

<div class="footer">
    <p>PISHONSERV | Integrity. Excellence. Trust</p>
    <p>www.pishonserv.com | inquiry@pishonserv.com</p>
</div>

</body>
</html>





<!--<//?php-->
<!--//if (!isset($signed_name)) $signed_name = '_________________________';-->
<!--//if (!isset($role)) $role = 'user';-->
<!--//$role = strtolower($role);-->
<!--//$date = date('F j, Y');-->

<!--//$logo_path = realpath(__DIR__ . '/../public/images/logo.png');-->
<!--//$logo_data = ($logo_path && file_exists($logo_path))-->
<!--    //? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path))-->
<!--    : '';-->
<!--?>-->

<!--<!DOCTYPE html>-->
<!--<html lang="en">-->
<!--<head>-->
<!--    <meta charset="UTF-8">-->
<!--    <title>PISHONSERV - MOU</title>-->
<!--    <style>-->
<!--        body { font-family: Arial, sans-serif; font-size: 13px; line-height: 1.6; color: #222; padding: 40px; }-->
<!--        h1, h2, h3 { color: #092468; }-->
<!--        h1 { text-align: center; font-size: 24px; margin-bottom: 20px; }-->
<!--        h3 { margin-top: 20px; font-size: 16px; }-->
<!--        p, ul { margin-bottom: 15px; }-->
<!--        ul { padding-left: 20px; }-->
<!--        .signature { margin-top: 50px; }-->
<!--        .footer { margin-top: 60px; font-size: 12px; color: #777; text-align: center; }-->
<!--        .logo { text-align: center; margin-bottom: 20px; }-->
<!--        .logo img { height: 80px; }-->
<!--    </style>-->
<!--</head>-->
<!--<body>-->
<!--<div style="position: fixed; top: 40%; left: 25%; transform: rotate(-45deg); opacity: 0.1; font-size: 80px; color: #000; z-index: 0;">-->
<!--    CONFIDENTIAL-->
<!--</div>-->
<!--<div class="logo">-->
<!--    <//?php if ($logo_data): ?>-->
<!--        <img src="<//?= $logo_data ?>" alt="PISHONSERV Logo">-->
<!--    <//?php else: ?>-->
<!--        <h2>PISHONSERV</h2>-->
<!--    <//?php endif; ?>-->
<!--</div>-->

<!--<h1>Memorandum of Understanding (MOU)</h1>-->

<!--<p>-->
<!--    This Memorandum of Understanding ("MOU") is made on <strong><//?= $date ?></strong> between -->
<!--    <strong><//?= ucwords($signed_name) ?></strong> (the "<//?= ucfirst($role) ?>") and <strong>PISHONSERV</strong>, -->
<!--    an organization committed to providing trusted property solutions.-->
<!--</p>-->

<!--<h3>Purpose</h3>-->
<!--<p>This MOU outlines the responsibilities and obligations of the <//?= ucfirst($role) ?> in their relationship with PISHONSERV.</p>-->

<!--<h3>General Terms</h3>-->
<!--<ul>-->
<!--    <li>The <//?= ucfirst($role) ?> agrees to operate with integrity, transparency, and professionalism.</li>-->
<!--    <li>All property data or interactions must reflect accurate, up-to-date, and verifiable information.</li>-->
<!--    <li>The <//?= ucfirst($role) ?> shall comply with all relevant local and platform policies.</li>-->
<!--</ul>-->

<!--<//?php if ($role === 'agent'): ?>-->
<!--    <h3>Agent-Specific Responsibilities</h3>-->
<!--    <ul>-->
<!--        <li>Ensure properties listed are genuine and represented with owner consent.</li>-->
<!--        <li>Promptly respond to client inquiries and bookings.</li>-->
<!--        <li>Coordinate viewings and maintain positive client-agent interactions.</li>-->
<!--    </ul>-->
<!--<//?php elseif ($role === 'owner'): ?>-->
<!--    <h3>Property Owner Responsibilities</h3>-->
<!--    <ul>-->
<!--        <li>Provide complete and truthful property details (pricing, facilities, availability).</li>-->
<!--        <li>Ensure that properties listed are yours to manage, lease, or sell.</li>-->
<!--        <li>Ensure property is accessible and in suitable condition when listed.</li>-->
<!--    </ul>-->
<!--<//?php elseif ($role === 'hotel_owner'): ?>-->
<!--    <h3>Hotel Owner Responsibilities</h3>-->
<!--    <ul>-->
<!--        <li>Ensure all room availability, pricing, and amenities are up to date.</li>-->
<!--        <li>Provide excellent guest experiences as a PISHONSERV partner hotel.</li>-->
<!--        <li>Comply with hospitality regulations and tax obligations.</li>-->
<!--    </ul>-->
<!--<//?php elseif ($role === 'developer'): ?>-->
<!--    <h3>Developer Responsibilities</h3>-->
<!--    <ul>-->
<!--        <li>Ensure that all estates or buildings listed are approved and legally registered.</li>-->
<!--        <li>Provide buyers with correct and clear information (e.g., title, delivery timelines).</li>-->
<!--        <li>Handle customer communication ethically during the construction and sales process.</li>-->
<!--    </ul>-->
<!--<//?php endif; ?>-->

<!--<h3>Confidentiality</h3>-->
<!--<p>Both parties agree to maintain confidentiality of any sensitive information or personal data exchanged on the platform.</p>-->

<!--<h3>Duration and Termination</h3>-->
<!--<p>This MOU shall remain in effect unless either party terminates it in writing. Breach of terms may result in suspension or termination of platform access.</p>-->

<!--<div class="signature">-->
<!--    <p><strong>Signed:</strong> <//?= ucwords($signed_name) ?></p>-->
<!--    <p><strong>Role:</strong> <//?= ucfirst($role) ?></p>-->
<!--    <p><strong>Date:</strong> <//?= $date ?></p>-->
<!--</div>-->

<!--<div class="footer">-->
<!--    <p>PISHONSERV | Integrity. Excellence. Trust</p>-->
<!--    <p>www.pishonserv.com | inquiry@pishonserv.com</p>-->
<!--</div>-->

<!--</body>-->
<!--</html>-->
