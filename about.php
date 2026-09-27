<?php
include "config/database.php";
include "includes/header.php";
?>

<!-- ABOUT HERO -->
<section class="about-hero">
    <span class="eyebrow">OUR MISSION & CLINICAL VISION</span>
    <h1>Connecting voluntary donors with urgent healthcare needs.</h1>
    <p>
        LIFELINE was created to eliminate the delays, confusion, and stress surrounding blood transfusions during emergency medical care. By uniting voluntary donors, patients, and certified hospitals on a single modern platform, we ensure that life-saving blood is available whenever and wherever it is needed.
    </p>
</section>

<!-- CORE PILLARS -->
<section class="section-container" style="padding-top: 20px;">
    <div class="section-header">
        <span class="eyebrow">OUR FOUNDATIONAL STANDARDS</span>
        <h2>Built on clinical safety and integrity.</h2>
        <p>Every feature of the LIFELINE platform is designed around medical protocol, donor wellbeing, and patient security.</p>
    </div>

    <div class="about-grid" style="padding: 0 0 60px;">
        <div class="about-card">
            <div class="about-card-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            </div>
            <h3>Donor Health & Interval Tracking</h3>
            <p>
                We enforce mandatory 90-day recovery intervals between whole blood donations. Donors cannot accept new appointments until their recovery window is complete, protecting their physiological well-being.
            </p>
        </div>

        <div class="about-card">
            <div class="about-card-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <h3>Rapid Urgency Dispatch</h3>
            <p>
                Blood requests are categorized by urgency: Normal, Urgent, and Critical. High-priority requests trigger immediate notification to all compatible donors within the hospital's radius.
            </p>
        </div>

        <div class="about-card">
            <div class="about-card-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </div>
            <h3>Privacy & Voluntary Integrity</h3>
            <p>
                Personal contact details remain private. Donors review hospital requests and voluntarily commit to appointments. LIFELINE is strictly non-commercial and adheres to national transfusion guidelines.
            </p>
        </div>
    </div>
</section>

<!-- BLOOD STORAGE & CLINICAL FACTS -->
<section class="section-container" style="background: var(--surface); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
    <div class="section-header">
        <span class="eyebrow">HEMATOLOGY FACTS</span>
        <h2>Why consistent donation matters.</h2>
        <p>Blood is a living medicine that cannot be synthesized in a laboratory. It has a finite shelf life, requiring regular replenishment.</p>
    </div>

    <div class="about-grid" style="padding: 0 0 40px;">
        <div class="about-card" style="background: var(--surface-soft);">
            <div class="about-card-icon" style="background: #FAF8F5; color: #171717;">
                <strong>42d</strong>
            </div>
            <h3>Red Blood Cells</h3>
            <p>
                Stored refrigerated at 2°C to 6°C for up to 42 days. Essential for surgeries, trauma patients, and individuals managing severe chronic anemia.
            </p>
        </div>

        <div class="about-card" style="background: var(--surface-soft);">
            <div class="about-card-icon" style="background: #FAF8F5; color: #171717;">
                <strong>5d</strong>
            </div>
            <h3>Platelets</h3>
            <p>
                Stored at room temperature (20°C–24°C) with continuous gentle agitation for only 5 days. Crucial for cancer patients undergoing chemotherapy.
            </p>
        </div>

        <div class="about-card" style="background: var(--surface-soft);">
            <div class="about-card-icon" style="background: #FAF8F5; color: #171717;">
                <strong>1yr</strong>
            </div>
            <h3>Plasma</h3>
            <p>
                Frozen at -18°C or colder for up to 1 year. Vital for treating burn trauma victims, severe shock, bleeding disorders, and liver conditions.
            </p>
        </div>
    </div>
</section>

<!-- FAQ SECTION -->
<section class="section-container">
    <div class="section-header">
        <span class="eyebrow">COMMON QUESTIONS</span>
        <h2>Frequently Asked Questions</h2>
        <p>Everything you need to know about donating or requesting blood through LIFELINE.</p>
    </div>

    <div style="max-width: 860px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;">
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-card); padding: 28px 32px;">
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700; margin-bottom: 10px;">
                Who is eligible to donate blood?
            </h3>
            <p style="font-size: 14px; color: var(--text-secondary); line-height: 1.6;">
                Most individuals aged 18 to 65, weighing at least 50 kg (110 lbs), with acceptable hemoglobin levels and blood pressure, are eligible to donate. Before every session, the medical team conducts a brief physical check to ensure your safety.
            </p>
        </div>

        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-card); padding: 28px 32px;">
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700; margin-bottom: 10px;">
                How often can I donate through LIFELINE?
            </h3>
            <p style="font-size: 14px; color: var(--text-secondary); line-height: 1.6;">
                Whole blood donors are advised to wait at least 90 days between donations. LIFELINE automatically updates your last donation date and reminds you when you are safely eligible to donate again.
            </p>
        </div>

        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-card); padding: 28px 32px;">
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700; margin-bottom: 10px;">
                How does a recipient post a request?
            </h3>
            <p style="font-size: 14px; color: var(--text-secondary); line-height: 1.6;">
                Recipients or authorized family members register a Recipient account and complete a simple form with the patient’s required blood type, number of units, hospital location, and urgency level. The request is immediately broadcasted to all compatible donors.
            </p>
        </div>

        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-card); padding: 28px 32px;">
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700; margin-bottom: 10px;">
                Is LIFELINE completely free to use?
            </h3>
            <p style="font-size: 14px; color: var(--text-secondary); line-height: 1.6;">
                Yes. LIFELINE is a non-commercial, voluntary platform created to facilitate blood donation logistics for communities, clinics, and hospitals without any transaction fees.
            </p>
        </div>
    </div>
</section>

<!-- FINAL BANNER -->
<section class="cta-banner">
    <h2>Ready to make an immediate impact?</h2>
    <p>Join thousands of voluntary donors who stand ready to answer emergency calls in your city.</p>
    <div class="cta-actions">
        <a href="/register.php?role=donor" class="primary-button" style="background: #FFFFFF; color: #171717 !important;">
            Register as a Donor
        </a>
        <a href="/register.php?role=recipient" class="secondary-button btn-cta-secondary">
            Request Blood Support
        </a>
    </div>
</section>

<?php
include "includes/footer.php";
?>
