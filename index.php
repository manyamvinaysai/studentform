<?php
declare(strict_types=1);

// 1. CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$success  = false;
$errorMsg = null;
$cloudId  = null;
$localId  = null;

$name  = '';
$email = '';
$phone = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $connLocal = null;
    $connCloud = null;

    try {
        $name  = trim((string)($_POST["name"] ?? ""));
        $email = trim((string)($_POST["email"] ?? ""));
        $phone = trim((string)($_POST["phone"] ?? ""));

        // Validation
        if (
            strlen($name) < 2 ||
            strlen($name) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150 ||
            !preg_match('/^[0-9]{10}$/', $phone)
        ) {
            throw new InvalidArgumentException(
                "Invalid input: Please provide a valid name (2-100 characters), valid email, and a 10-digit phone number."
            );
        }

        // 2. Connect to Local XAMPP MySQL
        $connLocal = new mysqli("127.0.0.1", "root", "", "student_db", 3306);
        $connLocal->set_charset("utf8mb4");

        // 3. Connect to Online Google Cloud SQL (MySQL 8.4)
        // REPLACE WITH THE EXACT IP COPIED FROM STEP 3:
        $cloudHost = "34.75.56.189";
        $cloudPort = 3306;
        $cloudDb   = "student_db"; // Change to student_db if your console shows an underscore
        $cloudUser = "adminuser";
        $cloudPass = "Kumar@2003";

        $connCloud = mysqli_init();
        $connCloud->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $connCloud->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

        // MYSQLI_CLIENT_SSL allows MySQL 8.4 caching_sha2 password transport
        $connected = @$connCloud->real_connect(
            $cloudHost,
            $cloudUser,
            $cloudPass,
            $cloudDb,
            $cloudPort,
            null,
            MYSQLI_CLIENT_SSL
        );

        if (!$connected) {
            // Automatic fallback if database uses an underscore instead
            $cloudDb = "student_db";
            $connCloud->real_connect(
                $cloudHost,
                $cloudUser,
                $cloudPass,
                $cloudDb,
                $cloudPort,
                null,
                MYSQLI_CLIENT_SSL
            );
        }

        $connCloud->set_charset("utf8mb4");

        // 4. Begin transactions across both databases
        $connLocal->begin_transaction();
        $connCloud->begin_transaction();

        // 5. Insert into Cloud SQL
        $stmtCloud = $connCloud->prepare("INSERT INTO students (name, email, phone) VALUES (?, ?, ?)");
        $stmtCloud->bind_param("sss", $name, $email, $phone);
        $stmtCloud->execute();
        $cloudId = (int)$stmtCloud->insert_id;
        $stmtCloud->close();

        // 6. Insert into Local XAMPP MySQL
        $stmtLocal = $connLocal->prepare("INSERT INTO students (name, email, phone) VALUES (?, ?, ?)");
        $stmtLocal->bind_param("sss", $name, $email, $phone);
        $stmtLocal->execute();
        $localId = (int)$stmtLocal->insert_id;
        $stmtLocal->close();

        // 7. Commit both operations
        $connCloud->commit();
        $connLocal->commit();

        $success = true;

    } catch (Throwable $e) {
        if ($connCloud instanceof mysqli) {
            try { $connCloud->rollback(); } catch (Throwable $t) {}
        }
        if ($connLocal instanceof mysqli) {
            try { $connLocal->rollback(); } catch (Throwable $t) {}
        }
        $errorMsg = $e->getMessage();
    } finally {
        if ($connCloud instanceof mysqli) {
            $connCloud->close();
        }
        if ($connLocal instanceof mysqli) {
            $connLocal->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Result</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <?php if ($success): ?>
        <div class="alert-box success">
            <h2>Registration Successful!</h2>
            <p>Details synchronized simultaneously to both databases:</p>
            <ul>
                <li><strong>Full Name:</strong> <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>Email:</strong> <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>Phone:</strong> <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>Cloud SQL Record ID:</strong> <?= $cloudId ?></li>
                <li><strong>Local MySQL Record ID:</strong> <?= $localId ?></li>
            </ul>
            <a class="btn-link" href="index.html">← Back to Registration Form</a>
        </div>
    <?php else: ?>
        <div class="alert-box error">
            <h2>Submission Failed</h2>
            <p><strong>Error:</strong> <?= htmlspecialchars($errorMsg ?? 'No submission data received.', ENT_QUOTES, 'UTF-8') ?></p>
            <a class="btn-link" href="index.html">← Try Again</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>