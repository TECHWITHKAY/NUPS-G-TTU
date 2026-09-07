<?php
require_once 'config.php';
require_once 'db.php';

$page_title = 'Bible Quiz';
$page_description = 'Test your biblical knowledge with the NUPS-G daily quiz and climb the leaderboard.';
$is_logged_in = isset($_SESSION['user_id']);
$is_admin_user = $is_logged_in && ($_SESSION['role'] ?? '') === 'admin';
$is_player = $is_logged_in && !$is_admin_user; // admins set questions - they don't play

/* ---------- Quiz schedule window ---------- */
$quiz_available = true;
$next_quiz_time = null;
$quiz_settings = null;
try {
    $stmt = $conn->query("SELECT * FROM quiz_settings ORDER BY id DESC LIMIT 1");
    $quiz_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($quiz_settings) {
        $quiz_start = new DateTime($quiz_settings['quiz_start']);
        $quiz_end = clone $quiz_start;
        $quiz_end->modify('+' . (int)$quiz_settings['quiz_duration_hours'] . ' hours');
        $now = new DateTime();
        $quiz_available = (bool)$quiz_settings['is_active'] && $now >= $quiz_start && $now <= $quiz_end;
        if (!$quiz_available) {
            $next_quiz_time = $now < $quiz_start ? $quiz_start : $quiz_end;
        }
    }
} catch (Exception $e) {
    $quiz_available = true;
}

/* ---------- Quiz identity: one dated quiz per day ---------- */
$today_key = date('Y-m-d'); // the "quiz sent" date (site timezone)

