<?php
include('./oauthClientCredentials.php');
include('./databasecon.php');

$redirectUrl = 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
session_start();
$action = $_GET['action'] ?? '';


if ($action == 'login')
{
    $_SESSION['state'] = PROVIDER['d'].'-'.hash('sha256', OAUTH_STATE_SECRET.microtime(true));
    unset($_SESSION['access_token']);
    $params = array(
        'client_id' => PROVIDER['clientId'],
        'redirect_uri' => $redirectUrl,
        'response_type' => 'code',
        'scope' => 'read',
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

    $userData = oauthRequest(PROVIDER['introspectUrl'], array(
        'token' => $token->access_token,
        'token_type_hint' => 'access_token'
    ));
    $_SESSION['username'] = $userData->username;
    var_dump($token);    
    var_dump($userData);
    saveUserToDB($userData->username, PROVIDER['providerId']);
//    header('Location: ' . $_SERVER['PHP_SELF']);
}

function oauthRequest($url, $post) 
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    curl_setopt($ch, CURLOPT_USERPWD, PROVIDER['d'].':'.PROVIDER['clientSecret']);
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
        $row= $sql->fetch();
        var_dump($row);
        if ($row) {
            $_SESSION['PersonId'] = $row['PersonId'];
        }
    } else die('User duplicate');    
}


if (isset($_SESSION['PersonId'])): ?>
    <?=$_SESSION['PersonId']?>
<? endif; ?>
