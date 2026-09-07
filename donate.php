<?php
// Load configuration
require_once 'config.php';
require_once 'db.php';

$page_title = 'Donate';
$page_description = 'Support the ministry of NUPS-G TTU. Your generosity fuels worship, outreach and community service.';

$message = '';
$error = '';
$celebrate = false;

// Paystack is live only when a secret key has been configured in .env.
$paystack_enabled = PAYSTACK_SECRET_KEY !== '';

// --- Paystack return: verify the just-completed payment --------------------
$callback_ref = trim($_GET['reference'] ?? '');
if ($callback_ref !== '' && $paystack_enabled) {
    $verify = paystack_verify($callback_ref);
    if ($verify['ok']) {
        $paid = $verify['status'] === 'success';
        try {
            $stmt = $conn->prepare(
                "UPDATE donations SET payment_status = ? WHERE payment_reference = ?"
            );
            $stmt->execute([$paid ? 'success' : 'failed', $callback_ref]);
        } catch (Exception $e) {
            // Donation row will stay 'pending'; still tell the donor the outcome.
        }
        if ($paid) {
            $message = 'Hallelujah! Your donation was received and confirmed. God bless you abundantly!';
            $celebrate = true;
        } else {
            $error = 'Your payment was not completed, so no money was taken. Please try again. Your gift matters.';
        }
    } else {
        $error = 'We could not confirm your payment: ' . htmlspecialchars($verify['error']);
    }
}

