<?php

session_start();

include "config/database.php";

$message = "";

if (isset($_POST['login']))
{
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password))
    {
        $message = "Please enter both your email address and password.";
    }
    else
    {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT user_id, name, email, password, role FROM users WHERE email = ? LIMIT 1"
        );

        if ($stmt)
        {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($user = mysqli_fetch_assoc($result))
            {
                if (password_verify($password, $user['password']))
                {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];

                    mysqli_stmt_close($stmt);

                    if ($user['role'] == 'donor')
                    {
                        header("Location: donor/dashboard.php");
                        exit();
                    }
                    elseif ($user['role'] == 'recipient')
                    {
                        header("Location: recipient/dashboard.php");
                        exit();
                    }
                    elseif ($user['role'] == 'admin')
                    {
                        header("Location: admin/dashboard.php");
                        exit();
                    }
                    else
                    {
                        $message = "Invalid account role.";
                    }
                }
                else
                {
                    $message = "Incorrect password. Please verify and try again.";
                }
            }
            else
            {
                $message = "No account found registered with this email address.";
            }

            mysqli_stmt_close($stmt);
        }
        else
        {
            $message = "Unable to process login right now. Please try again.";
        }
    }
}

include "includes/header.php";

?>

<section class="auth-split-wrapper">

    <!-- LEFT SHOWCASE BANNER -->
    <div class="auth-split-showcase">
        <div class="auth-showcase-content">
            <span class="eyebrow" style="color: #E88394; margin-bottom: 16px;">LIFELINE CLINICAL NETWORK</span>
            <h2>Precision coordination for emergency blood care.</h2>
            <p>
                Access your personalized dashboard to manage blood donations, track upcoming hospital appointments, or monitor active transfusion requests.
            </p>
        </div>

        <div class="auth-showcase-quote">
            <blockquote>
                “The blood you donate gives someone another chance at life. One day that someone may be a close relative, a friend, or you.”
            </blockquote>
            <cite>World Health Organization · Blood Safety</cite>
        </div>

        <div style="font-size: 13px; color: #78746F;">
            Encrypted session · 256-bit security · Healthcare data compliance
        </div>
    </div>

    <!-- RIGHT FORM CARD -->
    <div class="auth-split-form-container">
        <div class="auth-box">

            <div class="auth-box-header">
                <span class="eyebrow">PORTAL ACCESS</span>
                <h1>Sign in to LIFELINE</h1>
                <p>Welcome back. Please enter your credentials to continue.</p>
            </div>

            <div class="auth-card">

                <?php if ($message != ""): ?>
                    <div class="message error">
                        <span><?php echo htmlspecialchars($message); ?></span>
                        <button type="button" class="alert-dismiss" style="background:none; border:none; color:inherit; font-size:16px; cursor:pointer;" aria-label="Dismiss">&times;</button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">

                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            placeholder="e.g. yourname@example.com"
                            required
                            autocomplete="email"
                        >
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label for="password" style="margin-bottom: 0;">Password</label>
                        </div>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <button
                        type="submit"
                        name="login"
                        class="primary-button"
                        style="width: 100%; margin-top: 8px;"
                    >
                        Sign in to Account
                    </button>

                </form>

                <div class="auth-footer">
                    <span>Don't have an account yet?</span>
                    <a href="register.php">Join the LIFELINE network</a>
                </div>

            </div>

        </div>
    </div>

</section>

<?php
include "includes/footer.php";
?>