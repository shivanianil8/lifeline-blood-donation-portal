<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'recipient')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";


/* =========================
   GET RECIPIENT ID
========================= */

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT recipient_id
     FROM recipients
     WHERE user_id = ? LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$recipient = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


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
    else
    {
        $recipient_id = $user_id;
    }
}
else
{
    $recipient_id = (int)$recipient['recipient_id'];
}


/* =========================
   VARIABLES
========================= */

$message = "";
$message_type = "";

$blood_group = "";
$units_required = "";
$hospital = "";
$location = "";
$required_date = "";
$priority = "normal";
$reason = "";


/* =========================
   HANDLE FORM SUBMISSION
========================= */

if (isset($_POST['submit_request']))
{
    $blood_group = trim($_POST['blood_group'] ?? '');

    $units_required = intval(
        $_POST['units_required'] ?? 0
    );

    $hospital = trim(
        $_POST['hospital'] ?? ''
    );

    $location = trim(
        $_POST['location'] ?? ''
    );

    $required_date = trim(
        $_POST['required_date'] ?? ''
    );

    $priority = trim(
        $_POST['priority'] ?? 'normal'
    );

    $reason = trim(
        $_POST['reason'] ?? ''
    );


    /* =========================
       VALIDATION
    ========================= */

    $valid_blood_groups = [
        "A+",
        "A-",
        "B+",
        "B-",
        "AB+",
        "AB-",
        "O+",
        "O-"
    ];

    $valid_priorities = [
        "normal",
        "urgent",
        "critical"
    ];


    if (!in_array($blood_group, $valid_blood_groups))
    {
        $message = "Please select a valid blood group.";
        $message_type = "error";
    }

    elseif ($units_required < 1)
    {
        $message = "Units required must be at least 1.";
        $message_type = "error";
    }

    elseif ($hospital == "")
    {
        $message = "Please enter the hospital name.";
        $message_type = "error";
    }

    elseif ($location == "")
    {
        $message = "Please enter the location.";
        $message_type = "error";
    }

    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $required_date))
    {
        $message = "Please select a valid required date.";
        $message_type = "error";
    }

    elseif (!in_array($priority, $valid_priorities))
    {
        $message = "Please select a valid priority.";
        $message_type = "error";
    }


    /* =========================
       CHECK DATE
    ========================= */

    if ($message == "")
    {
        $date_object = DateTime::createFromFormat(
            "Y-m-d",
            $required_date
        );

        if (
            !$date_object ||
            $date_object->format("Y-m-d") !== $required_date
        )
        {
            $message = "Please select a valid required date.";
            $message_type = "error";
        }
    }


    /* =========================
       INSERT REQUEST
    ========================= */

    if ($message == "")
    {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO blood_requests
            (
                recipient_id,
                blood_group,
                units_required,
                hospital,
                location,
                required_date,
                priority,
                reason,
                status
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );


        if (!$stmt)
        {
            $message = "Unable to prepare request.";
            $message_type = "error";
        }
        else
        {
            mysqli_stmt_bind_param(
                $stmt,
                "isisssss",
                $recipient_id,
                $blood_group,
                $units_required,
                $hospital,
                $location,
                $required_date,
                $priority,
                $reason
            );


            if (mysqli_stmt_execute($stmt))
            {
                $message = "Blood request submitted successfully.";
                $message_type = "success";

                $blood_group = "";
                $units_required = "";
                $hospital = "";
                $location = "";
                $required_date = "";
                $priority = "normal";
                $reason = "";
            }
            else
            {
                $message = "Failed to submit request: "
                    . mysqli_stmt_error($stmt);

                $message_type = "error";
            }


            mysqli_stmt_close($stmt);
        }
    }
}


/* =========================
   CURRENT PAGE
========================= */

include "recipient-layout.php";

