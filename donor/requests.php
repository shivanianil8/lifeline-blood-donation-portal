<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'donor')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/request_helpers.php";
include "../includes/notification_helpers.php";


/* =====================================================
   GET CURRENT DONOR
===================================================== */

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT donor_id, blood_group, location
     FROM donors
     WHERE user_id = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$donor = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$donor)
{
    $donor_id = 0;
    $blood_group = "";
    $donor_location = "Not set";
}
else
{
    $donor_id = (int)$donor['donor_id'];
    $blood_group = $donor['blood_group'] ?? "";
    $donor_location = $donor['location'] ?? "Not set";
}


/* =====================================================
   HANDLE I CAN DONATE
===================================================== */

$message = "";
$message_type = "";

if (isset($_POST['donate_request']))
{
    if ($donor_id === 0 || empty($blood_group))
    {
        $message = "Please complete your donor profile before volunteering.";
        $message_type = "error";
    }
    else
    {
        $request_id = intval(
            $_POST['request_id'] ?? 0
        );

        if ($request_id <= 0)
        {
            $message = "Invalid blood request.";
            $message_type = "error";
        }
        else
        {
            mysqli_begin_transaction($conn);

            try
            {
                /* =================================================
                   1. FETCH REQUEST DETAILS
                ================================================= */

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT
                        request_id,
                        blood_group,
                        units_required,
                        hospital,
                        location,
                        required_date,
                        priority,
                        status
                     FROM blood_requests
                     WHERE request_id = ?
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $request_id
                );

                mysqli_stmt_execute($stmt);

                $res = mysqli_stmt_get_result($stmt);

                $request = mysqli_fetch_assoc($res);

                mysqli_stmt_close($stmt);


                if (!$request)
                {
                    throw new Exception(
                        "This blood request was not found."
                    );
                }


                /* =================================================
                   2. VALIDATE REQUEST IS ACTIVE
                ================================================= */

                $req_status = strtolower(
                    $request['status'] ?? 'pending'
                );


                if ($req_status === 'cancelled')
                {
                    throw new Exception(
                        "This blood request has been cancelled."
                    );
                }


                if ($req_status === 'fulfilled')
                {
                    throw new Exception(
                        "This request has already been fulfilled."
                    );
                }


                /* =================================================
                   3. VERIFY BLOOD GROUP COMPATIBILITY
                ================================================= */

                if ($request['blood_group'] !== $blood_group)
                {
                    throw new Exception(
                        "Blood group mismatch: Request requires "
                        . htmlspecialchars($request['blood_group'])
                        . "."
                    );
                }


                /* =================================================
                   4. VERIFY REMAINING UNITS
                ================================================= */

                $units_summary = get_request_units_summary(
                    $conn,
                    $request_id
                );


                if ($units_summary['remaining'] <= 0)
                {
                    throw new Exception(
                        "This request has already been fulfilled."
                    );
                }


                /* =================================================
                   5. CHECK FOR EXISTING ACTIVE APPOINTMENT
                ================================================= */

                $check = mysqli_prepare(
                    $conn,
                    "SELECT appointment_id
                     FROM appointments
                     WHERE donor_id = ?
                     AND hospital = ?
                     AND appointment_date = ?
                     AND status IN ('pending', 'confirmed')"
                );


                mysqli_stmt_bind_param(
                    $check,
                    "iss",
                    $donor_id,
                    $request['hospital'],
                    $request['required_date']
                );


                mysqli_stmt_execute($check);

                $check_result = mysqli_stmt_get_result($check);

                $existing = mysqli_fetch_assoc(
                    $check_result
                );

                mysqli_stmt_close($check);


                if ($existing)
                {
                    throw new Exception(
                        "You already have an active scheduled appointment for this request."
                    );
                }


                /* =================================================
                   6. GENERATE APPOINTMENT ID FOR TIDB
                ================================================= */

                $appointment_id_result = mysqli_query(
                    $conn,
                    "SELECT COALESCE(MAX(appointment_id), 0) + 1 AS next_id
                     FROM appointments"
                );


                if (!$appointment_id_result)
                {
                    throw new Exception(
                        "Unable to generate appointment ID."
                    );
                }


                $appointment_id_row = mysqli_fetch_assoc(
                    $appointment_id_result
                );


                $new_appointment_id =
                    (int)$appointment_id_row['next_id'];


                /* =================================================
                   7. CREATE APPOINTMENT
                ================================================= */

                $appointment_time = "10:00:00";


                $insert = mysqli_prepare(
                    $conn,
                    "INSERT INTO appointments
                    (
                        appointment_id,
                        donor_id,
                        request_id,
                        hospital,
                        appointment_date,
                        appointment_time,
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
                        'pending'
                    )"
                );


                if (!$insert)
                {
                    throw new Exception(
                        "Unable to prepare appointment."
                    );
                }


                mysqli_stmt_bind_param(
                    $insert,
                    "iiisss",
                    $new_appointment_id,
                    $donor_id,
                    $request_id,
                    $request['hospital'],
                    $request['required_date'],
                    $appointment_time
                );


                if (!mysqli_stmt_execute($insert))
                {
                    throw new Exception(
                        "Failed to create appointment: "
                        . mysqli_stmt_error($insert)
                    );
                }


                mysqli_stmt_close($insert);


                /* =================================================
                   8. UPDATE REQUEST LIFECYCLE
                ================================================= */

                sync_request_status(
                    $conn,
                    $request_id
                );


                /* =================================================
                   9. NOTIFY RECIPIENT
                ================================================= */

                $rec_stmt = mysqli_prepare(
                    $conn,
                    "SELECT r.user_id
                     FROM blood_requests br
                     JOIN recipients r
                       ON br.recipient_id = r.recipient_id
                       OR br.recipient_id = r.user_id
                     WHERE br.request_id = ?
                     LIMIT 1"
                );


                if ($rec_stmt)
                {
                    mysqli_stmt_bind_param(
                        $rec_stmt,
                        "i",
                        $request_id
                    );

                    mysqli_stmt_execute($rec_stmt);

                    $rec_res = mysqli_stmt_get_result(
                        $rec_stmt
                    );

                    $rec_row = mysqli_fetch_assoc(
                        $rec_res
                    );

                    mysqli_stmt_close($rec_stmt);


                    if (
                        $rec_row &&
                        !empty($rec_row['user_id'])
                    )
                    {
                        $donor_display_name =
                            !empty($_SESSION['name'])
                            ? $_SESSION['name']
                            : 'A voluntary donor';


                        create_notification(
                            $conn,
                            (int)$rec_row['user_id'],
                            "Your blood request #$request_id received a donor response from $donor_display_name for "
                            . date(
                                "d M Y",
                                strtotime(
                                    $request['required_date']
                                )
                            ),
                            'donor_response',
                            $request_id
                        );
                    }
                }


                /* =================================================
                   10. NOTIFY DONOR
                ================================================= */

                create_notification(
                    $conn,
                    $user_id,
                    "Your appointment has been scheduled at "
                    . htmlspecialchars($request['hospital'])
                    . " for "
                    . date(
                        "d M Y",
                        strtotime(
                            $request['required_date']
                        )
                    ),
                    'appointment',
                    $request_id
                );


                /* =================================================
                   11. COMMIT TRANSACTION
                ================================================= */

                mysqli_commit($conn);


                $message =
                    "Thank you for volunteering! Your donation appointment has been scheduled.";

                $message_type = "success";
            }

            catch (Exception $e)
            {
                mysqli_rollback($conn);

                $message = $e->getMessage();

                $message_type = "error";
            }
        }
    }
}


