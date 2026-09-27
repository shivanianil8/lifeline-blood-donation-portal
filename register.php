<?php

session_start();

include "config/database.php";

$message = "";
$success = "";

/*
|--------------------------------------------------------------------------
| PRESELECT ROLE
|--------------------------------------------------------------------------
*/

$selected_role = $_POST['role'] ?? ($_GET['role'] ?? 'donor');

if ($selected_role !== 'donor' && $selected_role !== 'recipient') {
    $selected_role = 'donor';
}


/*
|--------------------------------------------------------------------------
| HANDLE REGISTRATION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    $name = trim($_POST["name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';
    $phone = trim($_POST["phone"] ?? '');
    $role = $_POST["role"] ?? '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        empty($name) ||
        empty($email) ||
        empty($password) ||
        empty($phone)
    )
    {
        $message = "Please fill in all required fields.";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $message = "Please enter a valid email address.";
    }

    elseif ($role != "donor" && $role != "recipient")
    {
        $message = "Please select a valid account role (Donor or Recipient).";
    }

    elseif (strlen($password) < 6)
    {
        $message = "Password must be at least 6 characters long.";
    }

    elseif ($password !== $confirm_password)
    {
        $message = "Passwords do not match. Please verify and try again.";
    }

    else
    {
        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE EMAIL
        |--------------------------------------------------------------------------
        */

        $check = mysqli_prepare(
            $conn,
            "SELECT user_id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if (!$check)
        {
            $message = "Unable to process registration at this time.";
        }
        else
        {
            mysqli_stmt_bind_param(
                $check,
                "s",
                $email
            );

            mysqli_stmt_execute($check);

            $check_result = mysqli_stmt_get_result($check);

            $existing_user = mysqli_fetch_assoc($check_result);

            mysqli_stmt_close($check);


            if ($existing_user)
            {
                $message = "An account with this email address already exists. Please sign in instead.";
            }

            else
            {
                /*
                |--------------------------------------------------------------------------
                | START TRANSACTION
                |--------------------------------------------------------------------------
                */

                mysqli_begin_transaction($conn);

                try
                {
                    /*
                    |--------------------------------------------------------------------------
                    | GENERATE USER ID
                    |--------------------------------------------------------------------------
                    |
                    | TiDB production database does not automatically generate
                    | user_id, so we generate the next ID manually.
                    |
                    */

                    $id_result = mysqli_query(
                        $conn,
                        "SELECT COALESCE(MAX(user_id), 0) + 1 AS next_id
                         FROM users"
                    );

                    if (!$id_result)
                    {
                        throw new Exception("Unable to generate user ID.");
                    }

                    $id_row = mysqli_fetch_assoc($id_result);

                    $new_user_id = (int)$id_row['next_id'];


                    /*
                    |--------------------------------------------------------------------------
                    | HASH PASSWORD
                    |--------------------------------------------------------------------------
                    */

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    if ($hashed_password === false)
                    {
                        throw new Exception("Unable to secure password.");
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | INSERT USER
                    |--------------------------------------------------------------------------
                    */

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

                    if (!$stmt)
                    {
                        throw new Exception("Unable to prepare user registration.");
                    }

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

                    if (!mysqli_stmt_execute($stmt))
                    {
                        throw new Exception(
                            mysqli_stmt_error($stmt)
                        );
                    }

                    mysqli_stmt_close($stmt);


                    /*
                    |--------------------------------------------------------------------------
                    | INITIALIZE RECIPIENT PROFILE
                    |--------------------------------------------------------------------------
                    |
                    | recipients.recipient_id also does not auto-generate
                    | in the TiDB production database.
                    |
                    */

                    if ($role == "recipient")
                    {
                        /*
                        | Generate next recipient ID
                        */

                        $recipient_id_result = mysqli_query(
                            $conn,
                            "SELECT COALESCE(MAX(recipient_id), 0) + 1 AS next_id
                             FROM recipients"
                        );

                        if (!$recipient_id_result)
                        {
                            throw new Exception(
                                "Unable to generate recipient ID."
                            );
                        }

                        $recipient_id_row = mysqli_fetch_assoc(
                            $recipient_id_result
                        );

                        $new_recipient_id =
                            (int)$recipient_id_row['next_id'];


                        /*
                        | Insert recipient profile
                        */

                        $rec_init = mysqli_prepare(
                            $conn,
                            "INSERT INTO recipients
                            (
                                recipient_id,
                                user_id
                            )
                            VALUES (?, ?)"
                        );

                        if (!$rec_init)
                        {
                            throw new Exception(
                                "Unable to prepare recipient profile."
                            );
                        }

                        mysqli_stmt_bind_param(
                            $rec_init,
                            "ii",
                            $new_recipient_id,
                            $new_user_id
                        );

                        if (!mysqli_stmt_execute($rec_init))
                        {
                            throw new Exception(
                                mysqli_stmt_error($rec_init)
                            );
                        }

                        mysqli_stmt_close($rec_init);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | COMMIT
                    |--------------------------------------------------------------------------
                    */

                    mysqli_commit($conn);

                    $success =
                        "Your account has been created successfully. You can now sign in.";
                }

                catch (Exception $e)
                {
                    /*
                    |--------------------------------------------------------------------------
                    | ROLLBACK
                    |--------------------------------------------------------------------------
                    */

                    mysqli_rollback($conn);

                    $message =
                        "Registration failed. Please check your details and try again.";
                }
            }
        }
    }
}


include "includes/header.php";

?>


<section class="auth-split-wrapper">


    <!-- =========================================================
         LEFT SHOWCASE BANNER
    ========================================================== -->

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

            <cite>
                Clinical Transfusion Society
            </cite>

        </div>


        <div style="font-size: 13px; color: #78746F;">
            Safe & Confidential · Zero Registration Fees ·
            Verified Clinical Facilities
        </div>

    </div>



    <!-- =========================================================
         RIGHT FORM
    ========================================================== -->

    <div class="auth-split-form-container">

        <div class="auth-box">


            <!-- HEADER -->

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



            <!-- CARD -->

            <div class="auth-card">


                <!-- ERROR MESSAGE -->

                <?php if ($message != ""): ?>

                    <div class="message error">

                        <span>
                            <?php
                            echo htmlspecialchars($message);
                            ?>
                        </span>

                        <button
                            type="button"
                            class="alert-dismiss"
                            style="
                                background:none;
                                border:none;
                                color:inherit;
                                font-size:16px;
                                cursor:pointer;
                            "
                            aria-label="Dismiss"
                        >
                            &times;
                        </button>

                    </div>

                <?php endif; ?>



                <!-- SUCCESS MESSAGE -->

                <?php if ($success != ""): ?>

                    <div class="message success">

                        <div>

                            <strong>
                                Success!
                            </strong>

                            <?php
                            echo htmlspecialchars($success);
                            ?>

                            <div style="margin-top: 8px;">

                                <a
                                    href="login.php"
                                    style="
                                        color: #215939;
                                        font-weight: 700;
                                        text-decoration: underline;
                                    "
                                >
                                    Click here to Sign In now →
                                </a>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>



                <!-- =================================================
                     REGISTRATION FORM
                ================================================== -->

                <form
                    method="POST"
                    action="register.php"
                    class="register-form"
                >


                    <!-- ROLE SELECTION -->

                    <div class="form-group">

                        <label>
                            Select Account Type
                        </label>


                        <div class="role-grid">


                            <!-- DONOR -->

                            <div class="role-card-option">

                                <input
                                    type="radio"
                                    id="role-donor"
                                    name="role"
                                    value="donor"

                                    <?php
                                    if ($selected_role === 'donor')
                                    {
                                        echo 'checked';
                                    }
                                    ?>

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



                            <!-- RECIPIENT -->

                            <div class="role-card-option">

                                <input
                                    type="radio"
                                    id="role-recipient"
                                    name="role"
                                    value="recipient"

                                    <?php
                                    if ($selected_role === 'recipient')
                                    {
                                        echo 'checked';
                                    }
                                    ?>
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
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST['name'] ?? ''
                                );
                            ?>"
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
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST['email'] ?? ''
                                );
                            ?>"
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
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST['phone'] ?? ''
                                );
                            ?>"
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
                            style="
                                display: none;
                                color: #A9344B;
                            "
                        >
                            Passwords do not match.
                        </small>

                    </div>



                    <!-- SUBMIT -->

                    <button
                        type="submit"
                        name="register"
                        class="primary-button"
                        style="
                            width: 100%;
                            margin-top: 8px;
                        "
                    >
                        Create My Account
                    </button>


                </form>



                <!-- AUTH FOOTER -->

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