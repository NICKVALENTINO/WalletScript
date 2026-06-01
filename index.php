<?php
require_once 'auth.php';

if ($Logged_In === 7) {
    header('Location: wallet.php');
    exit;
}

$myusername = get_request_value('POST', 'username');
$mypassword = get_request_value('POST', 'password');
$myrepeat = get_request_value('POST', 'repeat');
$form_action = get_request_value('POST', 'action');
$postedToken = get_request_value('POST', 'csrf_token');
$return_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_valid_csrf_token($postedToken)) {
    $return_error = 'Your session expired. Please try again.';
} elseif ($form_action === 'login') {
    if ($myusername === '' || $mypassword === '') {
        $return_error = 'Username and password are required.';
    } elseif (!$db_found || !($db instanceof PDO)) {
        $return_error = 'Database connection is unavailable.';
    } else {
        $loginStatement = $db->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $loginStatement->execute(['username' => $myusername]);
        $user = $loginStatement->fetch();

        if (!$user) {
            $return_error = 'User does not exist.';
        } else {
            $storedPassword = (string) $user['password'];
            $legacyHash = md5($mypassword);
            $passwordMatches = password_verify($mypassword, $storedPassword) || hash_equals($storedPassword, $legacyHash);

            if (!$passwordMatches) {
                $return_error = 'Invalid password.';
            } else {
                if (hash_equals($storedPassword, $legacyHash)) {
                    $upgradeStatement = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
                    $upgradeStatement->execute([
                        'password' => password_hash($mypassword, PASSWORD_DEFAULT),
                        'id' => $user['id'],
                    ]);
                }

                session_regenerate_id(true);
                $_SESSION['user_session'] = $user['username'];
                header('Location: wallet.php');
                exit;
            }
        }
    }
} elseif ($form_action === 'register') {
    if ($myusername === '' || $mypassword === '' || $myrepeat === '') {
        $return_error = 'All registration fields are required.';
    } elseif ($mypassword !== $myrepeat) {
        $return_error = 'Passwords did not match.';
    } else {
        $uLength = strlen($myusername);
        $pLength = strlen($mypassword);
        $errors = [];

        if ($uLength < 3 || $uLength > 30) {
            $errors[] = 'Username must be between 3 and 30 characters.';
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $myusername)) {
            $errors[] = 'Username may only contain letters, numbers, and underscores.';
        }

        if ($pLength < 8 || $pLength > 128) {
            $errors[] = 'Password must be between 8 and 128 characters.';
        }

        if (!$db_found || !($db instanceof PDO)) {
            $errors[] = 'Database connection is unavailable.';
        }

        if (!$errors) {
            $registerStatement = $db->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
            $registerStatement->execute(['username' => $myusername]);

            if ($registerStatement->fetch()) {
                $return_error = 'Username already taken.';
            } else {
                $insertStatement = $db->prepare(
                    'INSERT INTO users (date, ip, username, password) VALUES (:date, :ip, :username, :password)'
                );
                $insertStatement->execute([
                    'date' => $date,
                    'ip' => $ip,
                    'username' => $myusername,
                    'password' => password_hash($mypassword, PASSWORD_DEFAULT),
                ]);

                session_regenerate_id(true);
                $_SESSION['user_session'] = $myusername;
                header('Location: wallet.php');
                exit;
            }
        } else {
            $return_error = implode('<br>', array_map('h', $errors));
        }
    }
}

if ($return_error !== '') {
    $return_error = '<center><p><b style="color: #FF0000;">' . $return_error . '</b></p></center>';
}
?>
<html>
<head>
   <title>WalletScript</title>
   <style>
      body { background: #04B431; color: #000000; font-family: Georgia, serif; font-size: 14px; margin: 0; padding: 0; }
      table { font-size: 14px; }
      a { text-decoration: none; color: #04B431; }
      input { height: 22px; border: 1px solid #04B431; border-radius: 6px; }
      .button { height: 28px; background: #0B6121; border: 1px solid #0B6121; color: #FFFFFF; font-weight: bold; border-radius: 6px; padding: 0 10px; }
      .notice { background: #FFF7D6; border: 1px solid #E0C35A; color: #5B4B17; margin: 10px auto; padding: 10px; width: 680px; border-radius: 8px; }
   </style>
</head>
<body>
   <center>
   <div align="center" style="width: 700px; background: #FFFFFF; font-weight: bold; border-left: 4px solid #0B6121; border-right: 4px solid #0B6121; border-bottom: 4px solid #0B6121; border-top: 0 solid #FFFFFF; padding:10px; border-radius: 0 0 15px 15px;">
   <table style="width: 100%; height: 50px;">
      <tr>
         <td align="left" style="width: 30px;" nowrap>
            <a href="http://<?php echo h($server_url); ?>">WalletScript</a>
         </td>
         <td align="left" style="font-size: 18px; font-weight: bold;" nowrap>
            <a href="http://<?php echo h($server_url); ?>" style="color: #04B431;">WalletScript</a>
         </td>
         <td align="right" nowrap>
            <form action="index.php" method="POST">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <table>
               <tr>
                  <td align="right"><input type="text" name="username" placeholder="Username" value="<?php echo h($myusername); ?>" style="width: 110px;" required autofocus></td>
                  <td align="right"><input type="password" name="password" placeholder="Password" style="width: 110px;" required></td>
                  <td colspan="2" align="right"><input type="submit" name="submit" class="button" value="Login"></td>
               </tr>
            </table>
            </form>
         </td>
      </tr>
   </table>
   </div>
   <?php if ($db_error !== '') { ?>
      <div class="notice">Database configuration error: <?php echo h($db_error); ?></div>
   <?php } ?>
   <p></p>
   <div align="center" style="width: 700px; background: #FFFFFF; font-weight: bold; border: 4px solid #0B6121; padding:10px; border-radius: 15px;">
   <table style="width: 100%; height: 50px;">
      <tr>
         <td align="left" valign="top" style="padding-left: 15px;" nowrap>
            <b>Welcome</b><br>
            WalletScript is a lightweight PHP wallet front-end for Bitcoin-style RPC wallets.<br>
            Withdrawals still pay the blockchain network fee charged by your coin daemon.<br>
            No extra fee is charged by this application itself.
         </td>
         <td align="right" valign="top" style="padding-right: 15px;" nowrap>
   <form action="index.php" method="POST">
   <input type="hidden" name="action" value="register">
   <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
   <table>
      <tr>
         <td align="left"><b>Register a new account:</b></td>
      </tr><tr>
         <td align="center"><?php echo $return_error; ?></td>
      </tr><tr>
         <td align="right"><input type="text" name="username" placeholder="Username" value="<?php echo h($myusername); ?>" style="width: 180px;" required></td>
      </tr><tr>
         <td align="right"><input type="password" name="password" placeholder="Password" style="width: 180px;" required></td>
      </tr><tr>
         <td align="right"><input type="password" name="repeat" placeholder="Repeat Password" style="width: 180px;" required></td>
      </tr><tr>
         <td align="right"><input type="submit" name="submit" class="button" value="Register"></td>
      </tr>
   </table>
         </td>
      </tr>
   </table>
   </div>
   <p></p>
   </center>
</body>
</html>
<?php require 'footer.php'; ?>
