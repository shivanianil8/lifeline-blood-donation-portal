<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'recipient')
{
    header("Location: ../login.php");
    exit();
}

require_once "../config/database.php";
require_once "../includes/request_helpers.php";


/* =====================================================
   GET RECIPIENT ID
===================================================== */

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT recipient_id
     FROM recipients
     WHERE user_id = ?
     LIMIT 1"
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
    /*
    |--------------------------------------------------------------------------
    | AUTO-CREATE RECIPIENT PROFILE
    |--------------------------------------------------------------------------
    | TiDB does not automatically generate recipient_id.
    */

    $id_result = mysqli_query(
        $conn,
        "SELECT COALESCE(MAX(recipient_id), 0) + 1 AS next_id
         FROM recipients"
    );

    if (!$id_result)
    {
        die("Unable to generate recipient ID.");
    }

    $id_row = mysqli_fetch_assoc($id_result);

    $new_recipient_id = (int)$id_row['next_id'];


    $init = mysqli_prepare(
        $conn,
        "INSERT INTO recipients
        (
            recipient_id,
            user_id,
            blood_group_required,
            location
        )
        VALUES (?, ?, NULL, NULL)"
    );

    if (!$init)
    {
        die("Unable to prepare recipient profile.");
    }


    mysqli_stmt_bind_param(
        $init,
        "ii",
        $new_recipient_id,
        $user_id
    );


    if (!mysqli_stmt_execute($init))
    {
        mysqli_stmt_close($init);

        die("Unable to create recipient profile.");
    }


    mysqli_stmt_close($init);

    $recipient_id = $new_recipient_id;
}
else
{
    $recipient_id = (int)$recipient['recipient_id'];
}


/* =====================================================
   VARIABLES
===================================================== */

$message = "";
$message_type = "";

$blood_group = "";
$units_required = "";
$hospital = "";
$location = "";
$required_date = "";
$priority = "normal";
$reason = "";


/* =====================================================
   HANDLE FORM SUBMISSION
===================================================== */

if (isset($_POST['submit_request']))
{
    $blood_group = trim(
        $_POST['blood_group'] ?? ''
    );

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


    /* =================================================
       VALIDATION
    ================================================= */

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

    elseif (!preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $required_date
    ))
    {
        $message = "Please select a valid required date.";
        $message_type = "error";
    }

    elseif (!in_array($priority, $valid_priorities))
    {
        $message = "Please select a valid priority.";
        $message_type = "error";
    }


    /* =================================================
       CHECK DATE
    ================================================= */

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


    /* =================================================
       INSERT BLOOD REQUEST
    ================================================= */

    if ($message == "")
    {

        /*
        |--------------------------------------------------------------------------
        | GENERATE REQUEST ID FOR TIDB
        |--------------------------------------------------------------------------
        |
        | The production TiDB table does not auto-generate request_id.
        | Generate the next available ID manually.
        |
        */

        $request_id_result = mysqli_query(
            $conn,
            "SELECT COALESCE(MAX(request_id), 0) + 1 AS next_id
             FROM blood_requests"
        );


        if (!$request_id_result)
        {
            $message = "Unable to generate request ID.";
            $message_type = "error";
        }
        else
        {
            $request_id_row = mysqli_fetch_assoc(
                $request_id_result
            );

            $new_request_id =
                (int)$request_id_row['next_id'];


            /*
            |--------------------------------------------------------------------------
            | PREPARE INSERT
            |--------------------------------------------------------------------------
            */

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO blood_requests
                (
                    request_id,
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
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'pending'
                )"
            );


            if (!$stmt)
            {
                $message =
                    "Unable to prepare blood request.";

                $message_type = "error";
            }
            else
            {

                /*
                |--------------------------------------------------------------------------
                | BIND VALUES
                |--------------------------------------------------------------------------
                |
                | request_id       = i
                | recipient_id     = i
                | blood_group      = s
                | units_required   = i
                | hospital         = s
                | location         = s
                | required_date    = s
                | priority         = s
                | reason           = s
                |
                */

                mysqli_stmt_bind_param(
                    $stmt,
                    "iisis ssss",
                    $new_request_id,
                    $recipient_id,
                    $blood_group,
                    $units_required,
                    $hospital,
                    $location,
                    $required_date,
                    $priority,
                    $reason
                );


                /*
                |--------------------------------------------------------------------------
                | EXECUTE
                |--------------------------------------------------------------------------
                */

                if (mysqli_stmt_execute($stmt))
                {
                    $message =
                        "Blood request submitted successfully.";

                    $message_type = "success";


                    /*
                    | Clear form
                    */

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
                    $message =
                        "Failed to submit blood request.";

                    $message_type = "error";
                }


                mysqli_stmt_close($stmt);
            }
        }
    }
}