/* =====================================================
   GET MATCHING BLOOD REQUESTS
===================================================== */

$requests = array();


if (!empty($blood_group))
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            request_id,
            blood_group,
            units_required,
            hospital,
            location,
            required_date,
            priority,
            reason,
            status
         FROM blood_requests
         WHERE blood_group = ?
         AND status IN (
             'pending',
             'matched',
             'partially_fulfilled'
         )
         ORDER BY
            CASE
                WHEN priority = 'critical' THEN 1
                WHEN priority = 'urgent' THEN 2
                ELSE 3
            END,
            required_date ASC"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $blood_group
    );


    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);


    while ($row = mysqli_fetch_assoc($result))
    {
        $requests[] = $row;
    }


    mysqli_stmt_close($stmt);
}


/* =====================================================
   CHECK WHICH REQUESTS HAVE ACTIVE APPOINTMENTS
===================================================== */

$donor_active_req_ids = [];


if ($donor_id > 0)
{
    $chk_appt = mysqli_prepare(
        $conn,
        "SELECT request_id
         FROM appointments
         WHERE donor_id = ?
         AND status IN ('pending', 'confirmed')"
    );


    if ($chk_appt)
    {
        mysqli_stmt_bind_param(
            $chk_appt,
            "i",
            $donor_id
        );


        mysqli_stmt_execute($chk_appt);

        $res = mysqli_stmt_get_result(
            $chk_appt
        );


        while ($r = mysqli_fetch_assoc($res))
        {
            $donor_active_req_ids[] =
                (int)$r['request_id'];
        }


        mysqli_stmt_close($chk_appt);
    }
}


