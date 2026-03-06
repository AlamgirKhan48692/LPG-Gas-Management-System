<?php
// login.php  — Single-file Register + Login (upgraded UI + forgot password + remember me)
// NOTE: Save as login.php in your project root (same folder as other pages)
// Author: ChatGPT (modified for your project)

session_start();

include 'db_connect.php';




/* -------------------------
   POST handling
   ------------------------- */
$register_msg = "";
$login_msg    = "";
$forgot_msg   = "";
$reset_msg    = "";

/* Helper to set flash messages in session for render (persist across redirect) */
function flash($key, $msg) {
    $_SESSION['flash_'.$key] = $msg;
}
function get_flash($key) {
    $k = 'flash_'.$key;
    if (isset($_SESSION[$k])) { $v = $_SESSION[$k]; unset($_SESSION[$k]); return $v; }
    return "";
}

/* If user already has remember cookie and no session, auto-login */
if (!isset($_SESSION['username']) && isset($_COOKIE['remember_user'])) {
    $user = $_COOKIE['remember_user'];
    // fetch role & set session if user exists
    $st = $conn->prepare("SELECT username, role FROM users WHERE username = ? LIMIT 1");
    $st->bind_param("s", $user);
    $st->execute();
    $res = $st->get_result();
    if ($r = $res->fetch_assoc()) {
        $_SESSION['username'] = $r['username'];
        $_SESSION['role']     = $r['role'];
    }
    $st->close();
}

/* Handle POST actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    /* ---------- REGISTER ---------- */
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = strtolower(trim($_POST['role'] ?? ''));

        if ($username === '' || $password === '' || $role === '') {
            $register_msg = "All fields are required.";
        } else {
            $allowed_roles = ['md','operator','user','admin'];
            if (!in_array($role, $allowed_roles)) $role = 'operator';

            // only one md allowed
            if ($role === 'md') {
                $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'md' LIMIT 1");
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $register_msg = "MD already exists. Only one MD account allowed.";
                    $stmt->close();
                } else $stmt->close();
            }

            if ($register_msg === '') {
                // check duplicate username
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $register_msg = "Username already exists.";
                    $stmt->close();
                } else {
                    $stmt->close();
                    // insert
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->bind_param("sss", $username, $hash, $role);
                    if ($stmt->execute()) {
                        $register_msg = "Registration successful. You can now log in.";
                    } else {
                        $register_msg = "Database error: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
        flash('register_msg', $register_msg);
        // stay on same page and show message
        header("Location: " . $_SERVER['PHP_SELF'] . "?view=register");
        exit();
    }

    /* ---------- LOGIN ---------- */
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember']) ? true : false;

        if ($username === '' || $password === '') {
            $login_msg = "Enter username and password.";
            flash('login_msg', $login_msg);
            header("Location: " . $_SERVER['PHP_SELF'] . "?view=login");
            exit();
        }

        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                // success
                $_SESSION['username'] = $row['username'];
                $_SESSION['role']     = $row['role'];

                // set remember cookie if requested (30 days)
                if ($remember) {
                    setcookie('remember_user', $row['username'], time() + 30*24*3600, "/");
                } else {
                    // remove cookie if existed
                    if (isset($_COOKIE['remember_user'])) setcookie('remember_user', '', time()-3600, "/");
                }

                header("Location: insaf_home.php");
                exit;
            } else {
                $login_msg = "Invalid password.";
            }
        } else {
            $login_msg = "User not found.";
        }
        $stmt->close();
        flash('login_msg', $login_msg);
        header("Location: " . $_SERVER['PHP_SELF'] . "?view=login");
        exit();
    }

    /* ---------- FORGOT PASSWORD (generate token) ---------- */
    if ($action === 'forgot') {
        $uname = trim($_POST['forgot_username'] ?? '');
        if ($uname === '') {
            $forgot_msg = "Enter username.";
            flash('forgot_msg', $forgot_msg);
            header("Location: " . $_SERVER['PHP_SELF'] . "?view=forgot");
            exit();
        }
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $uname);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', time() + 15*60); // 15 minutes
            $u = $uname;
            $up = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE username = ?");
            $up->bind_param("sss", $token, $expires, $u);
            $ok = $up->execute();
            $up->close();

            if ($ok) {
                // Since no email system, show token to user (local use). In production you'd email it.
                $forgot_msg = "Reset code (valid 15 minutes): <strong>$token</strong>. Use Reset Password form.";
            } else {
                $forgot_msg = "Failed to prepare reset token.";
            }
        } else {
            $forgot_msg = "Username not found.";
        }
        flash('forgot_msg', $forgot_msg);
        header("Location: " . $_SERVER['PHP_SELF'] . "?view=forgot");
        exit();
    }

    /* ---------- RESET PASSWORD (use token) ---------- */
    if ($action === 'reset') {
        $token = trim($_POST['reset_token'] ?? '');
        $newpw = trim($_POST['reset_password'] ?? '');

        if ($token === '' || $newpw === '') {
            $reset_msg = "Token and new password are required.";
            flash('reset_msg', $reset_msg);
            header("Location: " . $_SERVER['PHP_SELF'] . "?view=reset");
            exit();
        }

        $stmt = $conn->prepare("SELECT username, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $expires = $row['reset_expires'];
            if ($expires === null || strtotime($expires) < time()) {
                $reset_msg = "Token expired. Generate a new reset token.";
            } else {
                // update password, clear token
                $hash = password_hash($newpw, PASSWORD_DEFAULT);
                $u = $row['username'];
                $up = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE username = ?");
                $up->bind_param("ss", $hash, $u);
                if ($up->execute()) {
                    $reset_msg = "Password reset successful. Please login.";
                } else {
                    $reset_msg = "Failed to update password.";
                }
                $up->close();
            }
        } else {
            $reset_msg = "Invalid token.";
        }
        $stmt->close();
        flash('reset_msg', $reset_msg);
        header("Location: " . $_SERVER['PHP_SELF'] . "?view=reset");
        exit();
    }
}