/* =====================================================
   CURRENT PAGE
===================================================== */

$current_page = basename(
    $_SERVER['PHP_SELF']
);

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Recipient | Lifeline
    </title>

    <link
        rel="stylesheet"
        href="../css/dashboard.css"
    >

</head>


<body>


<div class="app">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">


        <!-- BRAND -->

        <div class="brand">

            <span class="brand-mark">
                +
            </span>

            <span>
                LIFELINE
            </span>

        </div>


        <div class="sidebar-label">
            RECIPIENT
        </div>


        <!-- NAVIGATION -->

        <nav>


            <a
                href="dashboard.php"
                class="nav-item <?php

                    if ($current_page == 'dashboard.php')
                    {
                        echo 'active';
                    }

                ?>"
            >

                <span>
                    ⌂
                </span>

                Overview

            </a>


            <a
                href="profile.php"
                class="nav-item <?php

                    if ($current_page == 'profile.php')
                    {
                        echo 'active';
                    }

                ?>"
            >

                <span>
                    ◯
                </span>

                Profile

            </a>


            <a
                href="create_request.php"
                class="nav-item <?php

                    if ($current_page == 'create_request.php')
                    {
                        echo 'active';
                    }

                ?>"
            >

                <span>
                    □
                </span>

                Create Request

            </a>


            <a
                href="requests.php"
                class="nav-item <?php

                    if ($current_page == 'requests.php')
                    {
                        echo 'active';
                    }

                ?>"
            >

                <span>
                    □
                </span>

                My Requests

            </a>


        </nav>


        <!-- BOTTOM -->

        <div class="sidebar-bottom">


            <a
                href="notifications.php"
                class="nav-item <?php

                    if ($current_page == 'notifications.php')
                    {
                        echo 'active';
                    }

                ?>"
            >

                <span>
                    ○
                </span>

                Notifications

            </a>


            <a
                href="../logout.php"
                class="nav-item logout"
            >

                <span>
                    ↪
                </span>

                Log out

            </a>


        </div>


    </aside>



    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main">


        <!-- TOP BAR -->

        <header class="topbar">


            <div>

                <p class="eyebrow">
                    RECIPIENT PORTAL
                </p>

            </div>


            <div class="user">


                <div class="avatar">

                    <?php

                    echo strtoupper(
                        substr(
                            $_SESSION['name'],
                            0,
                            1
                        )
                    );

                    ?>

                </div>


                <div>

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $_SESSION['name']
                        );

                        ?>

                    </strong>


                    <small>
                        Recipient
                    </small>

                </div>


            </div>


        </header>



        <!-- =====================================================
             PAGE CONTENT
        ====================================================== -->

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
                        echo htmlspecialchars(
                            $message_type
                        );
                    ?>"
                >

                    <?php

                    echo htmlspecialchars(
                        $message
                    );

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

                            echo htmlspecialchars(
                                $reason
                            );

                        ?></textarea>


                    </div>



                    <!-- SUBMIT -->

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