/* =====================================================
   LAYOUT
===================================================== */

include "donor-layout.php";

?>


<section class="content">


    <div class="page-heading">

        <p class="eyebrow">
            BLOOD REQUESTS
        </p>


        <h1>
            Requests that need you
        </h1>


        <p class="subtitle">
            View verified blood requests matching your registered blood group.
        </p>

    </div>



    <?php if ($message != "") { ?>

        <div
            class="<?php
                echo $message_type === 'success'
                    ? 'success-message'
                    : 'error-message';
            ?>"
        >

            <span>
                <?php
                echo htmlspecialchars($message);
                ?>
            </span>

        </div>

    <?php } ?>



    <!-- DONOR INFORMATION BAR -->

    <div class="request-filter-bar">

        <div>

            <span class="filter-label">
                YOUR BLOOD GROUP
            </span>

            <strong>
                <?php
                echo htmlspecialchars($blood_group);
                ?>
            </strong>

        </div>


        <div>

            <span class="filter-label">
                YOUR LOCATION
            </span>

            <strong>
                <?php
                echo htmlspecialchars($donor_location);
                ?>
            </strong>

        </div>

    </div>



    <?php if (!empty($blood_group) && count($requests) > 0) { ?>


        <div class="request-list">


            <?php foreach ($requests as $request) { ?>


                <?php

                $req_id =
                    (int)$request['request_id'];


                $units_summary =
                    get_request_units_summary(
                        $conn,
                        $req_id
                    );


                $st =
                    $units_summary['status'];


                $req_units =
                    $units_summary['required'];


                $ful_units =
                    $units_summary['fulfilled'];


                $rem_units =
                    $units_summary['remaining'];


                $pct =
                    $units_summary['percent'];


                $priority =
                    strtolower(
                        $request['priority'] ?? 'normal'
                    );


                $has_active_appt =
                    in_array(
                        $req_id,
                        $donor_active_req_ids
                    );


                $is_fully_fulfilled =
                    (
                        $units_summary['remaining'] <= 0
                        ||
                        $st === 'fulfilled'
                    );

                ?>


                <div class="request-card">


                    <!-- TOP -->

                    <div class="request-card-top">


                        <div>


                            <div class="request-blood-group">

                                <?php
                                echo htmlspecialchars(
                                    $request['blood_group']
                                );
                                ?>

                            </div>


                            <span class="request-units">

                                Request #

                                <?php
                                echo $req_id;
                                ?>

                            </span>


                        </div>



                        <div
                            style="
                                display: flex;
                                gap: 8px;
                                align-items: center;
                            "
                        >


                            <span
                                class="priority-badge priority-<?php
                                    echo htmlspecialchars(
                                        $priority
                                    );
                                ?>"
                            >

                                <?php
                                echo ucfirst(
                                    htmlspecialchars(
                                        $priority
                                    )
                                );
                                ?>

                            </span>



                            <span
                                class="badge badge-<?php
                                    echo htmlspecialchars(
                                        str_replace(
                                            ' ',
                                            '_',
                                            $st
                                        )
                                    );
                                ?>"
                            >

                                <?php

                                if (
                                    $st ===
                                    'partially_fulfilled'
                                )
                                {
                                    echo "Partially Fulfilled";
                                }
                                else
                                {
                                    echo ucfirst(
                                        htmlspecialchars($st)
                                    );
                                }

                                ?>

                            </span>


                        </div>


                    </div>



                    <!-- HOSPITAL & PROGRESS -->

                    <div class="request-main">


                        <h2>

                            <?php
                            echo htmlspecialchars(
                                $request['hospital']
                            );
                            ?>

                        </h2>


                        <p class="request-location">

                            📍

                            <?php
                            echo htmlspecialchars(
                                $request['location']
                            );
                            ?>

                        </p>



                        <!-- PROGRESS BAR -->

                        <div style="margin-top: 14px;">


                            <div
                                style="
                                    display: flex;
                                    justify-content: space-between;
                                    font-size: 11px;
                                    font-weight: 600;
                                    color: #555;
                                    margin-bottom: 4px;
                                "
                            >

                                <span>
                                    Fulfillment Progress
                                </span>


                                <span>

                                    <?php
                                    echo $ful_units;
                                    ?>

                                    /

                                    <?php
                                    echo $req_units;
                                    ?>

                                    units

                                    (<?php
                                    echo $pct;
                                    ?>%)

                                </span>

                            </div>



                            <div class="progress-container">

                                <div
                                    class="progress-bar <?php

                                        if ($pct >= 100)
                                        {
                                            echo 'fulfilled';
                                        }

                                    ?>"
                                    style="
                                        width:
                                        <?php
                                        echo $pct;
                                        ?>%;
                                    "
                                ></div>

                            </div>


                        </div>



                        <!-- UNITS STAT GRID -->

                        <div class="units-stat-grid">


                            <div class="units-stat-item">

                                <span>
                                    Total Required
                                </span>

                                <strong>
                                    <?php
                                    echo $req_units;
                                    ?>
                                    units
                                </strong>

                            </div>



                            <div class="units-stat-item">

                                <span>
                                    Fulfilled
                                </span>

                                <strong>
                                    <?php
                                    echo $ful_units;
                                    ?>
                                    units
                                </strong>

                            </div>



                            <div class="units-stat-item">

                                <span>
                                    Still Needed
                                </span>

                                <strong class="highlight">
                                    <?php
                                    echo $rem_units;
                                    ?>
                                    units
                                </strong>

                            </div>


                        </div>



                        <?php if (!empty($request['reason'])) { ?>


                            <p class="request-reason">

                                <strong>
                                    Note:
                                </strong>

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $request['reason']
                                    )
                                );
                                ?>

                            </p>


                        <?php } ?>


                    </div>



                    <!-- DETAILS -->

                    <div class="request-details">


                        <div>

                            <span>
                                REQUIRED BY
                            </span>


                            <strong>

                                <?php

                                if (
                                    !empty(
                                        $request['required_date']
                                    )
                                )
                                {
                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $request['required_date']
                                        )
                                    );
                                }
                                else
                                {
                                    echo "Immediate";
                                }

                                ?>

                            </strong>

                        </div>



                        <div>

                            <span>
                                STATUS
                            </span>


                            <strong>

                                <?php

                                if (
                                    $st ===
                                    'partially_fulfilled'
                                )
                                {
                                    echo "Partially Fulfilled";
                                }
                                else
                                {
                                    echo ucfirst(
                                        htmlspecialchars($st)
                                    );
                                }

                                ?>

                            </strong>

                        </div>


                    </div>



                    <!-- ACTION BUTTON -->

                    <div class="request-action">


                        <?php if ($is_fully_fulfilled) { ?>


                            <div
                                style="
                                    padding: 10px;
                                    background: #FAF8F5;
                                    border: 1px solid #EBE7E1;
                                    border-radius: 8px;
                                    text-align: center;
                                    font-size: 12px;
                                    color: var(--muted);
                                    font-weight: 600;
                                "
                            >

                                This request has already been fulfilled.

                            </div>


                        <?php } elseif ($has_active_appt) { ?>


                            <div
                                style="
                                    padding: 10px;
                                    background: #EBF3FA;
                                    border: 1px solid #CCE0F5;
                                    border-radius: 8px;
                                    text-align: center;
                                    font-size: 12px;
                                    color: #2B6CB0;
                                    font-weight: 600;
                                "
                            >

                                ✓ You have an active appointment
                                scheduled for this request.

                            </div>


                        <?php } else { ?>


                            <form method="POST">


                                <input
                                    type="hidden"
                                    name="request_id"
                                    value="<?php
                                        echo $req_id;
                                    ?>"
                                >


                                <button
                                    type="submit"
                                    name="donate_request"
                                    class="respond-button"
                                >

                                    I can donate
                                    (<?php
                                    echo $rem_units;
                                    ?>
                                    <?php

                                    echo $rem_units == 1
                                        ? 'unit'
                                        : 'units';

                                    ?>
                                    needed)

                                </button>


                            </form>


                        <?php } ?>


                    </div>


                </div>


            <?php } ?>


        </div>


    <?php } elseif (!empty($blood_group)) { ?>


        <div class="requests-empty-card">


            <div class="empty-icon">
                ✓
            </div>


            <h2>
                No active requests
            </h2>


            <p>

                There are currently no active blood requests
                matching your blood group
                (<?php
                echo htmlspecialchars($blood_group);
                ?>).

                You will see new urgent requests here
                as soon as they are submitted.

            </p>


        </div>


    <?php } else { ?>


        <div class="requests-empty-card">


            <div class="empty-icon">
                +
            </div>


            <h2>
                Complete your donor profile
            </h2>


            <p>
                Add your blood group to see matching blood requests.
            </p>


            <a
                href="profile.php"
                class="primary-button"
                style="margin-top: 14px;"
            >
                Complete Profile →
            </a>


        </div>


    <?php } ?>


</section>


</main>
</div>

</body>
</html>