/* Render page - collect flash messages */
$register_msg = get_flash('register_msg');
$login_msg    = get_flash('login_msg');
$forgot_msg   = get_flash('forgot_msg');
$reset_msg    = get_flash('reset_msg');

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Insaf AK LPG — Login & Register</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<!-- Google font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700;900&display=swap" rel="stylesheet">

<style>
:root{
  --bg1:#0f63a3; --bg2:#00c6ff; --glass:#ffffffcc;
}
*{box-sizing:border-box}
body{margin:0;font-family:'Poppins',sans-serif;background:linear-gradient(135deg,var(--bg1),var(--bg2));min-height:100vh;display:flex;align-items:center;justify-content:center;color:#03223f}
.container{width:100%;max-width:980px;margin:24px;padding:0}
.card{background:rgba(255,255,255,0.95);border-radius:14px;overflow:hidden;display:grid;grid-template-columns:1fr 480px;box-shadow:0 10px 40px rgba(2,10,26,0.25)}
@media(max-width:980px){ .card{grid-template-columns:1fr} }
.left{padding:36px;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.03))}
.logo-wrap{display:flex;align-items:center;gap:14px}
.logo-svg{width:72px;height:72px;display:inline-block}
.brand{font-weight:900;font-size:22px;color:white}
.left .desc{color:rgba(255,255,255,0.9);margin-top:18px;line-height:1.4}
.small{font-size:13px;opacity:0.9;color:black;margin-top:10px}

/* animated logo */
.logo-circle {transform-origin:center; animation:spin 6s linear infinite;}
@keyframes spin{from{transform:rotate(0deg)} to{transform:rotate(360deg)}}

