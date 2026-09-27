<?php

session_start();

include "config/database.php";

$message = "";
$success = "";

// Preselect role from GET parameter or POST or default to donor
$selected_role = $_POST['role'] ?? ($_GET['role'] ?? 'donor');

if ($selected_role !== 'donor' && $selected_role !== 'recipient') {
    $selected_role = 'donor';
}

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    $name = trim($_POST["name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';
    $phone = trim($_POST["phone"] ?? '');
    $role = $_POST["role"] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($phone))
    {
        $message = "Please fill in all required fields.";
    }
    elseif ($role != "donor" && $role != "recipient")
    {
        $message = "Please select a valid account role (Donor or Recipient).";
    }
    elseif (strlen($password) < 6)
    {
        $message = "Password must be at least 6 characters long.";
    }
    elseif (!empty($confirm_password) && $password !== $confirm_password)
    {
        $message = "Passwords do not match. Please verify and try again.";
    }
    else
    {
        $check = mysqli_prepare(
            $conn,
            "SELECT user_id FROM users WHERE email = ?"
        );

        if ($check)
        {
            mysqli_stmt_bind_param($check, "s", $email);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);

            if (mysqli_stmt_num_rows($check) > 0)
            {
                $message = "An account with this email address already exists. Please sign in instead.";
            }
            else
            {
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /*
                 * TiDB does not allow AUTO_INCREMENT to be added
                 * to the existing user_id column.
                 *
                 * Therefore, generate the next user ID explicitly.
                 */
                $id_result = mysqli_query(
                    $conn,
                    "SELECT COALESCE(MAX(user_id), 0) + 1 AS next_id FROM users"
                );

                if (!$id_result)
                {
                    $message = "Unable to generate user ID.";
                }
                else
                {
                    $id_row = mysqli_fetch_assoc($id_result);
                    $new_user_id = (int)$id_row['next_id'];

                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO users
                        (
                            user_id,
                            name,
                            email,
                            password,
                            phone,
                            role
                        )
                        VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    if ($stmt)
                    {
                        mysqli_stmt_bind_param(
                            $stmt,
                            "isssss",
                            $new_user_id,
                            $name,
                            $email,
                            $hashed_password,
                            $phone,
                            $role
                        );

                        if (mysqli_stmt_execute($stmt))
                        {
                            mysqli_stmt_close($stmt);

                            // Auto-initialize recipient profile record
                            // if role is recipient.
                            if ($role == "recipient")
                            {
                                $rec_init = mysqli_prepare(
                                    $conn,
                                    "INSERT INTO recipients (user_id) VALUES (?)"
                                );

                                if ($rec_init)
                                {
                                    mysqli_stmt_bind_param(
                                        $rec_init,
                                        "i",
                                        $new_user_id
                                    );

                                    mysqli_stmt_execute($rec_init);
                                    mysqli_stmt_close($rec_init);
                                }
                            }

                            $success = "Your account has been created successfully. You can now sign in.";
                        }
                        else
                        {
                            $message = "Registration failed. Please check your details and try again.";
                            mysqli_stmt_close($stmt);
                        }
                    }
                    else
                    {
                        $message = "Unable to process registration at this time.";
                    }
                }
            }

            mysqli_stmt_close($check);
        }
    }
}

include "includes/header.php";

?>

