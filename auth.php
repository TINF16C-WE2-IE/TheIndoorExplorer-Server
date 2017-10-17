<?php
include('./oauthClientCredentials.php');
include('./databasecon.php');

$redirectUrl = 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
session_start();
$action = $_GET['action'] ?? '';

$provider = $_GET['providerId'] ?? 'none';
$provider = isset($_GET['state']) ? explode('-', $_GET['state'])[0] : $provider;
if(isset($providers[$provider])) {
    define('PROVIDER', $providers[$provider]);
} else {
    die('no valid provider');
}

if ($action == 'login')
{
    $_SESSION['state'] = PROVIDER['providerId'].'-'.hash('sha256', OAUTH_STATE_SECRET.microtime(true));
    unset($_SESSION['access_token']);
    $params = array(
        'client_id' => PROVIDER['clientId'],
        'redirect_uri' => $redirectUrl,
        'response_type' => 'code',
        'scope' => PROVIDER['scope'],
        'state' => $_SESSION['state']
    );
    header('Location: '.PROVIDER['authorizeUrl'].'?'.http_build_query($params));
    die();
}

if (isset($_GET['code']))
{
    if (!isset($_GET['state']) || $_SESSION['state'] != $_GET['state'])
    {
        header('Location: '.$_SERVER['PHP_SELF']);
        die();
    }
    $token = oauthRequest(PROVIDER['tokenUrl'], array(
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirectUrl,
        'state' => $_SESSION['state'],
        'code' => $_GET['code']
    ));

    $username = getUsernameFromApi($token->access_token);
    saveUserToDB($username, PROVIDER['providerId']);
    header('Location: https://tinf16c-we2-ie.github.io/');
}

function oauthRequest($url, $post) 
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($ch, CURLOPT_USERPWD, PROVIDER['clientId'].':'.PROVIDER['clientSecret']);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}

function saveUserToDB($username, $provider) {
    global $con;
    $sql = $con->prepare('INSERT IGNORE INTO Person (Username, Provider) VALUES(?, ?)');
    if ($sql->execute(array($username, $provider))) {
        $sql = $con->prepare('SELECT PersonId FROM Person WHERE Username = ? AND Provider = ?');
        $sql->execute(array($username, $provider));
        $row = $sql->fetch();
        if ($row) {
            $_SESSION['PersonId'] = $row['PersonId'];
        }
    } else die('User duplicate');    
}

function getUsernameFromApi($token) {
    $username = '';
    if (PROVIDER['providerId'] == 'nubenum') {
        $userData = oauthRequest(PROVIDER['introspectUrl'], array(
            'token' => $token,
            'token_type_hint' => 'access_token'
        ));
        $username = $userData->username ?? '';
    } else if (PROVIDER['providerId'] == 'github') {
        $url = PROVIDER['introspectUrl'];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: token '.$token, 'User-Agent: indoor-explorer'));
        $response = curl_exec($ch);
        curl_close($ch);
        $userData = json_decode($response, true);
        $username = $userData[0]['email'] ?? '';
    }
    if (strlen($username) > 0) return $username;
    else die('got no username');
}
?>
Not a valid request
