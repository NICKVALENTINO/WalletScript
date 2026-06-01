<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once 'jsonRPCClient.php';

function satoshitize($amount)
{
    return sprintf('%.8f', (float) $amount);
}

function satoshitrim($amount)
{
    return rtrim(rtrim((string) $amount, '0'), '.');
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function get_request_value($method, $key, $default = '')
{
    $source = strtoupper((string) $method) === 'POST' ? INPUT_POST : INPUT_GET;
    $value = filter_input($source, $key, FILTER_UNSAFE_RAW);

    if ($value === null || $value === false) {
        return $default;
    }

    return trim((string) $value);
}

function is_placeholder_value($value)
{
    $value = trim((string) $value);

    return $value === ''
        || stripos($value, 'your_') === 0
        || stripos($value, 'change_me') === 0;
}

function get_config_value($envKey, $default)
{
    $value = getenv($envKey);

    return $value === false ? $default : $value;
}

function ensure_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function is_valid_csrf_token($token)
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

$server_url = $_SERVER['SERVER_NAME'] ?? 'localhost';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$date = date('Y-m-d H:i:s');

$dbConfig = [
    'host' => get_config_value('WALLETSCRIPT_DB_HOST', '127.0.0.1'),
    'name' => get_config_value('WALLETSCRIPT_DB_NAME', 'walletscript'),
    'user' => get_config_value('WALLETSCRIPT_DB_USER', 'change_me'),
    'pass' => get_config_value('WALLETSCRIPT_DB_PASS', 'change_me'),
];

$rpcConfig = [
    'host' => get_config_value('WALLETSCRIPT_RPC_HOST', '127.0.0.1'),
    'port' => get_config_value('WALLETSCRIPT_RPC_PORT', '8332'),
    'user' => get_config_value('WALLETSCRIPT_RPC_USER', 'change_me'),
    'pass' => get_config_value('WALLETSCRIPT_RPC_PASS', 'change_me'),
];

$db = null;
$db_found = false;
$db_error = '';
$rpc_error = '';
$current_user = null;
$Bytecoind = null;
$Bytecoind_Balance = '0.00000000';
$Bytecoind_accountaddresses = [];
$Bytecoind_List_Transactions = [];

try {
    if (
        is_placeholder_value($dbConfig['host'])
        || is_placeholder_value($dbConfig['name'])
        || is_placeholder_value($dbConfig['user'])
    ) {
        throw new RuntimeException('Database credentials have not been configured.');
    }

    $db = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['name']),
        $dbConfig['user'],
        $dbConfig['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $db_found = true;
} catch (Throwable $exception) {
    $db_error = $exception->getMessage();
}

$user_session = $_SESSION['user_session'] ?? null;
if (!$user_session) {
    $Logged_In = 2;
} else {
    $Logged_In = 7;

    if ($db instanceof PDO) {
        $userStatement = $db->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $userStatement->execute(['username' => $user_session]);
        $current_user = $userStatement->fetch();
    }

    if (!$current_user) {
        $_SESSION = [];
        session_destroy();
        session_start();
        $Logged_In = 2;
    }
}

if ($Logged_In === 7) {
    try {
        if (
            is_placeholder_value($rpcConfig['host'])
            || is_placeholder_value($rpcConfig['port'])
            || is_placeholder_value($rpcConfig['user'])
            || is_placeholder_value($rpcConfig['pass'])
        ) {
            throw new RuntimeException('RPC credentials have not been configured.');
        }

        $Bytecoind = new jsonRPCClient(
            sprintf(
                'http://%s:%s@%s:%s/',
                rawurlencode($rpcConfig['user']),
                rawurlencode($rpcConfig['pass']),
                $rpcConfig['host'],
                $rpcConfig['port']
            )
        );
        $wallet_id = 'walletscript:' . $user_session;
        $Bytecoind_Balance = satoshitize($Bytecoind->getbalance($wallet_id, 6));
        $Bytecoind_accountaddresses = (array) $Bytecoind->getaddressesbyaccount($wallet_id);
        $Bytecoind_List_Transactions = (array) $Bytecoind->listtransactions($wallet_id, 10);
    } catch (Throwable $exception) {
        $rpc_error = $exception->getMessage();
        $Bytecoind = null;
    }
}

$csrf_token = ensure_csrf_token();
?>
