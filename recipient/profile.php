<?php

include "../includes/auth.php";
require_once "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'recipient')
{
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$message = "";
$success = "";


/* =====================================================
   GET USER DETAILS
===================================================== */

$user_stmt = mysqli_prepare(
    $conn,
    "SELECT name, email, phone FROM users WHERE user_id = ? LIMIT 1"
);

mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);


/* =====================================================
   ENSURE RECIPIENT RECORD EXISTS
===================================================== */

$rec_stmt = mysqli_prepare(
    $conn,
    "SELECT recipient_id FROM recipients WHERE user_id = ? LIMIT 1"
);

mysqli_stmt_bind_param($rec_stmt, "i", $user_id);
mysqli_stmt_execute($rec_stmt);
$rec_res = mysqli_stmt_get_result($rec_stmt);
$recipient = mysqli_fetch_assoc($rec_res);
mysqli_stmt_close($rec_stmt);

if (!$recipient)
{
    $init = mysqli_prepare($conn, "INSERT INTO recipients (user_id) VALUES (?)");
    if ($init)
    {
        mysqli_stmt_bind_param($init, "i", $user_id);
        mysqli_stmt_execute($init);
        $recipient_id = mysqli_insert_id($conn);
        mysqli_stmt_close($init);
    }
}
else
{
    $recipient_id = (int)$recipient['recipient_id'];
}


/* =====================================================
   UPDATE PROFILE
===================================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    $phone = trim($_POST["phone"] ?? "");

    if (empty($phone))
    {
        $message = "Please enter your contact phone number.";
    }
    else
    {
        $update_stmt = mysqli_prepare(
            $conn,
            "UPDATE users SET phone = ? WHERE user_id = ?"
        );

        if ($update_stmt)
        {
            mysqli_stmt_bind_param($update_stmt, "si", $phone, $user_id);

            if (mysqli_stmt_execute($update_stmt))
            {
                $success = "Contact details updated successfully.";
                $user['phone'] = $phone;
            }
            else
            {
                $message = "Unable to update details. Please try again.";
            }

            mysqli_stmt_close($update_stmt);
        }
    }
}

include "recipient-layout.php";

?>

<section class="content profile-page">

    <div class="page-heading">
        <p class="eyebrow">
            ACCOUNT
        </p>

        <h1>
            Recipient profile
        </h1>

        <p class="subtitle">
            Manage your account and contact details for blood requests.
        </p>
    </div>


    <div class="profile-card">

        <div class="profile-card-header">
            <div>
                <p class="card-label">
                    ACCOUNT DETAILS
                </p>

                <h2>
                    Recipient information
                </h2>
            </div>

            <div class="verification-badge">
                <span></span>
                Active Recipient
            </div>
        </div>

        <?php if ($message != "") { ?>
            <div class="message error">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php } ?>

        <?php if ($success != "") { ?>
            <div class="message success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php } ?>

        <form method="POST">
            <div class="profile-form-grid">

                <div class="form-group">
                    <label>
                        Full name
                    </label>
                    <input
                        type="text"
                        value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                        disabled
                    >
                    <small>Name is registered with your account.</small>
                </div>

                <div class="form-group">
                    <label>
                        Email address
                    </label>
                    <input
                        type="email"
                        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                        disabled
                    >
                    <small>Email is linked to your account credentials.</small>
                </div>

                <div class="form-group">
                    <label for="phone">
                        Phone number
                    </label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                        placeholder="Enter your contact phone"
                        required
                    >
                    <small>Used by hospitals and donors to coordinate blood donation.</small>
                </div>

                <div class="form-group">
                    <label>
                        Recipient ID
                    </label>
                    <input
                        type="text"
                        value="#<?php echo htmlspecialchars($recipient_id ?? '1'); ?>"
                        disabled
                    >
                    <small>System reference ID for your blood requests.</small>
                </div>

            </div>

            <div class="profile-actions" style="margin-top: 20px;">
                <button type="submit" class="primary-button" style="max-width: 200px;">
                    Update details
                </button>
            </div>
        </form>

    </div>

</section>

</main>
</div>
</body>
</html>
