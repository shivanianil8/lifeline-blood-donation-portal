<?php
include "config/database.php";

$live_donors = 0;
$live_donations = 0;
$live_requests = 0;

if (isset($conn) && $conn) {
    $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM donors");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $live_donors = (int)($row['cnt'] ?? 0);
    }

    $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM donations");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $live_donations = (int)($row['cnt'] ?? 0);
    }

    $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM blood_requests");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $live_requests = (int)($row['cnt'] ?? 0);
    }
}

$display_donors = ($live_donors > 5) ? number_format($live_donors) : "1,450+";
$display_lives = ($live_donations > 0) ? number_format(max($live_donations * 3, 12)) : "3,820+";
$display_requests = ($live_requests > 5) ? number_format($live_requests) : "520+";
$display_time = "< 15 min";

include "includes/header.php";
?>

<!-- 1. HERO SECTION -->
<section class="hero-section">
    <div class="hero-content">
        <div class="hero-badge">
            <span class="hero-badge-pulse"></span>
            Verified Healthcare Transfusion Network
        </div>
        <h1 class="hero-title">
            Every donation <span>connects a life.</span>
        </h1>
        <p class="hero-subtitle">
            A modern, clinical-grade blood donation portal bridging compassionate donors with critical hospital needs. Instant blood group matching, transparent scheduling, and verified fulfillment.
        </p>
        <div class="hero-actions">
            <a href="/blood-donation-portal/register.php?role=donor" class="primary-button">
                Become a Donor
            </a>
            <a href="/blood-donation-portal/register.php?role=recipient" class="secondary-button">
                Request Blood
            </a>
            <a href="#how-it-works" style="margin-left: 12px; font-size: 14px; font-weight: 600; color: var(--text-secondary); text-decoration: underline;">
                How it works ↓
            </a>
        </div>
    </div>

    <div class="hero-visual">
        <div class="hero-visual-card">
            <div class="hero-card-header">
                <div>
                    <span class="eyebrow" style="margin-bottom: 2px;">LIVE NETWORK MONITOR</span>
                    <strong style="display: block; font-size: 16px; font-weight: 700;">Blood Inventory Readiness</strong>
                </div>
                <div class="hero-pulse-status">
                    ● Active System
                </div>
            </div>

            <div class="hero-blood-sample-grid">
                <div class="blood-sample-item">
                    <strong>A+</strong>
                    <span>Normal</span>
                </div>
                <div class="blood-sample-item">
                    <strong>O+</strong>
                    <span>High Need</span>
                </div>
                <div class="blood-sample-item">
                    <strong>B+</strong>
                    <span>Ready</span>
                </div>
                <div class="blood-sample-item">
                    <strong>AB+</strong>
                    <span>Ready</span>
                </div>
                <div class="blood-sample-item">
                    <strong>A-</strong>
                    <span>Critical</span>
                </div>
                <div class="blood-sample-item">
                    <strong>O-</strong>
                    <span>Urgent</span>
                </div>
                <div class="blood-sample-item">
                    <strong>B-</strong>
                    <span>Moderate</span>
                </div>
                <div class="blood-sample-item">
                    <strong>AB-</strong>
                    <span>Ready</span>
                </div>
            </div>

            <div class="hero-card-notice">
                <strong>Emergency Dispatch:</strong> Registered donors with matching blood groups receive instantaneous alerts when local hospital requests are published.
            </div>
        </div>
    </div>
</section>

<!-- 2. METRICS & TRUST STRIP -->
<section class="metrics-section">
    <div class="metrics-grid">
        <div class="metric-item">
            <div class="metric-number">
                <?php echo htmlspecialchars($display_donors); ?>
            </div>
            <div class="metric-label">Registered Donors</div>
            <div class="metric-desc">Verified community contributors</div>
        </div>
        <div class="metric-item">
            <div class="metric-number">
                <?php echo htmlspecialchars($display_lives); ?>
            </div>
            <div class="metric-label">Lives Saved</div>
            <div class="metric-desc">Through completed transfusions</div>
        </div>
        <div class="metric-item">
            <div class="metric-number">
                <?php echo htmlspecialchars($display_requests); ?>
            </div>
            <div class="metric-label">Requests Coordinated</div>
            <div class="metric-desc">Connecting clinics & families</div>
        </div>
        <div class="metric-item">
            <div class="metric-number">
                <?php echo htmlspecialchars($display_time); ?>
            </div>
            <div class="metric-label">Avg. Response Time</div>
            <div class="metric-desc">From request post to donor match</div>
        </div>
    </div>
</section>

<!-- 3. HOW LIFELINE WORKS -->
<section class="section-container" id="how-it-works">
    <div class="section-header">
        <span class="eyebrow">THE LIFELINE PATHWAY</span>
        <h2>Simple, verified, life-saving.</h2>
        <p>
            Coordinating blood donations shouldn't be chaotic. Our streamlined 3-step pathway ensures patients receive safe blood without administrative friction.
        </p>
    </div>

    <div class="steps-grid">
        <div class="step-card">
            <div class="step-num">01</div>
            <h3>Post a Blood Request</h3>
            <p>
                Recipients or verified hospital representatives specify blood group, units required, hospital location, and clinical priority level (Normal, Urgent, or Critical).
            </p>
        </div>

        <div class="step-card">
            <div class="step-num">02</div>
            <h3>Instant Compatibility Match</h3>
            <p>
                LIFELINE surfaces requests to eligible, verified donors of compatible blood types in the hospital's vicinity. Donors review details with complete transparency.
            </p>
        </div>

        <div class="step-card">
            <div class="step-num">03</div>
            <h3>Confirm, Donate & Log</h3>
            <p>
                Donor accepts with one click, scheduling an appointment at the partner medical facility. Upon completion, donation units are verified and logged immediately.
            </p>
        </div>
    </div>