?>


        <section class="content">


            <!-- HEADING -->

            <div class="page-heading">


                <p class="eyebrow">
                    BLOOD REQUEST
                </p>


                <h1>
                    Request blood
                </h1>


                <p class="subtitle">
                    Submit a blood request and let matching donors know you need help.
                </p>


            </div>



            <!-- MESSAGE -->

            <?php if ($message != "") { ?>

                <div
                    class="message <?php
                        echo htmlspecialchars($message_type);
                    ?>"
                >

                    <?php

                    echo htmlspecialchars($message);

                    ?>

                </div>

            <?php } ?>



            <!-- FORM CARD -->

            <div class="dashboard-card">


                <form method="POST">


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


                            <option
                                value="A+"
                                <?php
                                if ($blood_group == "A+")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                A+
                            </option>


                            <option
                                value="A-"
                                <?php
                                if ($blood_group == "A-")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                A-
                            </option>


                            <option
                                value="B+"
                                <?php
                                if ($blood_group == "B+")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                B+
                            </option>


                            <option
                                value="B-"
                                <?php
                                if ($blood_group == "B-")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                B-
                            </option>


                            <option
                                value="AB+"
                                <?php
                                if ($blood_group == "AB+")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                AB+
                            </option>


                            <option
                                value="AB-"
                                <?php
                                if ($blood_group == "AB-")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                AB-
                            </option>


                            <option
                                value="O+"
                                <?php
                                if ($blood_group == "O+")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                O+
                            </option>


                            <option
                                value="O-"
                                <?php
                                if ($blood_group == "O-")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                O-
                            </option>


                        </select>


                    </div>



                    <!-- UNITS -->

                    <div class="form-group">


                        <label for="units_required">
                            Units required
                        </label>


                        <input
                            type="number"
                            id="units_required"
                            name="units_required"
                            min="1"
                            value="<?php
                                echo htmlspecialchars(
                                    $units_required
                                );
                            ?>"
                            required
                        >


                    </div>



                    <!-- HOSPITAL -->

                    <div class="form-group">


                        <label for="hospital">
                            Hospital
                        </label>


                        <input
                            type="text"
                            id="hospital"
                            name="hospital"
                            placeholder="Enter hospital name"
                            value="<?php
                                echo htmlspecialchars(
                                    $hospital
                                );
                            ?>"
                            required
                        >


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
                            placeholder="Enter location"
                            value="<?php
                                echo htmlspecialchars(
                                    $location
                                );
                            ?>"
                            required
                        >


                    </div>



                    <!-- DATE -->

                    <div class="form-group">


                        <label for="required_date">
                            Required date
                        </label>


                        <input
                            type="date"
                            id="required_date"
                            name="required_date"
                            value="<?php
                                echo htmlspecialchars(
                                    $required_date
                                );
                            ?>"
                            required
                        >


                    </div>



                    <!-- PRIORITY -->

                    <div class="form-group">


                        <label for="priority">
                            Priority
                        </label>


                        <select
                            id="priority"
                            name="priority"
                            required
                        >


                            <option
                                value="normal"
                                <?php
                                if ($priority == "normal")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                Normal
                            </option>


                            <option
                                value="urgent"
                                <?php
                                if ($priority == "urgent")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                Urgent
                            </option>


                            <option
                                value="critical"
                                <?php
                                if ($priority == "critical")
                                {
                                    echo "selected";
                                }
                                ?>
                            >
                                Critical
                            </option>


                        </select>


                    </div>



                    <!-- REASON -->

                    <div class="form-group">


                        <label for="reason">
                            Reason
                        </label>


                        <textarea
                            id="reason"
                            name="reason"
                            rows="4"
                            placeholder="Briefly explain why blood is required"
                        ><?php
                            echo htmlspecialchars($reason);
                        ?></textarea>


                    </div>



                    <!-- BUTTON -->

                    <button
                        type="submit"
                        name="submit_request"
                        class="primary-button"
                    >
                        Submit blood request
                    </button>


                </form>


            </div>


        </section>


    </main>


</div>


</body>

</html>