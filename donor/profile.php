<?php

include "../includes/auth.php";
include "../config/database.php";


/* =========================
   DONOR ACCESS CHECK
========================= */

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'donor')
{
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$message = "";
$success = "";


/* =========================
   GET USER DETAILS
========================= */

$user_stmt = mysqli_prepare(
    $conn,
    "SELECT name, email, phone
     FROM users
     WHERE user_id = ?"
);

mysqli_stmt_bind_param(
    $user_stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($user_stmt);

$user_result = mysqli_stmt_get_result($user_stmt);

$user = mysqli_fetch_assoc($user_result);

mysqli_stmt_close($user_stmt);


/* =========================
   GET DONOR DETAILS
========================= */

$donor_stmt = mysqli_prepare(
    $conn,
    "SELECT blood_group,
            age,
            gender,
            location,
            address,
            last_donation_date
     FROM donors
     WHERE user_id = ?"
);

mysqli_stmt_bind_param(
    $donor_stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($donor_stmt);

$donor_result = mysqli_stmt_get_result($donor_stmt);

$donor = mysqli_fetch_assoc($donor_result);

mysqli_stmt_close($donor_stmt);


/* =========================
   DEFAULT VALUES
========================= */

$blood_group = $donor['blood_group'] ?? "";
$age = $donor['age'] ?? "";
$gender = $donor['gender'] ?? "";
$location = $donor['location'] ?? "";
$address = $donor['address'] ?? "";
$last_donation_date = $donor['last_donation_date'] ?? "";


/* =========================
   UPDATE PROFILE
========================= */

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    $blood_group = $_POST["blood_group"] ?? "";
    $age = $_POST["age"] ?? "";
    $gender = $_POST["gender"] ?? "";
    $location = trim($_POST["location"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $last_donation_date = $_POST["last_donation_date"] ?? "";


    /* ---------- VALIDATION ---------- */

    if ($blood_group == "")
    {
        $message = "Please select your blood group.";
    }

    elseif ($age == "" || $age < 18 || $age > 65)
    {
        $message = "Age must be between 18 and 65.";
    }

    elseif ($gender == "")
    {
        $message = "Please select your gender.";
    }

    elseif ($location == "")
    {
        $message = "Please enter your location.";
    }

    else
    {
        /* Convert empty date to NULL */

        $date_value = ($last_donation_date == "")
            ? null
            : $last_donation_date;


        /* ---------- CHECK EXISTING PROFILE ---------- */

        $check_stmt = mysqli_prepare(
            $conn,
            "SELECT donor_id
             FROM donors
             WHERE user_id = ?"
        );

        mysqli_stmt_bind_param(
            $check_stmt,
            "i",
            $user_id
        );

        mysqli_stmt_execute($check_stmt);

        mysqli_stmt_store_result($check_stmt);

        $exists = mysqli_stmt_num_rows($check_stmt) > 0;

        mysqli_stmt_close($check_stmt);


        /* ---------- UPDATE EXISTING PROFILE ---------- */

        if ($exists)
        {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE donors
                 SET blood_group = ?,
                     age = ?,
                     gender = ?,
                     location = ?,
                     address = ?,
                     last_donation_date = ?
                 WHERE user_id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sissssi",
                $blood_group,
                $age,
                $gender,
                $location,
                $address,
                $date_value,
                $user_id
            );
        }


        /* ---------- INSERT NEW PROFILE ---------- */

        else
        {
            /*
             * TiDB is not auto-generating donor_id.
             * Generate the next donor ID manually.
             */

            $id_result = mysqli_query(
                $conn,
                "SELECT COALESCE(MAX(donor_id), 0) + 1 AS next_id
                 FROM donors"
            );

            if (!$id_result)
            {
                $message = "Unable to generate donor ID.";
                $stmt = null;
            }
            else
            {
                $id_row = mysqli_fetch_assoc($id_result);

                $new_donor_id = (int)$id_row['next_id'];


                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO donors
                    (
                        donor_id,
                        user_id,
                        blood_group,
                        age,
                        gender,
                        location,
                        address,
                        last_donation_date
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if ($stmt)
                {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "iisis sss",
                        $new_donor_id,
                        $user_id,
                        $blood_group,
                        $age,
                        $gender,
                        $location,
                        $address,
                        $date_value
                    );
                }
            }
        }


        /* ---------- EXECUTE ---------- */

        if ($stmt)
        {
            if (mysqli_stmt_execute($stmt))
            {
                $success = "Your donor profile has been updated successfully.";
            }
            else
            {
                $message = "Unable to update your profile. Please try again.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}


/* =========================
   PAGE LAYOUT
========================= */

include "donor-layout.php";

?>

<section class="content profile-page">

    <!-- PAGE HEADING -->

    <div class="page-heading">

        <p class="eyebrow">
            PROFILE
        </p>

        <h1>
            Your donor profile
        </h1>

        <p class="subtitle">
            Keep your information up to date so you can be contacted when your blood type is needed.
        </p>

    </div>


    <!-- PROFILE CARD -->

    <div class="profile-card">

        <div class="profile-card-header">

            <div>

                <p class="card-label">
                    PERSONAL INFORMATION
                </p>

                <h2>
                    Donor details
                </h2>

            </div>


            <div class="verification-badge">

                <span></span>

                Verification pending

            </div>

        </div>


        <!-- MESSAGES -->

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


            <!-- ROW 1 -->

            <div class="profile-form-grid">


                <!-- NAME -->

                <div class="form-group">

                    <label>
                        Full name
                    </label>

                    <input
                        type="text"
                        value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                        disabled
                    >

                    <small>
                        Name is linked to your account.
                    </small>

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label>
                        Email address
                    </label>

                    <input
                        type="email"
                        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                        disabled
                    >

                    <small>
                        Email is linked to your account.
                    </small>

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label>
                        Phone number
                    </label>

                    <input
                        type="text"
                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                        disabled
                    >

                    <small>
                        Contact details are managed through your account.
                    </small>

                </div>


                <!-- BLOOD GROUP -->

                <div class="form-group">

                    <label for="blood_group">
                        Blood group
                    </label>

                    <select
                        id="blood_group"
                        name="blood_group"
                        required
                    >

                        <option value="">
                            Select blood group
                        </option>

                        <option value="A+"
                            <?php if ($blood_group == "A+") echo "selected"; ?>>
                            A+
                        </option>

                        <option value="A-"
                            <?php if ($blood_group == "A-") echo "selected"; ?>>
                            A-
                        </option>

                        <option value="B+"
                            <?php if ($blood_group == "B+") echo "selected"; ?>>
                            B+
                        </option>

                        <option value="B-"
                            <?php if ($blood_group == "B-") echo "selected"; ?>>
                            B-
                        </option>

                        <option value="AB+"
                            <?php if ($blood_group == "AB+") echo "selected"; ?>>
                            AB+
                        </option>

                        <option value="AB-"
                            <?php if ($blood_group == "AB-") echo "selected"; ?>>
                            AB-
                        </option>

                        <option value="O+"
                            <?php if ($blood_group == "O+") echo "selected"; ?>>
                            O+
                        </option>

                        <option value="O-"
                            <?php if ($blood_group == "O-") echo "selected"; ?>>
                            O-
                        </option>

                    </select>

                </div>


                <!-- AGE -->

                <div class="form-group">

                    <label for="age">
                        Age
                    </label>

                    <input
                        type="number"
                        id="age"
                        name="age"
                        min="18"
                        max="65"
                        value="<?php echo htmlspecialchars($age); ?>"
                        placeholder="Enter your age"
                        required
                    >

                </div>


                <!-- GENDER -->

                <div class="form-group">

                    <label for="gender">
                        Gender
                    </label>

                    <select
                        id="gender"
                        name="gender"
                        required
                    >

                        <option value="">
                            Select gender
                        </option>

                        <option value="Male"
                            <?php if ($gender == "Male") echo "selected"; ?>>
                            Male
                        </option>

                        <option value="Female"
                            <?php if ($gender == "Female") echo "selected"; ?>>
                            Female
                        </option>

                        <option value="Other"
                            <?php if ($gender == "Other") echo "selected"; ?>>
                            Other
                        </option>

                    </select>

                </div>


                <!-- LOCATION -->

                <div class="form-group">

                    <label for="location">
                        Location
                    </label>

                    <input
                        type="text"
                        id="location"
                        name="location"
                        value="<?php echo htmlspecialchars($location); ?>"
                        placeholder="City / Town"
                        required
                    >

                    <small>
                        Helps match you with nearby blood requests.
                    </small>

                </div>


                <!-- LAST DONATION -->

                <div class="form-group">

                    <label for="last_donation_date">
                        Last donation date
                    </label>

                    <input
                        type="date"
                        id="last_donation_date"
                        name="last_donation_date"
                        value="<?php echo htmlspecialchars($last_donation_date); ?>"
                    >

                    <small>
                        Leave blank if you have never donated.
                    </small>

                </div>


            </div>


            <!-- ADDRESS -->

            <div class="form-group full-width">

                <label for="address">
                    Address
                </label>

                <textarea
                    id="address"
                    name="address"
                    rows="4"
                    placeholder="Enter your full address"
                ><?php echo htmlspecialchars($address); ?></textarea>

            </div>


            <!-- SAVE -->

            <div class="profile-actions">

                <button
                    type="submit"
                    class="primary-button"
                >
                    Save profile
                </button>

            </div>


        </form>

    </div>

</section>

</main>

</div>

</body>

</html>