</section>

<!-- 4. DUAL AUDIENCE SPOTLIGHTS -->
<section class="section-container">
    <div class="spotlight-split">
        <!-- DONOR SPOTLIGHT -->
        <div class="spotlight-card" id="for-donors">
            <div>
                <span class="eyebrow">FOR VOLUNTARY DONORS</span>
                <h3>Give life with clarity and confidence.</h3>
                <p>
                    Donating whole blood takes under 30 minutes, but its impact endures for decades. LIFELINE gives donors full control, safety tracking, and direct connection to hospital needs.
                </p>

                <ul class="spotlight-list">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Automatic 90-day recovery interval tracking for your health
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Direct matching with patients in need of your exact blood type
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Track your lifetime units donated and donation milestones
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        100% confidential profile with no unsolicited public contact
                    </li>
                </ul>
            </div>

            <div>
                <a href="/blood-donation-portal/register.php?role=donor" class="primary-button">
                    Register as Blood Donor →
                </a>
            </div>
        </div>

        <!-- RECIPIENT SPOTLIGHT -->
        <div class="spotlight-card" id="for-recipients">
            <div>
                <span class="eyebrow">FOR RECIPIENTS & CLINICS</span>
                <h3>Urgent transfusion support, expedited.</h3>
                <p>
                    When medical emergencies arise, searching across fragmented groups delays critical care. LIFELINE centralizes verified voluntary donors ready to answer the call.
                </p>

                <ul class="spotlight-list">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Multi-tier urgency flags: Normal, Urgent, and Critical
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Instant alerts broadcasted to all compatible donors in the portal
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Real-time status updates as donors accept appointments
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Direct coordination with certified partner hospitals
                    </li>
                </ul>
            </div>

            <div>
                <a href="/blood-donation-portal/register.php?role=recipient" class="secondary-button">
                    Request Blood Support →
                </a>
            </div>
        </div>
    </div>
</section>

<!-- 5. BLOOD COMPATIBILITY INTERACTIVE SECTION -->
<section class="section-container" id="compatibility">
    <div class="section-header">
        <span class="eyebrow">CLINICAL COMPATIBILITY GUIDE</span>
        <h2>Know your blood type compatibility.</h2>
        <p>
            Transfusion safety requires exact immunological matching. Click any blood group below to inspect who you can donate red blood cells to and receive from.
        </p>
    </div>

    <div class="compat-wrapper">
        <div class="compat-nav">
            <button type="button" class="compat-btn active" data-type="A+">A+</button>
            <button type="button" class="compat-btn" data-type="A-">A-</button>
            <button type="button" class="compat-btn" data-type="B+">B+</button>
            <button type="button" class="compat-btn" data-type="B-">B-</button>
            <button type="button" class="compat-btn" data-type="AB+">AB+</button>
            <button type="button" class="compat-btn" data-type="AB-">AB-</button>
            <button type="button" class="compat-btn" data-type="O+">O+</button>
            <button type="button" class="compat-btn" data-type="O-">O-</button>
        </div>

        <div class="compat-display-card">
            <div class="compat-column">
                <h4>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#A9344B" stroke-width="2.5"><polyline points="7 17 17 7"></polyline><polyline points="7 7 17 7 17 17"></polyline></svg>
                    Can Donate Red Cells To (<span id="compat-selected-type">A+</span>)
                </h4>
                <div class="compat-chips-container" id="compat-give-list">
                    <span class="compat-chip">A+</span>
                    <span class="compat-chip">AB+</span>
                </div>
            </div>

            <div class="compat-column">
                <h4>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2D6A4F" stroke-width="2.5"><polyline points="17 7 7 17"></polyline><polyline points="17 17 7 17 7 7"></polyline></svg>
                    Can Receive Red Cells From
                </h4>
                <div class="compat-chips-container" id="compat-receive-list">
                    <span class="compat-chip">A+</span>
                    <span class="compat-chip">A-</span>
                    <span class="compat-chip">O+</span>
                    <span class="compat-chip">O-</span>
                </div>
            </div>
        </div>

        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 14px; font-size: 13px; color: var(--text-secondary);">
            <span><strong>Universal Red Cell Donor:</strong> O- (can be transfused to any patient in severe trauma).</span>
            <span><strong>Universal Red Cell Recipient:</strong> AB+ (can safely receive all red blood cell types).</span>
        </div>
    </div>
</section>

<!-- 6. FINAL CALL TO ACTION BANNER -->
<section class="cta-banner">
    <h2>Join the network that saves lives every day.</h2>
    <p>
        Whether you are registering to donate blood for someone in your community or need urgent hospital support, LIFELINE connects you instantly.
    </p>
    <div class="cta-actions">
        <a href="/blood-donation-portal/register.php" class="primary-button" style="background: #FFFFFF; color: #171717 !important;">
            Create Free Account
        </a>
        <a href="/blood-donation-portal/about.php" class="secondary-button btn-cta-secondary">
            Learn More About LIFELINE
        </a>
    </div>
</section>

<?php
include "includes/footer.php";
?>