/* right form */
.right{padding:26px}
.tabs{display:flex;gap:8px}
.tab{cursor:pointer;padding:8px 12px;border-radius:8px;font-weight:700}
.tab.active{background:linear-gradient(90deg,#0f63a3,#1e88e5);color:white}
.form{margin-top:18px}
.input{display:block;width:100%;padding:10px;border-radius:8px;border:1px solid #e6eef7;margin:8px 0}
.btn{display:inline-block;padding:10px 16px;background:linear-gradient(90deg,#6a00ff,#00d2ff);color:white;border:none;border-radius:10px;font-weight:800;cursor:pointer}
.btn.ghost{background:transparent;border:1px solid #e6eef7;color:#0b3b5a}
.note{font-size:12px;color:#5e6d78;margin-top:8px}

/* alerts */
.alert{padding:10px;border-radius:8px;margin:8px 0;font-weight:700}
.alert-success{background:#e6fff4;color:#016a3d}
.alert-error{background:#ffecec;color:#8a1f2d}

/* password strength */
.strength{height:8px;border-radius:8px;background:#eee;margin-top:6px;overflow:hidden}
.strength > i{display:block;height:100%;width:0%;transition:width .25s}

/* footer small */
.footer{font-size:12px;color:#6b7c8a;margin-top:12px}

/* table like list preview for development */
.small-muted{font-size:13px;color:#6b7c8a}
</style>
</head>
<body>

<div class="container">
  <div class="card">

    <!-- left panel: animated logo & info -->
    <div class="left">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div class="logo-wrap">
          <!-- animated SVG logo -->
          <svg class="logo-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="g1" x1="0" x2="1"><stop offset="0" stop-color="#00c6ff"/><stop offset="1" stop-color="#0f63a3"/></linearGradient>
            </defs>
            <g class="logo-circle">
              <circle cx="32" cy="32" r="28" fill="url(#g1)" opacity="0.18"/>
              <path d="M32 12c6 10 10 14 0 28-10-14-6-18 0-28z" fill="#ffb74d"/>
              <path d="M32 12c-6 10-10 14 0 28 10-14 6-18 0-28z" fill="#ff7043" opacity="0.9"/>
            </g>
          </svg>

          <div>
            <div style="font-weight:900;color:black;font-size:20px">Insaf AK LPG</div>
            <div class="small">Sales • Stock • Expenditure</div>
          </div>
        </div>

        <div style="text-align:right;color:black;font-weight:700">v1.0</div>
      </div>

      <div class="desc" style="margin-top:26px; color:black">
        A lightweight local admin panel for Insaf AK LPG. Use the login or register forms on the right.
        <div class="small" style="margin-top:14px">
          • Unique MD role (only one) <br>
          • Remember me support <br>
          • Forgot password (local token) <br>
          • Password strength hints
        </div>
      </div>

      <div class="footer">Made for local use — keep DB backups. In production you should configure email for password resets.</div>
    </div>

    <!-- right panel: tabs and forms -->
    <div class="right">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div style="display:flex;gap:6px" id="tabs">
          <div class="tab active" data-view="login" onclick="switchView('login')">Login</div>
          
        </div>
        <div style="font-size:13px;color:#6b7c8a">Welcome</div>
      </div>

      <!-- messages -->
      <?php if ($login_msg): ?><div class="alert alert-error"><?= htmlspecialchars($login_msg) ?></div><?php endif; ?>
      <?php if ($register_msg): ?><div class="alert <?= strpos($register_msg,'successful')!==false ? 'alert-success' : 'alert-error' ?>"><?= $register_msg ?></div><?php endif; ?>
      <?php if ($forgot_msg): ?><div class="alert <?= strpos($forgot_msg,'Reset code')!==false ? 'alert-success' : 'alert-error' ?>"><?= $forgot_msg ?></div><?php endif; ?>
      <?php if ($reset_msg): ?><div class="alert <?= strpos($reset_msg,'successful')!==false ? 'alert-success' : 'alert-error' ?>"><?= $reset_msg ?></div><?php endif; ?>

      <!-- LOGIN FORM -->
      <div id="view-login" class="view form">
        <form method="post" onsubmit="return handleLogin(event)">
          <input type="hidden" name="action" value="login">
          <input name="username" id="login_username" class="input" placeholder="Username"
       autocomplete="off" spellcheck="false">

          <div style="position:relative;">
  <input name="password" id="login_password" type="password" class="input" placeholder="Password" required>
  <span onclick="togglePassword('login_password', this)" 
        style="position:absolute; right:12px; top:14px; cursor:pointer; font-size:13px; color:#0b3b5a;">
        Show
  </span>
</div>

            <input type="checkbox" name="remember" id="remember"> <span>Remember me</span>
          </label>
          <div style="margin-top:12px;display:flex;gap:8px">
            <button class="btn">Login</button>
            <button type="button" class="btn ghost" onclick="switchView('forgot')">Forgot?</button>
          </div>
        </form>
      </div>

      <!-- REGISTER FORM -->
      <div id="view-register" class="view form" style="display:none">
        <form method="post" onsubmit="return handleRegister(event)">
          <input type="hidden" name="action" value="register">
          <input name="username" id="reg_username" class="input" placeholder="Username" 
       autocomplete="off" spellcheck="false" autocorrect="off" autocapitalize="off" required>

          <div style="position:relative;">
  <input name="password" id="reg_password" type="password" class="input" placeholder="Password" required>
  <span onclick="togglePassword('reg_password', this)" 
        style="position:absolute; right:12px; top:14px; cursor:pointer; font-size:13px; color:#0b3b5a;">
        Show
  </span>
</div>

          <div style="display:flex;gap:8px;">
            <select name="role" id="reg_role" class="input" style="flex:1">
              <option value="">Select role</option>
              <option value="md">MD</option>
              <option value="operator">Operator</option>
              <option value="user">User</option>
            </select>
          </div>
          <div class="note">Password strength</div>
          <div class="strength" id="reg_strength"><i></i></div>

          <div style="margin-top:12px;display:flex;gap:8px">
            <button class="btn">Register</button>
            <button type="button" class="btn ghost" onclick="switchView('login')">Back</button>
          </div>
        </form>
      </div>

      <!-- FORGOT FORM -->
      <div id="view-forgot" class="view form" style="display:none">
        <form method="post" onsubmit="return handleForgot(event)">
          <input type="hidden" name="action" value="forgot">
          <input name="forgot_username" id="forgot_username" class="input" placeholder="Enter username to reset" required>
          <div style="margin-top:12px;display:flex;gap:8px">
            <button class="btn">Generate Reset Code</button>
            <button type="button" class="btn ghost" onclick="switchView('login')">Back</button>
          </div>
          <div class="note">The reset code will show on screen (local app). In production you'd email it.</div>
        </form>
      </div>

      <!-- RESET FORM -->
      <div id="view-reset" class="view form" style="display:none">
        <form method="post" onsubmit="return handleReset(event)">
          <input type="hidden" name="action" value="reset">
          <input name="reset_token" id="reset_token" class="input" placeholder="Reset token" required>
          <div style="position:relative;">
          <input name="reset_password" id="reset_password" type="password" class="input" placeholder="New password" required>
          <span onclick="togglePassword('reset_password', this)" 
                style="position:absolute; right:12px; top:14px; cursor:pointer; font-size:13px; color:#0b3b5a;">
                Show
          </span>
          </div>

          <div class="note">Password strength</div>
          <div class="strength" id="reset_strength"><i></i></div>
          <div style="margin-top:12px;display:flex;gap:8px">
            <button class="btn">Reset Password</button>
            <button type="button" class="btn ghost" onclick="switchView('login')">Back</button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<script>
/* UI helpers */
function switchView(v){
  document.querySelectorAll('.view').forEach(x=>x.style.display='none');
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.getElementById('view-'+v).style.display='block';
  document.querySelector('.tab[data-view="'+v+'"]').classList.add('active');
  clearInputs();
  // scroll to top of form area (on small screens)
  window.scrollTo({top:0,behavior:'smooth'});
}

/* Clear only inputs in right panel */
function clearInputs(){
  document.querySelectorAll('.right .input').forEach(i=>i.value='');
  // clear password strength bars
  setStrength('reg_strength',0);
  setStrength('reset_strength',0);
}

/* initial view from ?view param */
(function(){
  const url = new URL(window.location.href);
  const v = url.searchParams.get('view') || 'login';
  if (['login','register','forgot','reset'].includes(v)) switchView(v);
})();

/* Form handlers that POST via normal form submission so server side handles logic */
function handleLogin(e){
  // allow normal submit
  return true;
}
function handleRegister(e){
  // client-side password strength check (optional)
  return true;
}
function handleForgot(e){ return true; }
function handleReset(e){ return true; }

/* Password strength meter (simple) */
function scorePassword(p){
  let score = 0;
  if (!p) return 0;
  if (p.length >= 8) score += 30;
  if (/[A-Z]/.test(p)) score += 20;
  if (/[0-9]/.test(p)) score += 20;
  if (/[^A-Za-z0-9]/.test(p)) score += 30;
  return Math.min(100, score);
}
function setStrength(id,score){
  const el = document.getElementById(id);
  if (!el) return;
  const bar = el.querySelector('i');
  bar.style.width = score + '%';
  if (score < 40) bar.style.background = '#ff6b6b';
  else if (score < 70) bar.style.background = '#ffb74d';
  else bar.style.background = '#4caf50';
}
document.getElementById('reg_password').addEventListener('input', e => {
  setStrength('reg_strength', scorePassword(e.target.value));
});
document.getElementById('reset_password').addEventListener('input', e => {
  setStrength('reset_strength', scorePassword(e.target.value));
});

/* Keep tabs accessible by keyboard (optional) */
document.querySelectorAll('.tab').forEach(t => {
  t.addEventListener('keydown', e => {
    if (e.key === 'Enter') switchView(t.dataset.view);
  });
});


function togglePassword(id, el){
    const field = document.getElementById(id);
    if(field.type === "password"){
        field.type = "text";
        el.textContent = "Hide";
    } else {
        field.type = "password";
        el.textContent = "Show";
    }
}

</script>

</body>
</html>
