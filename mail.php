<?php
// ============================================================
//  mail.php — Parigari Internship Application Handler
//  Place this file in the ROOT of your hosting (same folder
//  as index.html, careers.html, etc.)
// ============================================================

// ── Config ──────────────────────────────────────────────────
$to      = "contactus@parigari.org";
$subject = "New Internship Application — Parigari Trust";
// ────────────────────────────────────────────────────────────

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

// ── Sanitise text inputs ─────────────────────────────────────
function clean($val) {
    return htmlspecialchars(strip_tags(trim($val)));
}

$firstName  = clean($_POST["first_name"]  ?? "");
$lastName   = clean($_POST["last_name"]   ?? "");
$email      = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
$phone      = clean($_POST["phone"]       ?? "");
$role       = clean($_POST["role"]        ?? "");
$startDate  = clean($_POST["start_date"]  ?? "");
$education  = clean($_POST["education"]   ?? "");
$motivation = clean($_POST["motivation"]  ?? "");

// Basic validation
if (!$firstName || !$lastName || !$email || !$phone || !$role || !$motivation) {
    http_response_code(400);
    header("Location: careers.html?status=error");
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    header("Location: careers.html?status=error");
    exit();
}

// ── Build email body ─────────────────────────────────────────
$body  = "New Internship Application received from the Parigari website.\n";
$body .= str_repeat("─", 50) . "\n\n";
$body .= "Role Applied For : $role\n";
$body .= "Full Name        : $firstName $lastName\n";
$body .= "Email Address    : $email\n";
$body .= "Phone Number     : $phone\n";
$body .= "Available From   : $startDate\n";
$body .= "Education        : $education\n\n";
$body .= "Motivation / Cover Note:\n$motivation\n\n";
$body .= str_repeat("─", 50) . "\n";
$body .= "Submitted on: " . date("d M Y, h:i A") . "\n";

// ── Handle file attachments ───────────────────────────────────
$boundary  = md5(time());
$allowedTypes = [
    "application/pdf",
    "application/msword",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
];
$maxSize = 5 * 1024 * 1024; // 5 MB

$attachments = [];

foreach (["resume" => "Resume", "cover_letter" => "Cover Letter"] as $field => $label) {
    if (!empty($_FILES[$field]["name"]) && $_FILES[$field]["error"] === UPLOAD_ERR_OK) {
        $file = $_FILES[$field];

        // Validate size
        if ($file["size"] > $maxSize) {
            header("Location: careers.html?status=toolarge");
            exit();
        }

        // Validate MIME type
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file["tmp_name"]);
        if (!in_array($mimeType, $allowedTypes)) {
            header("Location: careers.html?status=badfile");
            exit();
        }

        $attachments[] = [
            "label"    => $label,
            "name"     => basename($file["name"]),
            "mime"     => $mimeType,
            "content"  => file_get_contents($file["tmp_name"])
        ];
    }
}

// ── Build MIME email ──────────────────────────────────────────
$headers  = "From: Parigari Website <noreply@parigari.org>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$message  = "--$boundary\r\n";
$message .= "Content-Type: text/plain; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$message .= $body . "\r\n";

foreach ($attachments as $att) {
    $encoded  = chunk_split(base64_encode($att["content"]));
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: {$att["mime"]}; name=\"{$att["name"]}\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"{$att["name"]}\"\r\n\r\n";
    $message .= $encoded . "\r\n";
}

$message .= "--$boundary--";

// ── Send email ────────────────────────────────────────────────
$sent = mail($to, $subject, $message, $headers);

// ── Send auto-reply to applicant ──────────────────────────────
if ($sent) {
    $replySubject = "We received your application — Parigari Social Welfare Trust";
    $replyBody    =
        "Dear $firstName,\n\n" .
        "Thank you for applying for the $role position at Parigari Social Welfare Trust.\n\n" .
        "We have received your application and will review it carefully. " .
        "Our team will get back to you within 5–7 working days.\n\n" .
        "In the meantime, feel free to reach us at:\n" .
        "📞 +91 9941309880 / +91 6381031622\n" .
        "✉️  contactus@parigari.org\n\n" .
        "With gratitude,\n" .
        "Parigari Social Welfare Trust\n" .
        "No.2/46, Bharathiyar Nagar, 2nd Street,\n" .
        "Ennore Beach Road, Chennai - 57.\n";

    $replyHeaders  = "From: Parigari Social Welfare Trust <contactus@parigari.org>\r\n";
    $replyHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
    mail($email, $replySubject, $replyBody, $replyHeaders);
}

// ── Redirect back ─────────────────────────────────────────────
header("Location: careers.html?status=" . ($sent ? "success" : "fail"));
exit();
?>