/** The day's quiz: the SAME dated questions for every member, in a fixed order. */
function getQuestionPool($conn, $today_key) {
    try {
        $stmt = $conn->prepare(
            "SELECT id, question, option_a, option_b, option_c, option_d, points
             FROM quiz_questions
             WHERE quiz_date = ?
             ORDER BY id"
        );
        $stmt->execute([$today_key]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Today's results, ranked by marks (score DESC) then by speed (time ASC).
 * Faster time breaks ties between equal marks.
 */
function getDailyResults($conn, $today_key) {
    try {
        $stmt = $conn->prepare(
            "SELECT r.user_id, r.score, r.total_questions, r.time_taken,
                    u.first_name, u.last_name
             FROM quiz_results r
             JOIN users u ON u.id = r.user_id
             WHERE r.quiz_date = ?
             ORDER BY r.score DESC, r.time_taken ASC, r.created_at ASC"
        );
        $stmt->execute([$today_key]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/* ---------- The once-per-day gate: has this member already played today? ---------- */
$already_played = null;
if ($is_player) {
    try {
        $stmt = $conn->prepare("SELECT * FROM quiz_results WHERE user_id = ? AND quiz_date = ?");
        $stmt->execute([$_SESSION['user_id'], $today_key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $already_played = $row ? $row : null;
    } catch (Exception $e) {
        $already_played = null;
    }
}

// Auto-publish today's quiz from the bank when nobody has for this day yet.
// Does nothing when a member already played (their bank-chosen paper is already
// locked in) or when questions already exist for this day.
if ($is_player && !$already_played) {
    publish_today_questions($conn, $today_key);
}

// Members who already played today get their saved result - no questions, no restart.
$pool = (!$already_played && $is_player) ? getQuestionPool($conn, $today_key) : [];
$daily_results = $is_logged_in ? getDailyResults($conn, $today_key) : [];

/* ---------- Submit handler ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_quiz') {
    header('Content-Type: application/json');

    if (!$is_logged_in) {
        echo json_encode(['ok' => false, 'error' => 'You must be logged in to submit the quiz.']);
        exit;
    }
    if ($is_admin_user) {
        echo json_encode(['ok' => false, 'error' => 'Admins set the questions. Only members can take the quiz.']);
        exit;
    }

    $today_key = date('Y-m-d');

    // Once-per-day: a second attempt (even after refresh) is rejected outright.
    $stmt = $conn->prepare("SELECT id FROM quiz_results WHERE user_id = ? AND quiz_date = ?");
    $stmt->execute([$_SESSION['user_id'], $today_key]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['ok' => false, 'error' => 'You have already taken today\'s quiz. Your result stands and your score cannot be increased.']);
        exit;
    }

    // Re-load the exact pool the user was given (server is the source of truth)
    $serverPool = getQuestionPool($conn, $today_key);
    if (empty($serverPool)) {
        echo json_encode(['ok' => false, 'error' => 'No questions available for this quiz.']);
        exit;
    }

    $poolById = [];
    $totalPossible = 0;
    foreach ($serverPool as $q) {
        $poolById[(int)$q['id']] = $q;
        $totalPossible += (int)$q['points'];
    }

    $rawAnswers = json_decode($_POST['answers'] ?? '[]', true);
    if (!is_array($rawAnswers)) {
        $rawAnswers = [];
    }

    $score = 0;
    $correct = 0;
    foreach ($rawAnswers as $id => $letter) {
        $id = (int)$id;
        if (!isset($poolById[$id])) continue; // ignore injected ids
        $letter = strtoupper(substr(trim((string)$letter), 0, 1));
        $stmt = $conn->prepare("SELECT correct_answer FROM quiz_questions WHERE id = ?");
        $stmt->execute([$id]);
        $right = $stmt->fetchColumn();
        if ($right !== false && $letter === strtoupper($right)) {
            $score += (int)$poolById[$id]['points'];
            $correct++;
        }
    }

    $timeTaken = max(0, (int)($_POST['time_taken'] ?? 0));

    // Save the result AND award lifetime points exactly once.
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare(
            "INSERT INTO quiz_results (user_id, quiz_date, score, total_questions, time_taken) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$_SESSION['user_id'], $today_key, $score, count($serverPool), $timeTaken]);

        $stmt = $conn->prepare("UPDATE users SET total_points = total_points + ? WHERE id = ?");
        $stmt->execute([$score, $_SESSION['user_id']]);
        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof PDOException && (int)$e->getCode() === 23505) {
            // Unique (user, quiz_date) - a racing second attempt lost the insert.
            echo json_encode(['ok' => false, 'error' => 'You have already taken today\'s quiz. Your result stands and your score cannot be increased.']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Could not save your result. Please try again.']);
        }
        exit;
    }

    // This member's position in today's ranking (marks, then time)
    $rank = 1;
    $players = 0;
    $rowsToday = getDailyResults($conn, $today_key);
    foreach ($rowsToday as $i => $rr) {
        if ((int)$rr['user_id'] === (int)$_SESSION['user_id']) {
            $rank = $i + 1;
            break;
        }
    }
    $players = count($rowsToday);

    $pct = count($serverPool) > 0 ? round(100 * $correct / count($serverPool)) : 0;
    echo json_encode([
        'ok' => true,
        'score' => $score,
        'possible' => $totalPossible,
        'correct' => $correct,
        'total' => count($serverPool),
        'pct' => $pct,
        'time' => $timeTaken,
        'rank' => $rank,
        'players' => $players,
    ]);
    exit;
}

/* ---------- Page data for the client ---------- */
$publicQuestions = [];
foreach ($pool as $q) {
    $publicQuestions[] = [
        'id' => (int)$q['id'],
        'q' => $q['question'],
        'a' => $q['option_a'],
        'b' => $q['option_b'],
        'c' => $q['option_c'],
        'd' => $q['option_d'],
        'pts' => (int)$q['points'],
    ];
}

// Where this member sits in today's table (if they've already played)
$my_daily_rank = null;
if ($is_player && $already_played) {
    foreach ($daily_results as $i => $rr) {
        if ((int)$rr['user_id'] === (int)$_SESSION['user_id']) {
            $my_daily_rank = $i + 1;
            break;
        }
    }
}

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-star"></i> Know the Word</span>
            <h1>Bible Knowledge Quiz</h1>
            <div class="divider"></div>
            <p>Test your biblical knowledge daily: earn points and climb the leaderboard</p>
        </div>
    </section>

    <style>
        .quiz-shell { max-width: 760px; margin-inline: auto; }
        .quiz-intro { text-align: center; }
        .quiz-intro .big-icon { width: 86px; height: 86px; margin: 0 auto 1.2rem; border-radius: 28px; display: grid; place-items: center; background: var(--sky); color: var(--primary); font-size: 2.1rem; }
        .rule-list { max-width: 460px; margin: 1.6rem auto 0; display: grid; gap: .7rem; text-align: left; }
        .rule-list li { display: flex; gap: .8rem; align-items: center; background: var(--bg-soft); border: 1px solid var(--line); border-radius: var(--r-sm); padding: .8rem 1.1rem; font-size: .95rem; }
        .rule-list i { color: var(--gold-dark); width: 18px; text-align: center; }

        /* Active quiz */
        .quiz-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.4rem; flex-wrap: wrap; }
        .quiz-progress-wrap { flex: 1; min-width: 200px; }
        .quiz-progress { height: 8px; background: var(--line); border-radius: 4px; overflow: hidden; }
        .quiz-progress > span { display: block; height: 100%; width: 0; background: linear-gradient(90deg, var(--primary), var(--gold)); border-radius: 4px; transition: width .4s var(--ease); }
        .quiz-progress-label { display: flex; justify-content: space-between; font-size: var(--fs-sm); color: var(--muted); font-weight: 700; margin-top: .35rem; }
        .quiz-timer { display: inline-flex; align-items: center; gap: .5rem; background: var(--gold-soft); color: var(--gold-deep); border: 1px solid var(--gold); padding: .45rem 1rem; border-radius: var(--r-pill); font-weight: 800; font-variant-numeric: tabular-nums; font-size: .92rem; }
        .q-count { display: inline-flex; align-items: center; gap: .5rem; background: var(--sky); color: var(--primary); padding: .45rem 1rem; border-radius: var(--r-pill); font-weight: 800; font-size: .92rem; }

        .question-text { font-family: var(--font-display); font-size: clamp(1.25rem, 3.2vw, 1.6rem); color: var(--ink); margin-bottom: 1.6rem; line-height: 1.4; }
        .options { display: grid; gap: .8rem; }
        .option {
            display: flex; align-items: center; gap: 1rem; text-align: left; width: 100%;
            padding: 1rem 1.2rem; border-radius: var(--r); cursor: pointer;
            border: 1.5px solid var(--line); background: #fff; color: var(--text); font-size: 1rem;
            transition: all .2s var(--ease);
        }
        .option .letter { flex: none; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: var(--bg-soft); color: var(--muted); font-weight: 800; font-size: .9rem; transition: all .2s var(--ease); }
        .option:hover { border-color: var(--primary-light); transform: translateX(4px); }
        .option:hover .letter { background: var(--sky); color: var(--primary); }
        .option.selected { border-color: var(--primary); background: var(--sky); box-shadow: var(--shadow-sm); }
        .option.selected .letter { background: var(--primary); color: #fff; }
        .quiz-actions { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1.8rem; flex-wrap: wrap; }

        /* Result */
        .result-circle { width: 150px; height: 150px; margin: 0 auto 1.4rem; border-radius: 50%; display: grid; place-items: center; position: relative; }
        .result-circle .ring { position: absolute; inset: 0; border-radius: 50%; background: conic-gradient(var(--gold) calc(var(--pct) * 1%), var(--line) 0); }
        .result-circle .inner { position: relative; width: 116px; height: 116px; border-radius: 50%; background: #fff; display: grid; place-items: center; font-family: var(--font-display); font-size: 2rem; font-weight: 700; color: var(--primary); }
        .result-verdict { font-family: var(--font-display); font-size: clamp(1.4rem, 4vw, 1.9rem); color: var(--ink); margin-bottom: .5rem; }
        .result-sub { color: var(--muted); margin-bottom: 1.6rem; }
        .verdict-chips { display: flex; justify-content: center; gap: .8rem; flex-wrap: wrap; margin-bottom: 1.8rem; }
        .verdict-chip { background: var(--bg-soft); border: 1px solid var(--line); border-radius: var(--r-pill); padding: .5rem 1.2rem; font-weight: 700; font-size: .92rem; }
        .verdict-chip i { color: var(--gold-dark); margin-right: .4rem; }
    </style>

    <section class="section">
        <div class="container">
            <div class="quiz-shell">
                <?php if (!$is_logged_in): ?>
                    <!-- Logged out -->
                    <div class="panel quiz-intro reveal in">
                        <div class="big-icon"><i class="fas fa-lock"></i></div>
                        <h2 style="font-size: 1.8rem; margin-bottom: .5rem;">Login Required</h2>
                        <p style="color: var(--muted); margin-bottom: 1.8rem;">Create a free account (or log in) to take the Bible quiz and compete on the leaderboard.</p>
                        <div style="display:flex; gap:.8rem; justify-content:center; flex-wrap:wrap;">
                            <a href="login.php" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt"></i> Login</a>
                            <a href="register.php" class="btn btn-outline btn-lg"><i class="fas fa-user-plus"></i> Register Free</a>
                        </div>
                        <p style="margin-top:1.6rem; font-size:.9rem; color:var(--muted);"><i class="fas fa-trophy" style="color:var(--gold-dark);"></i> Already have points? <a href="login.php" style="color:var(--primary); font-weight:700;">Log in</a> to see them on your profile.</p>
                    </div>

                <?php elseif (!$quiz_available && $next_quiz_time): ?>
                    <!-- Scheduled window closed -->
                    <div class="panel quiz-intro reveal in">
                        <div class="big-icon"><i class="fas fa-hourglass-half"></i></div>
                        <h2 style="font-size: 1.8rem; margin-bottom: .5rem;">The quiz is closed right now</h2>
                        <p style="color: var(--muted); margin-bottom: 1.4rem;">The next quiz window opens in:</p>
                        <p style="font-family: var(--font-display); font-size: 2.2rem; color: var(--primary); font-weight: 700;" class="countdown" data-countdown="<?php echo $next_quiz_time->format('c'); ?>"></p>
                        <p style="color: var(--muted); margin-top: 1.2rem;">Meanwhile, sharpen your sword: "Thy word have I hid in mine heart." (Psalm 119:11)</p>
                    </div>

                <?php elseif ($is_admin_user): ?>
                    <!-- Admins manage the quiz, they don't play it -->
                    <div class="panel quiz-intro reveal in">
                        <div class="big-icon"><i class="fas fa-user-shield"></i></div>
                        <h2 style="font-size: 1.8rem; margin-bottom: .5rem;">Admins set the questions</h2>
                        <p style="color: var(--muted); margin-bottom: 1.8rem;">The Bible quiz is for members to play, score and climb the leaderboard. As an admin, your role is to set and schedule the questions.</p>
                        <div style="display:flex; gap:.8rem; justify-content:center; flex-wrap:wrap;">
                            <a href="admin/dashboard.php#quiz" class="btn btn-primary btn-lg"><i class="fas fa-plus-circle"></i> Manage Quiz Questions</a>
                            <a href="index.php" class="btn btn-outline btn-lg"><i class="fas fa-home"></i> Back to Home</a>
                        </div>
                    </div>

                <?php elseif ($already_played): ?>
                    <!-- Member already played today's quiz: locked in, no re-takes -->
                    <?php $ap = $already_played; $ap_secs = (int)$ap['time_taken']; ?>
                    <div class="panel quiz-intro reveal in">
                        <div class="big-icon"><i class="fas fa-check-double"></i></div>
                        <h2 style="font-size: 1.8rem; margin-bottom: .5rem;">You've already taken today's quiz</h2>
                        <p style="color: var(--muted); margin-bottom: 1.6rem;">One attempt per quiz. Your result is locked in and your score can't be increased by retrying.</p>
                        <div class="verdict-chips">
                            <span class="verdict-chip"><i class="fas fa-star"></i> <?php echo (int)$ap['score']; ?> points</span>
                            <span class="verdict-chip"><i class="fas fa-stopwatch"></i> <?php echo floor($ap_secs / 60); ?>:<?php echo str_pad($ap_secs % 60, 2, '0', STR_PAD_LEFT); ?></span>
                            <?php if ($my_daily_rank): ?><span class="verdict-chip"><i class="fas fa-trophy"></i> #<?php echo $my_daily_rank; ?> of <?php echo count($daily_results); ?> today</span><?php endif; ?>
                        </div>
                        <div style="display:flex; gap:.8rem; justify-content:center; flex-wrap:wrap;">
                            <a href="#dailyBoard" class="btn btn-gold btn-lg"><i class="fas fa-trophy"></i> Today's Leaderboard</a>
                            <?php
                            $ap_share = 'I scored ' . (int)$ap['score'] . ' pts on today\'s ' . SITE_NAME . ' Bible Quiz! Try to beat me: ' . SITE_URL . '/quiz.php';
                            ?>
                            <a class="btn btn-whatsapp" href="<?php echo htmlspecialchars(whatsapp_share_url($ap_share)); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> Share My Score</a>
                        </div>
                    </div>

                <?php elseif (empty($pool)): ?>
                    <!-- No questions available -->
                    <div class="panel quiz-intro reveal in">
                        <div class="big-icon"><i class="fas fa-book-open"></i></div>
                        <h2 style="font-size: 1.8rem; margin-bottom: .5rem;">No questions ready yet</h2>
                        <p style="color: var(--muted);">Our quiz team is preparing today's questions. Please check back soon!</p>
                        <?php if (isAdmin()): ?>
                            <a href="admin/dashboard.php#quiz" class="btn btn-outline" style="margin-top:1.2rem;"><i class="fas fa-plus-circle"></i> Add Questions (Admin)</a>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <!-- Intro -->
                    <div id="quizIntro" class="panel quiz-intro">
                        <div class="big-icon"><i class="fas fa-star"></i></div>
                        <h2 style="font-size: 1.8rem; margin-bottom: .4rem;">Welcome, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?>!</h2>
                        <p style="color: var(--muted);">Answer as many questions as you can. Every correct answer earns points.</p>
                        <ul class="rule-list">
                            <li><i class="fas fa-list-ol"></i> <?php echo count($pool); ?> questions - <?php echo (int)($pool[0]['points'] ?? 10); ?> pts each, one attempt only</li>
                            <li><i class="fas fa-check-circle"></i> Everyone gets the same questions today</li>
                            <li><i class="fas fa-bolt"></i> Take your time, but faster answers win ties</li>
                            <li><i class="fas fa-trophy"></i> Your marks + time decide today's ranking</li>
                            <li><i class="fas fa-ban"></i> After you finish, today's quiz is locked. Refreshing won't let you retake it</li>
                        </ul>
                        <div style="display:flex; gap:.8rem; justify-content:center; flex-wrap:wrap; margin-top:2rem;">
                            <button id="startQuiz" class="btn btn-gold btn-lg"><i class="fas fa-play"></i> Start Quiz</button>
                            <a class="btn btn-whatsapp" href="<?php echo htmlspecialchars(whatsapp_quiz_link()); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> Invite Friends</a>
                        </div>
                    </div>

                    <!-- Active quiz -->
                    <div id="quizActive" class="panel" style="display:none;">
                        <div class="quiz-head">
                            <span class="q-count"><i class="fas fa-question-circle"></i> Question <span id="qIndex">1</span>/<span id="qTotal">0</span></span>
                            <span class="quiz-timer"><i class="fas fa-stopwatch"></i> <span id="quizClock">00:00</span></span>
                        </div>
                        <div class="quiz-progress-wrap">
                            <div class="quiz-progress"><span id="quizBar"></span></div>
                            <div class="quiz-progress-label"><span id="qPtsLabel">10 pts each</span><span id="qProgressText">0%</span></div>
                        </div>
                        <h2 class="question-text" id="qText"></h2>
                        <div class="options" id="qOptions"></div>
                        <div class="quiz-actions">
                            <button id="quizQuit" class="btn btn-outline"><i class="fas fa-times"></i> Quit</button>
                            <button id="quizNext" class="btn btn-primary"><span id="quizNextLabel">Next</span> <i class="fas fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Result -->
                    <div id="quizResult" class="panel quiz-intro" style="display:none;"></div>
                <?php endif; ?>
            </div>

            <?php if ($is_logged_in && !empty($daily_results)): ?>
                <!-- Today's quiz results: marks decide, faster time breaks ties -->
                <div class="daily-board reveal in" id="dailyBoard" style="margin-top: 2.6rem;">
                    <div style="text-align:center;">
                        <span class="eyebrow"><i class="fas fa-trophy" style="color:var(--gold-dark);"></i> Today's Quiz</span>
                        <h2 style="font-family:var(--font-display); color:var(--ink); font-size:1.6rem; margin:.6rem 0 .3rem;">Today's Results</h2>
                        <p style="color: var(--muted); font-size: var(--fs-sm); margin-bottom: 1.4rem;">Ranked by marks. When marks are level, the faster time takes the higher spot.</p>
                    </div>
                    <?php $pos = 0; $my_uid = (int)($_SESSION['user_id'] ?? 0); ?>
                    <div class="leader-list">
                        <?php foreach ($daily_results as $dr): $pos++; $secs = (int)$dr['time_taken']; $is_me = ((int)$dr['user_id'] === $my_uid); ?>
                        <div class="leader-row" style="<?php echo $is_me ? 'border-color: rgba(242,169,0,.6); background: var(--gold-soft);' : ''; ?>">
                            <span class="rank-dot <?php echo $pos === 1 ? 'gold' : ($pos === 2 ? 'silver' : ($pos === 3 ? 'bronze' : '')); ?>"><?php echo $pos; ?></span>
                            <span class="who"><b><?php echo htmlspecialchars($dr['first_name'] . ' ' . $dr['last_name']); ?></b><?php if ($is_me): ?><small>That's you</small><?php endif; ?></span>
                            <span class="pts"><?php echo (int)$dr['score']; ?> pts · <?php echo floor($secs / 60) . ':' . str_pad($secs % 60, 2, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php if (!empty($pool) && $is_logged_in): ?>
<script>
(function () {
    'use strict';
    var QUESTIONS = <?php echo json_encode($publicQuestions); ?>;
    var ME = {
        name: <?php echo json_encode(explode(' ', $_SESSION['user_name'])[0] ?? 'Champion'); ?>,
        siteName: <?php echo json_encode(SITE_NAME); ?>,
        siteUrl: <?php echo json_encode(SITE_URL); ?>,
        quizUrl: <?php echo json_encode(SITE_URL . '/quiz.php'); ?>
    };
    var intro = document.getElementById('quizIntro');
    var active = document.getElementById('quizActive');
    var result = document.getElementById('quizResult');

    var idx = 0;
    var answers = {};      // question id -> letter
    var seconds = 0;
    var timer = null;
    var submitting = false;

    var elQIndex = document.getElementById('qIndex');
    var elQTotal = document.getElementById('qTotal');
    var elClock = document.getElementById('quizClock');
    var elBar = document.getElementById('quizBar');
    var elProgressText = document.getElementById('qProgressText');
    var elText = document.getElementById('qText');
    var elOptions = document.getElementById('qOptions');
    var elNext = document.getElementById('quizNext');
    var elNextLabel = document.getElementById('quizNextLabel');
    var elQuit = document.getElementById('quizQuit');

    elQTotal.textContent = QUESTIONS.length;
    elQIndex.textContent = '1';

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function tick() {
        seconds++;
        var m = Math.floor(seconds / 60), s = seconds % 60;
        elClock.textContent = pad(m) + ':' + pad(s);
    }

    function render() {
        var q = QUESTIONS[idx];
        elText.textContent = q.q;
        elQIndex.textContent = idx + 1;
        elBar.style.width = Math.round(((idx + (idx === QUESTIONS.length ? 1 : 0)) / QUESTIONS.length) * 100) + '%';
        elProgressText.textContent = Math.round(((idx + (answers[q.id] ? 1 : 0)) / QUESTIONS.length) * 100) + '%';
        elNextLabel.textContent = (idx === QUESTIONS.length - 1) ? 'Finish' : 'Next';

        elOptions.innerHTML = '';
        ['a', 'b', 'c', 'd'].forEach(function (key) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'option' + (answers[q.id] === key.toUpperCase() ? ' selected' : '');
            btn.innerHTML = '<span class="letter">' + key.toUpperCase() + '</span><span>' + escapeHtml(q[key]) + '</span>';
            btn.addEventListener('click', function () {
                answers[q.id] = key.toUpperCase();
                render();
            });
            elOptions.appendChild(btn);
        });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    elQuit.addEventListener('click', function () {
        if (confirm('Quit the quiz? Your progress will be lost.')) { location.reload(); }
    });

    elNext.addEventListener('click', function () {
        if (idx < QUESTIONS.length - 1) {
            idx++;
            render();
        } else {
            submit();
        }
    });

    function submit() {
        if (submitting) return;
        submitting = true;
        clearInterval(timer);
        elNext.disabled = true;

        var body = new URLSearchParams();
        body.set('action', 'submit_quiz');
        body.set('answers', JSON.stringify(answers));
        body.set('time_taken', String(seconds));

        fetch(window.location.pathname, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) { throw new Error(res.error || 'Submission failed'); }
                showResult(res);
            })
            .catch(function (err) {
                toast(err.message || 'Could not submit the quiz. Check your connection and try again.', 'fa-exclamation-circle');
                elNext.disabled = false;
                submitting = false;
                timer = setInterval(tick, 1000);
            });
    }

    function verdict(pct) {
        if (pct >= 90) return { title: 'Outstanding! A true Berean! \uD83D\uDE4C', msg: 'You know your Bible exceptionally well. Share your score!' };
        if (pct >= 70) return { title: 'Well done! Keep growing!', msg: 'A strong score. The Word is dwelling richly in you.' };
        if (pct >= 40) return { title: 'Good attempt!', msg: 'Keep studying: "faith cometh by hearing, and hearing by the word of God."' };
        return { title: 'Keep pressing on!', msg: 'Every champion started as a beginner. Try again and grow.' };
    }

    function showResult(res) {
        var v = verdict(res.pct);
        var mins = Math.floor(res.time / 60), secs = res.time % 60;
        result.innerHTML =
            '<div class="result-circle" style="--pct:' + res.pct + '"><div class="ring"></div><div class="inner">' + res.pct + '%</div></div>' +
            '<h2 class="result-verdict">' + v.title + '</h2>' +
            '<p class="result-sub">' + v.msg + '</p>' +
            '<div class="verdict-chips">' +
                '<span class="verdict-chip"><i class="fas fa-check-circle"></i>' + res.correct + '/' + res.total + ' correct</span>' +
                '<span class="verdict-chip"><i class="fas fa-star"></i>' + res.score + ' points earned</span>' +
                '<span class="verdict-chip"><i class="fas fa-stopwatch"></i>' + pad(mins) + ':' + pad(secs) + '</span>' +
                (res.rank ? '<span class="verdict-chip"><i class="fas fa-trophy"></i> Rank #' + res.rank + ' of ' + res.players + ' today</span>' : '') +
            '</div>' +
            '<div style="display:flex; gap:.8rem; justify-content:center; flex-wrap:wrap;">' +
                '<button type="button" class="btn btn-whatsapp btn-lg" id="shareResultWa"><i class="fab fa-whatsapp"></i> Share on WhatsApp</button>' +
                '<button type="button" class="btn btn-primary btn-lg" id="downloadCard"><i class="fas fa-image"></i> Download Score Card</button>' +
            '</div>' +
            '<div style="display:flex; gap:.8rem; justify-content:center; flex-wrap:wrap; margin-top:.9rem;">' +
                '<button type="button" class="btn btn-outline" onclick="location.reload()"><i class="fas fa-redo"></i> Play Again</button>' +
                '<a href="index.php#quiz" class="btn btn-outline"><i class="fas fa-trophy"></i> View Leaderboard</a>' +
            '</div>';

        var waBtn = document.getElementById('shareResultWa');
        waBtn.addEventListener('click', function () {
            var text = 'I scored ' + res.score + ' pts (' + res.pct + '%) on the ' + ME.siteName + ' Bible Quiz! \uD83D\uDC51 Try to beat me \u2014 ' + ME.quizUrl;
            window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
        });
        document.getElementById('downloadCard').addEventListener('click', function () {
            drawScoreCard(res);
        });

        result.style.display = 'block';
        result.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /* ---------- Score card: download and post to WhatsApp status ---------- */
    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function drawScoreCard(res) {
        var W = 1080, H = 1350;
        var c = document.createElement('canvas');
        c.width = W; c.height = H;
        var x = c.getContext('2d');

        // Navy gradient background
        var g = x.createLinearGradient(0, 0, W, H);
        g.addColorStop(0, '#101b3d');
        g.addColorStop(0.55, '#1e3a8a');
        g.addColorStop(1, '#16255c');
        x.fillStyle = g;
        x.fillRect(0, 0, W, H);

        // Gold radial glow
        var glow = x.createRadialGradient(W / 2, 320, 60, W / 2, 320, 620);
        glow.addColorStop(0, 'rgba(242,169,0,0.22)');
        glow.addColorStop(1, 'rgba(242,169,0,0)');
        x.fillStyle = glow;
        x.fillRect(0, 0, W, H);

        var gold = '#f2a900', white = '#ffffff', soft = 'rgba(255,255,255,0.85)';
        x.textAlign = 'center';

        // Logo
        var logo = new Image();
        logo.onload = function () {
            var lw = 108, lh = 108;
            roundRect(x, W / 2 - lw / 2, 74, lw, lh, 22);
            x.fillStyle = '#ffffff';
            x.fill();
            x.save();
            x.beginPath();
            x.arc(W / 2, 74 + lh / 2, lh / 2, 0, Math.PI * 2);
            x.clip();
            x.drawImage(logo, W / 2 - lw / 2, 74, lw, lh);
            x.restore();
            paint();
        };
        logo.onerror = function () { paint(); };
        logo.src = ME.siteUrl + '/nupsglogo.png';

        function paint() {
            x.font = '600 44px "Plus Jakarta Sans", Arial, sans-serif';
            x.fillStyle = 'rgba(255,255,255,0.7)';
            x.fillText(ME.siteName.toUpperCase() + ' \u2022 BIBLE QUIZ', W / 2, 260);

            // Percentage ring
            var cx = W / 2, cy = 470, r = 215, lw = 26;
            x.lineWidth = lw;
            x.strokeStyle = 'rgba(255,255,255,0.14)';
            x.beginPath();
            x.arc(cx, cy, r, 0, Math.PI * 2);
            x.stroke();
            x.strokeStyle = gold;
            x.lineCap = 'round';
            x.beginPath();
            x.arc(cx, cy, r, -Math.PI / 2, -Math.PI / 2 + (Math.PI * 2 * Math.max(0, Math.min(100, res.pct)) / 100));
            x.stroke();

            x.font = '800 150px Sora, Arial, sans-serif';
            x.fillStyle = white;
            x.fillText(res.pct + '%', cx, cy + 55);
            x.font = '500 40px "Plus Jakarta Sans", Arial, sans-serif';
            x.fillStyle = soft;
            x.fillText(res.correct + ' of ' + res.total + ' correct', cx, cy + 115);

            // Score
            x.font = '700 58px Sora, Arial, sans-serif';
            x.fillStyle = gold;
            x.fillText('\u2B50 ' + res.score + ' points', W / 2, 880);

            x.font = '500 52px Sora, Arial, sans-serif';
            x.fillStyle = white;
            x.fillText('Well done, ' + ME.name + '!', W / 2, 985);

            // Divider
            x.strokeStyle = gold;
            x.lineWidth = 3;
            x.beginPath();
            x.moveTo(W / 2 - 160, 1060);
            x.lineTo(W / 2 + 160, 1060);
            x.stroke();

            // Verse
            x.font = 'italic 600 46px Georgia, serif';
            x.fillStyle = soft;
            x.fillText('\u201CFor to me, to live is Christ', W / 2, 1130);
            x.fillText('and to die is gain\u201D \u2014 Phil. 1:21', W / 2, 1195);

            x.font = '600 36px "Plus Jakarta Sans", Arial, sans-serif';
            x.fillStyle = 'rgba(255,255,255,0.65)';
            x.fillText(ME.siteUrl, W / 2, 1290);

            c.toBlob(function (blob) {
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'nupsg-quiz-score.png';
                document.body.appendChild(a);
                a.click();
                setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
                toast('Score card downloaded \u2014 post it to your status!');
            }, 'image/png');
        }
    }

    document.getElementById('startQuiz').addEventListener('click', function () {
        intro.style.display = 'none';
        active.style.display = 'block';
        render();
        timer = setInterval(tick, 1000);
        active.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
})();
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
