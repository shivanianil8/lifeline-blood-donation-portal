<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'donor')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";


/* =========================
   GET DONOR ID
========================= */

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT donor_id
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
}
else
{
    $donor_id = (int)$donor['donor_id'];
}


/* =========================
   GET DONATION HISTORY
========================= */

$donations = [];
$total_units = 0;
$last_donation = null;

if ($donor_id > 0)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            donation_id,
            appointment_id,
            blood_group,
            units,
            donation_date,
            hospital
         FROM donations
         WHERE donor_id = ?
         ORDER BY donation_date DESC"
    );

    if ($stmt)
    {
        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $donor_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result))
        {
            $donations[] = $row;
            $total_units += (int)$row['units'];
        }

        mysqli_stmt_close($stmt);
    }

    if (!empty($donations))
    {
        $last_donation = $donations[0]['donation_date'];
    }
}

include "donor-layout.php";

?>


        <section class="content">


            <!-- =========================
                 PAGE HEADING
            ========================= -->

            <div class="page-heading">

                <p class="eyebrow">
                    DONATION HISTORY
                </p>

                <h1>
                    Your donation history
                </h1>

                <p class="subtitle">
                    Keep track of the blood donations you have completed.
                </p>

            </div>


            <!-- SUMMARY STATS -->
            <div class="dashboard-stats" style="margin-bottom: 28px;">

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">TOTAL DONATIONS</span>
                        <span class="stat-icon">↗</span>
                    </div>
                    <div class="stat-value">
                        <?php echo count($donations); ?>
                    </div>
                    <p class="stat-description">
                        Completed donation sessions
                    </p>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">UNITS DONATED</span>
                        <span class="stat-icon">+</span>
                    </div>
                    <div class="stat-value">
                        <?php echo $total_units; ?>
                    </div>
                    <p class="stat-description">
                        Total blood units contributed
                    </p>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">LAST DONATION</span>
                        <span class="stat-icon">↻</span>
                    </div>
                    <div class="stat-value stat-text">
                        <?php
                        if ($last_donation)
                        {
                            echo date("d M Y", strtotime($last_donation));
                        }
                        else
                        {
                            echo "None yet";
                        }
                        ?>
                    </div>
                    <p class="stat-description">
                        Most recent donation date
                    </p>
                </div>

            </div>


            <!-- =========================
                 DONATIONS
            ========================= -->

            <?php if (count($donations) > 0) { ?>


                <div class="request-list">


                    <?php foreach ($donations as $donation) { ?>


                        <div class="request-card">


                            <!-- TOP -->

                            <div class="request-card-top">


                                <div>

                                    <div class="request-blood-group">

                                        <?php

                                        echo htmlspecialchars(
                                            $donation['blood_group']
                                        );

                                        ?>

                                    </div>


                                    <span class="request-units">

                                        Donation #

                                        <?php

                                        echo htmlspecialchars(
                                            $donation['donation_id']
                                        );

                                        ?>

                                    </span>

                                </div>


                                <span class="badge badge-completed">
                                    Completed
                                </span>


                            </div>


                            <!-- HOSPITAL -->

                            <div class="request-main">

                                <h2>

                                    <?php

                                    echo htmlspecialchars(
                                        $donation['hospital']
                                    );

                                    ?>

                                </h2>

                            </div>


                            <!-- DETAILS -->

                            <div class="request-details">


                                <div>

                                    <span>
                                        DATE
                                    </span>

                                    <strong>

                                        <?php

                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $donation['donation_date']
                                            )
                                        );

                                        ?>

                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        UNITS
                                    </span>

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $donation['units']
                                        );

                                        ?>

                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        APPOINTMENT
                                    </span>

                                    <strong>

                                        #

                                        <?php

                                        echo htmlspecialchars(
                                            $donation['appointment_id']
                                        );

                                        ?>

                                    </strong>

                                </div>


                            </div>


                        </div>


                    <?php } ?>


                </div>


            <?php } else { ?>


                <!-- =========================
                     EMPTY STATE
                ========================= -->

                <div class="requests-empty-card">


                    <div class="empty-icon">
                        🩸
                    </div>

                    <h2>
                        No donations yet
                    </h2>

                    <p>
                        Your completed blood donations will appear here after appointment completion.
                    </p>

                    <a
                        href="requests.php"
                        class="primary-button"
                        style="margin-top: 14px;"
                    >
                        Browse Blood Requests →
                    </a>


                </div>


            <?php } ?>


        </section>


    </main>


</div>


</body>

</html>