<section class="auth-split-wrapper">

    <!-- LEFT SHOWCASE BANNER -->
    <div class="auth-split-showcase">
        <div class="auth-showcase-content">

            <span
                class="eyebrow"
                style="color: #E88394; margin-bottom: 16px;"
            >
                JOIN THE LIFELINE NETWORK
            </span>

            <h2>
                Be the reason a patient goes home to their family.
            </h2>

            <p>
                Whether you are stepping up as a voluntary blood donor
                or seeking urgent transfusion support, our portal brings
                together compassion, speed, and clinical safety.
            </p>

        </div>

        <div class="auth-showcase-quote">

            <blockquote>
                “A single pint of blood can save up to three lives,
                and a single gesture of kindness can ripple across
                entire communities.”
            </blockquote>

            <cite>Clinical Transfusion Society</cite>

        </div>

        <div style="font-size: 13px; color: #78746F;">
            Safe & Confidential · Zero Registration Fees · Verified Clinical Facilities
        </div>

    </div>

    <!-- RIGHT FORM CARD -->
    <div class="auth-split-form-container">

        <div class="auth-box">

            <div class="auth-box-header">

                <span class="eyebrow">
                    NEW REGISTRATION
                </span>

                <h1>
                    Create your LIFELINE account
                </h1>

                <p>
                    Select your role and complete your registration below.
                </p>

            </div>

            <div class="auth-card">

                <?php if ($message != ""): ?>

                    <div class="message error">

                        <span>
                            <?php echo htmlspecialchars($message); ?>
                        </span>

                        <button
                            type="button"
                            class="alert-dismiss"
                            style="background:none; border:none; color:inherit; font-size:16px; cursor:pointer;"
                            aria-label="Dismiss"
                        >
                            &times;
                        </button>

                    </div>

                <?php endif; ?>


                <?php if ($success != ""): ?>

                    <div class="message success">

                        <div>

                            <strong>Success!</strong>

                            <?php echo htmlspecialchars($success); ?>

                            <div style="margin-top: 8px;">

                                <a
                                    href="login.php"
                                    style="color: #215939; font-weight: 700; text-decoration: underline;"
                                >
                                    Click here to Sign In now →
                                </a>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    action="register.php"
                    class="register-form"
                >

                    <!-- ROLE SELECTION CARDS -->
                    <div class="form-group">

                        <label>
                            Select Account Type
                        </label>

                        <div class="role-grid">

                            <div class="role-card-option">

                                <input
                                    type="radio"
                                    id="role-donor"
                                    name="role"
                                    value="donor"
                                    <?php if ($selected_role === 'donor') echo 'checked'; ?>
                                    required
                                >

                                <label
                                    for="role-donor"
                                    class="role-card-label"
                                >
                                    <span class="role-title">
                                        Blood Donor
                                    </span>

                                    <span class="role-desc">
                                        I want to voluntarily donate blood
                                    </span>
                                </label>

                            </div>


                            <div class="role-card-option">

                                <input
                                    type="radio"
                                    id="role-recipient"
                                    name="role"
                                    value="recipient"
                                    <?php if ($selected_role === 'recipient') echo 'checked'; ?>
                                >

                                <label
                                    for="role-recipient"
                                    class="role-card-label"
                                >
                                    <span class="role-title">
                                        Recipient
                                    </span>

                                    <span class="role-desc">
                                        I need blood for a patient
                                    </span>
                                </label>

                            </div>

                        </div>

                    </div>


                    <!-- FULL NAME -->
                    <div class="form-group">

                        <label for="name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                            placeholder="e.g. Dr. Jane Doe"
                            required
                        >

                    </div>


                    <!-- EMAIL -->
                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            placeholder="e.g. jane@example.com"
                            required
                            autocomplete="email"
                        >

                    </div>


                    <!-- PHONE -->
                    <div class="form-group">

                        <label for="phone">
                            Phone Number
                        </label>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                            placeholder="e.g. +1 234 567 8900"
                            maxlength="15"
                            required
                        >

                    </div>


                    <!-- PASSWORD -->
                    <div class="form-group">

                        <label for="password">
                            Password (Minimum 6 characters)
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a strong password"
                            minlength="6"
                            required
                            autocomplete="new-password"
                        >

                    </div>


                    <!-- CONFIRM PASSWORD -->
                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Repeat your password"
                            minlength="6"
                            required
                            autocomplete="new-password"
                        >

                        <small
                            id="password-mismatch-msg"
                            style="display: none; color: #A9344B;"
                        >
                            Passwords do not match.
                        </small>

                    </div>


                    <button
                        type="submit"
                        name="register"
                        class="primary-button"
                        style="width: 100%; margin-top: 8px;"
                    >
                        Create My Account
                    </button>

                </form>


                <div class="auth-footer">

                    <span>
                        Already have an account?
                    </span>

                    <a href="login.php">
                        Sign in to LIFELINE
                    </a>

                </div>

            </div>

        </div>

    </div>

</section>

<?php

include "includes/footer.php";

?>