// --- Donation form submitted ------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $note = sanitize($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($phone)) {
        $error = 'Please fill in your name, email and phone number.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid amount.';
    } else {
        $reference = '';
        try {
            $reference = 'NUPSG-' . strtoupper(bin2hex(random_bytes(8)));
            $stmt = $conn->prepare(
                "INSERT INTO donations (donor_name, donor_email, donor_phone, amount, message, payment_reference, payment_status)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([$name, $email, $phone, number_format($amount, 2, '.', ''), $note, $reference]);
        } catch (Exception $e) {
            $error = 'We could not save your donation right now. Please try again later.';
        }

        if ($error === '') {
            if (!$paystack_enabled) {
                // No Paystack key yet - keep the manual "giving intent" flow.
                $message = 'Thank you, ' . htmlspecialchars(explode(' ', $name)[0]) . '! Your giving intent has been received. Our team will reach out to confirm your gift.';
            } else {
                $started = paystack_initialize($amount, $email, $reference, [
                    'custom_fields' => [
                        ['display_name' => 'Donor Name', 'variable_name' => 'donor_name', 'value' => $name],
                        ['display_name' => 'Phone', 'variable_name' => 'donor_phone', 'value' => $phone],
                        ['display_name' => 'Message', 'variable_name' => 'message', 'value' => $note !== '' ? $note : 'Gift from donate page'],
                    ],
                    'cancel_action' => SITE_URL . '/donate.php',
                ]);
                if ($started['ok']) {
                    header('Location: ' . $started['authorization_url']);
                    exit;
                }
                // Payment could not be created - mark the row failed so admin knows.
                $error = 'We could not start the payment: ' . htmlspecialchars($started['error']);
                try {
                    $stmt = $conn->prepare("UPDATE donations SET payment_status = 'failed' WHERE payment_reference = ?");
                    $stmt->execute([$reference]);
                } catch (Exception $e) {}
            }
        }
    }
}

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-heart"></i> Giving</span>
            <h1>Support Our Mission</h1>
            <div class="divider"></div>
            <p>Your generosity powers worship, outreach and community transformation</p>
        </div>
    </section>

    <style>
        .donate-grid { display: grid; grid-template-columns: 1fr 1.1fr; gap: clamp(1.6rem, 4vw, 3rem); align-items: start; }
        .method { display: flex; gap: 1.1rem; padding: 1.3rem 1.4rem; align-items: flex-start; transition: transform .25s var(--ease), box-shadow .25s var(--ease); }
        .method:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
        .method .icon { width: 52px; height: 52px; flex: none; border-radius: 14px; display: grid; place-items: center; background: var(--sky); color: var(--primary); font-size: 1.3rem; }
        .method h3 { font-family: var(--font-body); font-size: 1.05rem; font-weight: 800; margin-bottom: .3rem; }
        .method p { color: var(--muted); font-size: .93rem; }
        .method code { background: var(--gold-soft); color: var(--gold-deep); border: 1px dashed var(--gold); border-radius: 8px; padding: .15rem .55rem; font-weight: 700; }
        /* Mobile Money brand tiles */
        .momo-card { padding: 1.5rem 1.6rem; }
        .momo-card h3 { font-family: var(--font-body); font-weight: 800; font-size: 1.12rem; color: var(--ink); margin-bottom: .25rem; }
        .momo-sub { color: var(--muted); font-size: .93rem; margin-bottom: 1.2rem; }
        .momo-logos { display: flex; flex-wrap: wrap; align-items: stretch; gap: .7rem; }
        .momo-logo { display: inline-flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px; min-width: 104px; height: 56px; padding: .4rem .95rem; border-radius: 12px; box-shadow: var(--shadow-xs); line-height: 1.05; transition: transform .2s var(--ease), box-shadow .2s var(--ease); }
        .momo-logo:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .momo-mtn { background: #ffcc08; color: #151515; }
        .momo-mtn .l-top { font-size: .58rem; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; }
        .momo-mtn .l-main { font-family: var(--font-display); font-weight: 800; font-size: 1.3rem; letter-spacing: -0.02em; }
        .momo-telecel { background: #e32526; color: #fff; }
        .momo-telecel .l-main { font-weight: 700; font-size: 1.05rem; letter-spacing: -0.02em; }
        .momo-telecel .l-top { font-size: .58rem; font-weight: 800; letter-spacing: .3em; margin-top: 1px; }
        .momo-at { background: #101014; color: #fff; }
        .momo-at .l-main { display: flex; align-items: center; font-weight: 900; font-size: 1.15rem; letter-spacing: .02em; }
        .momo-at .l-main b:first-child { color: #ff4136; }
        .momo-at .l-main b:last-child { color: #3d9bff; margin-left: .04em; }
        .momo-at .l-top { font-size: .56rem; font-weight: 700; letter-spacing: .26em; text-transform: uppercase; color: #9aa2b5; }
        .momo-number { margin-top: 1.15rem; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-weight: 700; color: var(--ink); font-size: .95rem; border-top: 1px dashed var(--line); padding-top: 1rem; }
        .momo-number button { display: inline-flex; align-items: center; gap: .45rem; background: var(--sky); color: var(--primary); border-radius: 9px; padding: .35rem .8rem; font-weight: 800; font-size: .95rem; border: 0; transition: background .2s, color .2s; }
        .momo-number button:hover { background: var(--primary); color: #fff; }
        .momo-number button i { font-size: .75rem; }
        .impact-strip { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 2.4rem; }
        .impact { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.14); border-radius: var(--r); padding: 1.2rem 1rem; text-align: center; }
        .impact i { color: var(--gold); font-size: 1.3rem; margin-bottom: .5rem; display: block; }
        .impact b { display: block; color: #fff; font-size: 1.05rem; }
        .impact small { color: rgba(255,255,255,0.75); }
        @media (max-width: 900px) { .donate-grid { grid-template-columns: 1fr; } .impact-strip { grid-template-columns: 1fr; } }
    </style>

    <section class="section">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success reveal in"><i class="fas fa-check-circle"></i><div><?php echo $message; ?></div></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error reveal in"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
            <?php endif; ?>

            <div class="donate-grid">
                <div>
                    <span class="eyebrow"><i class="fas fa-praying-hands"></i> Ways to Give</span>
                    <h2 style="font-size: var(--fs-xl); margin: .8rem 0 1.4rem;">Every gift advances the Gospel on campus</h2>
                    <p style="color: var(--muted); line-height: 1.9; margin-bottom: 1.8rem;">Your donations support weekly services, leadership training, outreach programmes like P-SICE, and the day-to-day ministry of the chapter.</p>

                    <article class="card card--hover momo-card">
                        <h3>Send via Mobile Money</h3>
                        <p class="momo-sub">We accept all three networks in Ghana. Send your gift and confirm it with the form.</p>
                        <div class="momo-logos">
                            <span class="momo-logo momo-mtn" title="MTN MoMo">
                                <span class="l-top">MTN</span>
                                <span class="l-main">MoMo</span>
                            </span>
                            <span class="momo-logo momo-telecel" title="Telecel Cash">
                                <span class="l-main">telecel</span>
                                <span class="l-top">CASH</span>
                            </span>
                            <span class="momo-logo momo-at" title="AT Money (AirtelTigo)">
                                <span class="l-main"><b>A</b><b>T</b></span>
                                <span class="l-top">Money</span>
                            </span>
                        </div>
                        <p class="momo-number">
                            <span>Pay to:</span>
                            <button type="button" class="js-copy-num" data-copy="+233 256 4843 60">+233 256 4843 60 <i class="fas fa-copy"></i></button>
                            <span style="font-weight:600; color:var(--muted); font-size:.85rem;">tap to copy</span>
                        </p>
                    </article>
                    <div class="method card card--hover" style="margin-top:1rem;">
                        <div class="icon"><i class="fas fa-university"></i></div>
                        <div>
                            <h3>Bank Transfer</h3>
                            <p>Account details are shared on request. Email <a href="mailto:sethsefa18@gmail.com" style="color: var(--primary); font-weight:700;">sethsefa18@gmail.com</a> and we'll respond promptly.</p>
                        </div>
                    </div>

                    <div class="mission-band" style="border-radius: var(--r-lg); padding-block: 2rem; margin-top: 2rem;">
                        <div class="container">
                            <div class="impact-strip">
                                <div class="impact"><i class="fas fa-bible"></i><b>Worship &amp; Teaching</b><small>services, crusades, retreats</small></div>
                                <div class="impact"><i class="fas fa-hands-helping"></i><b>Outreach</b><small>P-SICE &amp; community service</small></div>
                                <div class="impact"><i class="fas fa-graduation-cap"></i><b>Leadership</b><small>training &amp; development</small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2 style="font-size: 1.6rem; margin-bottom: .3rem;">Give online</h2>
                    <p style="color: var(--muted); margin-bottom: 1.8rem;"><?php echo $paystack_enabled ? 'Pay securely by card or any Mobile Money network: MTN MoMo, Telecel Cash or AT Money.' : 'Fill this in and we\'ll confirm your donation personally.'; ?></p>
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="field">
                                <label for="name">Full Name <span class="req">*</span></label>
                                <input type="text" id="name" name="name" required placeholder="Your name">
                            </div>
                            <div class="field">
                                <label for="phone">Phone <span class="req">*</span></label>
                                <input type="tel" id="phone" name="phone" required placeholder="+233 ...">
                            </div>
                        </div>
                        <div class="field">
                            <label for="email">Email <span class="req">*</span></label>
                            <input type="email" id="email" name="email" required placeholder="you@example.com">
                        </div>
                        <div class="field">
                            <label for="amount">Amount (GHS) <span class="req">*</span></label>
                            <input type="number" id="amount" name="amount" min="1" step="0.01" required placeholder="e.g. 50">
                        </div>
                        <div class="field">
                            <label for="message">Message <span style="font-weight:400; color:var(--muted);">(optional)</span></label>
                            <textarea id="message" name="message" rows="4" placeholder="A note, prayer request or dedication..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg btn-block"><i class="fas fa-hand-holding-heart"></i> <?php echo $paystack_enabled ? 'Donate Now - Card or Mobile Money' : 'Submit Giving Intent'; ?></button>
                        <p class="form-hint text-center"><?php echo $paystack_enabled ? 'You\'ll be taken to a secure Paystack checkout to complete your gift instantly.' : 'We\'ll never share your details. Giving intents are confirmed by a team member before anything is processed.'; ?></p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php if ($celebrate): ?>
<!-- Thank-you celebration popup -->
<style>
    .thank-overlay {
        position: fixed; inset: 0; z-index: 9999;
        display: grid; place-items: center; padding: 1.2rem;
        background: rgba(8, 15, 40, 0.62);
        backdrop-filter: blur(16px) saturate(1.2);
        -webkit-backdrop-filter: blur(16px) saturate(1.2);
        animation: thankFade .35s ease both;
    }
    .thank-card {
        position: relative;
        max-width: 560px; width: 100%;
        text-align: center; padding: clamp(2.2rem, 6vw, 3.2rem) clamp(1.4rem, 5vw, 2.8rem);
        border-radius: 26px;
        background: linear-gradient(150deg, rgba(255,255,255,0.92), rgba(255,255,255,0.72));
        border: 1px solid rgba(255,255,255,0.75);
        box-shadow: 0 30px 80px rgba(6, 12, 35, 0.55), inset 0 1px 0 rgba(255,255,255,0.9);
        backdrop-filter: blur(22px) saturate(1.4);
        -webkit-backdrop-filter: blur(22px) saturate(1.4);
        animation: thankPop .5s cubic-bezier(.2,1.4,.4,1) .08s both;
        overflow: hidden;
    }
    .thank-card::before {
        content: ''; position: absolute; top: -55%; left: -25%; width: 150%; height: 110%;
        background: linear-gradient(115deg, transparent 42%, rgba(255,255,255,0.55) 50%, transparent 58%);
        transform: rotate(6deg);
        animation: thankShine 2.6s ease 1s 2 both;
        pointer-events: none;
    }
    .thank-halo {
        width: 96px; height: 96px; margin: 0 auto 1.2rem;
        border-radius: 50%; display: grid; place-items: center;
        background: radial-gradient(circle at 30% 25%, #ffd75e, #f2a900 70%);
        box-shadow: 0 14px 34px rgba(242, 169, 0, 0.45), 0 0 0 10px rgba(242, 169, 0, 0.14);
        animation: thankFloat 3.2s ease-in-out infinite;
    }
    .thank-halo i { font-size: 2.5rem; color: #fff; filter: drop-shadow(0 2px 6px rgba(0,0,0,.25)); }
    .thank-card h2 {
        font-family: var(--font-display);
        font-size: clamp(2.1rem, 6vw, 2.9rem);
        color: var(--primary);
        margin: 0 0 .5rem;
        letter-spacing: .01em;
    }
    .thank-card .thank-line {
        color: var(--ink); font-size: clamp(1.02rem, 2.6vw, 1.18rem);
        line-height: 1.65; margin: 0 0 .35rem;
    }
    .thank-card .thank-bless {
        font-style: italic; color: var(--gold-dark); font-weight: 700;
        margin: 0 0 1.6rem;
    }
    .thank-amen {
        display: inline-flex; align-items: center; gap: .7rem;
        border: 0; cursor: pointer;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff; font-family: var(--font-body);
        font-size: 1.12rem; font-weight: 800; letter-spacing: .04em;
        padding: .95rem 2.9rem; border-radius: var(--r-pill);
        box-shadow: 0 12px 26px rgba(16, 27, 61, 0.35);
        transition: transform .2s var(--ease), box-shadow .2s var(--ease);
    }
    .thank-amen:hover { transform: translateY(-3px) scale(1.03); box-shadow: 0 18px 34px rgba(16, 27, 61, 0.42); }
    .thank-amen:active { transform: scale(.96); }
    .thank-hint { margin-top: .9rem; font-size: .8rem; color: var(--muted); }
    @keyframes thankFade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes thankPop { from { opacity: 0; transform: scale(.72) translateY(24px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    @keyframes thankFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-9px); } }
    @keyframes thankShine { 0% { left: -60%; } 100% { left: 130%; } }
    @media (prefers-reduced-motion: reduce) {
        .thank-card::before, .thank-halo { animation: none; }
    }
</style>
<div class="thank-overlay" id="thankOverlay" role="dialog" aria-modal="true" aria-labelledby="thankTitle">
    <div class="thank-card">
        <div class="thank-halo"><i class="fas fa-hand-holding-heart"></i></div>
        <h2 id="thankTitle">Hallelujah!</h2>
        <p class="thank-line">Your donation was received and confirmed.</p>
        <p class="thank-bless">God bless you abundantly! 🙏</p>
        <button type="button" class="thank-amen" id="thankAmen"><i class="fas fa-praying-hands"></i> Amen</button>
        <p class="thank-hint">Tap Amen, or click anywhere outside to close</p>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('thankOverlay');
    var amen = document.getElementById('thankAmen');
    function closeThank() {
        if (!overlay) return;
        overlay.style.transition = 'opacity .28s ease';
        overlay.style.opacity = '0';
        setTimeout(function () { overlay.remove(); }, 280);
        document.removeEventListener('keydown', onKey);
    }
    function onKey(e) { if (e.key === 'Escape') closeThank(); }
    if (amen) { amen.addEventListener('click', closeThank); }
    if (overlay) {
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeThank(); });
        document.addEventListener('keydown', onKey);
    }
})();
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
