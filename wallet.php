<?php
require_once 'auth.php';

if ($Logged_In !== 7) {
    header('Location: index.php');
    exit;
}

$withdraw_message = '';
$new_address_action = get_request_value('POST', 'newaddr');
$withdraw_amount = get_request_value('POST', 'amount');
$withdraw_address = get_request_value('POST', 'address');
$postedToken = get_request_value('POST', 'csrf_token');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_valid_csrf_token($postedToken)) {
    $withdraw_message = 'Your session expired. Please try again.';
} elseif ($rpc_error !== '') {
    $withdraw_message = 'Wallet RPC is unavailable until credentials are fixed.';
} elseif ($new_address_action === 'go') {
    try {
        $Bytecoind->getnewaddress($wallet_id);
        header('Location: wallet.php');
        exit;
    } catch (Throwable $exception) {
        $withdraw_message = $exception->getMessage();
    }
} elseif ($withdraw_address !== '' || $withdraw_amount !== '') {
    if ($withdraw_address === '' || $withdraw_amount === '') {
        $withdraw_message = 'Both amount and address are required.';
    } elseif (!is_numeric($withdraw_amount) || (float) $withdraw_amount <= 0) {
        $withdraw_message = 'Please enter a valid withdrawal amount.';
    } elseif ((float) $withdraw_amount > (float) $Bytecoind_Balance) {
        $withdraw_message = 'You do not have enough funds for that withdrawal.';
    } else {
        try {
            $normalizedAmount = satoshitize($withdraw_amount);
            $Bytecoind_Withdraw_From = $Bytecoind->sendfrom($wallet_id, $withdraw_address, (float) $normalizedAmount, 6);
            $withdraw_message = 'Withdrawal submitted. Transaction ID: ' . $Bytecoind_Withdraw_From;
            $Bytecoind_Balance = satoshitize($Bytecoind->getbalance($wallet_id, 6));
            $Bytecoind_accountaddresses = (array) $Bytecoind->getaddressesbyaccount($wallet_id);
            $Bytecoind_List_Transactions = (array) $Bytecoind->listtransactions($wallet_id, 10);
        } catch (Throwable $exception) {
            $withdraw_message = $exception->getMessage();
        }
    }
}
?>
<html>
<head>
   <title>WalletScript</title>
   <style>
      body { background: #04B431; color: #000000; font-family: Georgia, serif; font-size: 14px; margin: 0; padding: 0; }
      hr { height: 1px; background: #04B431; border: 0; }
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
         <td align="right" valign="top" nowrap>
            Balance: <?php echo h(satoshitrim($Bytecoind_Balance)); ?><br>
            <a href="logout.php">Logout</a>
         </td>
      </tr>
   </table>
   </div>
   <?php if ($rpc_error !== '') { ?>
      <div class="notice">RPC configuration error: <?php echo h($rpc_error); ?></div>
   <?php } ?>
   <p></p>
   <div align="center" style="width: 700px; background: #FFFFFF; font-weight: bold; border: 4px solid #0B6121; padding:10px; border-radius: 15px;">
   <table style="width: 650px;">
      <tr>
         <td colspan="2" align="left" valign="top" style="padding: 5px;" nowrap>
            <?php if ($withdraw_message !== '') { echo '<center><b style="color: #FF0000;">' . h($withdraw_message) . '</b></center>'; } ?>
            <b>Withdraw:</b>
            <center>
            <form action="wallet.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <table>
               <tr>
                  <td align="right" nowrap><b>Amount</b></td>
                  <td align="left" nowrap><input type="text" name="amount" style="width: 100px;" inputmode="decimal"></td>
                  <td align="right" nowrap><b>Address</b></td>
                  <td align="left" nowrap><input type="text" name="address" style="width: 180px;"></td>
                  <td align="left" nowrap><input type="submit" class="button" name="submit" value="Withdraw"></td>
               </tr>
            </table>
            </form>
            </center>
            <hr>
            <b>Deposit:</b>
            <form action="wallet.php" method="POST" style="display:inline;">
               <input type="hidden" name="newaddr" value="go">
               <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
               <input type="submit" class="button" value="Generate new address">
            </form>
            <center>
            <table>
               <tr>
                  <td align="left" style="padding: 3px;" nowrap>
                     <?php foreach ($Bytecoind_accountaddresses as $Bytecoind_accountaddress) { ?>
                        <?php echo h($Bytecoind_accountaddress); ?><br>
                     <?php } ?>
                  </td>
               </tr>
            </table>
            </center>
            <hr>
            <b>Last 10 Transactions:</b>
            <center>
            <table>
               <tr>
                  <td align="left" style="font-weight: bold; padding: 3px;" nowrap>Date</td>
                  <td align="left" style="font-weight: bold; padding: 3px;" nowrap>Address</td>
                  <td align="right" style="font-weight: bold; padding: 3px;" nowrap>Type</td>
                  <td align="right" style="font-weight: bold; padding: 3px;" nowrap>Amount</td>
                  <td align="right" style="font-weight: bold; padding: 3px;" nowrap>Fee</td>
                  <td align="right" style="font-weight: bold; padding: 3px;" nowrap>Confs</td>
                  <td align="left" style="font-weight: bold; padding: 3px;" nowrap>Info</td>
               </tr>
               <?php
               $bold_txxs = '';
               foreach ($Bytecoind_List_Transactions as $Bytecoind_List_Transaction) {
                   $bold_txxs = $bold_txxs === '' ? 'color: #666666; ' : '';
                   $isSend = ($Bytecoind_List_Transaction['category'] ?? '') === 'send';
                   $tx_type = $isSend ? '<b style="color: #FF0000;">Sent</b>' : '<b style="color: #01DF01;">Received</b>';
                   $txTime = !empty($Bytecoind_List_Transaction['time']) ? date('n/j/Y h:i a', (int) $Bytecoind_List_Transaction['time']) : '-';
                   $txAddress = h($Bytecoind_List_Transaction['address'] ?? '');
                   $txAmount = h(satoshitrim(satoshitize(abs((float) ($Bytecoind_List_Transaction['amount'] ?? 0)))));
                   $txFee = h(satoshitrim(satoshitize(abs((float) ($Bytecoind_List_Transaction['fee'] ?? 0)))));
                   $txConfs = h((string) ($Bytecoind_List_Transaction['confirmations'] ?? 0));
                   $txId = h($Bytecoind_List_Transaction['txid'] ?? '');

                   echo '<tr>
                           <td align="left" style="' . $bold_txxs . 'padding: 3px;" nowrap>' . h($txTime) . '</td>
                           <td align="left" style="' . $bold_txxs . 'padding: 3px;" nowrap>' . $txAddress . '</td>
                           <td align="right" style="' . $bold_txxs . 'padding: 3px;" nowrap>' . $tx_type . '</td>
                           <td align="right" style="' . $bold_txxs . 'padding: 3px;" nowrap>' . $txAmount . '</td>
                           <td align="right" style="' . $bold_txxs . 'padding: 3px;" nowrap>' . $txFee . '</td>
                           <td align="right" style="' . $bold_txxs . 'padding: 3px;" nowrap>' . $txConfs . '</td>
                           <td align="left" style="padding: 3px;" nowrap><a href="https://www.blockchain.com/explorer/search?search=' . $txId . '" target="_blank" rel="noopener noreferrer">Info</a></td>
                        </tr>';
               }
               ?>
            </table>
            </center>
         </td>
      </tr>
   </table>
   </div>
   <p></p>
   </center>
</body>
</html>
<?php require 'footer.php'; ?>
