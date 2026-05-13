// Code Editor and Runner

let editor;
let startTime   = null;
let submitCount = 0;
let hintUsed    = false;

document.addEventListener('DOMContentLoaded', function () {
    if (!level) return;
    initializeEditor();
    setupEventListeners();
});

function initializeEditor() {
    let mode = 'text/javascript';
    if (level.language === 'php') mode = 'application/x-httpd-php';
    if (level.type === 'page_recreation') mode = 'text/html';

    editor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
        mode,
        lineNumbers: true,
        theme: 'default',
        indentUnit: 4,
        indentWithTabs: false,
        lineWrapping: true,
        matchBrackets: true,
        autoCloseBrackets: true,
    });

    editor.on('change', () => {
        if (!startTime) startTime = Date.now();
        if (isPageRecreation) updatePreview();
    });
}

function setupEventListeners() {
    document.getElementById('runBtn').addEventListener('click', runCode);
    document.getElementById('submitBtn').addEventListener('click', submitCode);
    document.getElementById('hintBtn').addEventListener('click', showHint);

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function (e) { switchTab(this.dataset.tab, e); });
    });

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.addEventListener('click', () => window.location.href = '/logout.php');
}

// ── Run ──────────────────────────────────────────────────────────────────────

function runCode() {
    if (!startTime) startTime = Date.now();
    const code = editor.getValue();
    if (isPageRecreation) { updatePreview(); return; }
    if (level.language === 'javascript') runJavaScript(code);
    else runPHP(code);
}

function updatePreview() {
    const frame = document.getElementById('previewFrame');
    if (frame) frame.srcdoc = editor.getValue();
}

// Capture JS output using new Function so `console` is always our object
function captureJsOutput(code) {
    const lines = [];
    const con = {
        log:   (...a) => lines.push(a.map(x => typeof x === 'object' ? JSON.stringify(x) : String(x)).join(' ')),
        error: (...a) => lines.push(a.map(String).join(' ')),
        warn:  (...a) => lines.push(a.map(String).join(' ')),
        info:  (...a) => lines.push(a.map(String).join(' ')),
    };
    try { new Function('console', code)(con); }
    catch (e) { lines.push('Error: ' + e.message); }
    return lines.join('\n');
}

function runJavaScript(code) {
    const out = captureJsOutput(code);
    document.getElementById('output').textContent = out || '(no output)';
    switchTabByName('output');
}

function runPHP(code) {
    const outputEl = document.getElementById('output');
    outputEl.textContent = 'Running...';
    switchTabByName('output');
    fetch('/api/run-php.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code })
    })
    .then(r => r.json())
    .then(d => { outputEl.textContent = d.output || d.error || '(no output)'; })
    .catch(e => { outputEl.textContent = 'Error: ' + e.message; });
}

// ── Submit ───────────────────────────────────────────────────────────────────

function submitCode() {
    if (!isLoggedIn) {
        alert('Please log in to submit');
        window.location.href = '/login.php';
        return;
    }

    if (!startTime) startTime = Date.now();
    const code      = editor.getValue();
    const timeSpent = Math.floor((Date.now() - startTime) / 1000);
    const feedbackEl = document.getElementById('feedback');

    if (isPageRecreation) {
        submitCount++;
        doSubmit(code, '', timeSpent, submitCount);
        return;
    }

    if (level.language === 'javascript') {
        const output = captureJsOutput(code);
        submitCount++;
        doSubmit(code, output, timeSpent, submitCount);
    } else {
        feedbackEl.innerHTML = '<em>Running PHP...</em>';
        fetch('/api/run-php.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code })
        })
        .then(r => r.json())
        .then(d => {
            submitCount++;
            doSubmit(code, d.output || '', timeSpent, submitCount);
        })
        .catch(e => {
            feedbackEl.innerHTML = `<div class="error">Run failed: ${e.message}</div>`;
        });
    }
}

function doSubmit(code, output, timeSpent, tries) {
    const feedbackEl = document.getElementById('feedback');
    feedbackEl.innerHTML = '<em>Submitting…</em>';

    fetch('/api/submit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ level_id: level.id, code, output, time_spent_seconds: timeSpent, tries, hint_used: hintUsed })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            feedbackEl.innerHTML = `<div class="error">Server error: ${escapeHtml(data.error)}</div>`;
            return;
        }
        // Page recreation — submitted for manual review
        if (data.pending_review) {
            feedbackEl.innerHTML = `
                <div class="success" style="background:#e8f4fd;color:#1a5276;border-color:#aed6f1;">
                    📬 Your code has been submitted for review!<br>
                    <small>The admin will check your work and award points. Check your profile for updates.</small>
                </div>`;
            document.getElementById('submitBtn').textContent = '✔ Resubmit';
            return;
        }
        if (data.already_completed) {
            feedbackEl.innerHTML = `<div class="success">✅ You already completed this level!</div>`;
            return;
        }
        if (data.passed) {
            feedbackEl.innerHTML = `<div class="success">🎉 Level Completed! +${data.points ?? 0} pts</div>`;
            if (data.unlocked_achievements?.length) showAchievements(data.unlocked_achievements);
            setTimeout(() => {
                window.location.href = data.next_level_id ? `/?id=${data.next_level_id}` : '/';
            }, 2500);
        } else {
            // Use server-normalized values — these are EXACTLY what was compared
            const got = data.debug_got      ?? output.trim().toLowerCase();
            const exp = data.debug_expected ?? (level.expected_output ?? '').trim().toLowerCase();
            feedbackEl.innerHTML = `
                <div class="error">❌ Output doesn't match (Attempt ${tries})</div>
                <div class="diff-view">
                    <div class="actual">Got:      <code>${escapeHtml(got)}</code></div>
                    <div class="expected">Expected: <code>${escapeHtml(exp)}</code></div>
                </div>`;
        }
    })
    .catch(e => { feedbackEl.innerHTML = `<div class="error">Network error: ${escapeHtml(e.message)}</div>`; });
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function showHint() {
    const hint = level.hint || level.description || 'No hint available.';
    if (!hintUsed) {
        hintUsed = true;
        const btn = document.getElementById('hintBtn');
        btn.textContent = '💡 Hint (used)';
        btn.disabled = true;
    }
    alert('Hint: ' + hint);
}

function showAchievements(achievements) {
    const toast = document.getElementById('achievementToast');
    let i = 0;
    (function next() {
        if (i >= achievements.length) return;
        const a = achievements[i++];
        toast.innerHTML = `${a.icon} <strong>${a.name}</strong>: ${a.description}`;
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; setTimeout(next, 300); }, 4000);
    })();
}

function switchTab(tabName, event) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabName + 'Tab').style.display = 'block';
    event.target.classList.add('active');
}

function switchTabByName(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const tab = document.getElementById(name + 'Tab');
    if (tab) tab.style.display = 'block';
    const btn = document.querySelector(`.tab-btn[data-tab="${name}"]`);
    if (btn) btn.classList.add('active');
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}
