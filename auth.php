<?php
include('./oauthClientCredentials.php');

$authorizeURL = 'https://auth.nubenum.com/web/authorize';
$redirectURL = 'https://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
$internalPrefix =  'http://oauth2-server:8080/v1/oauth/';
$tokenURL = $internalPrefix.'tokens';
$introspectURL = $internalPrefix.'introspect';
session_start();
$action = $_GET['action'] ?? '';

if ($action == 'login')
{
    $_SESSION['state'] = hash('sha256', OAUTH_STATE_SECRET.microtime(true));
    unset($_SESSION['access_token']);
    $params = array(
        'client_id' => OAUTH_CLIENT_ID,
        'redirect_uri' => $redirectURL,
        'response_type' => 'code',
        'scope' => 'read',
        'state' => $_SESSION['state']
    );
    header('Location: '.$authorizeURL.'?'.http_build_query($params));
    die();
}

if (isset($_GET['code']))
{
    if (!isset($_GET['state']) || $_SESSION['state'] != $_GET['state'])
    {
        header('Location: '.$_SERVER['PHP_SELF']);
        die();
    }
    $token = oauthRequest($tokenURL, array(
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirectURL,
        'state' => $_SESSION['state'],
        'code' => $_GET['code']
    ));
    $_SESSION['access_token'] = $token->access_token;
    $_SESSION['refresh_token'] = $token->refresh_token;
    $userData = oauthRequest($introspectURL, array(
        'token' => $token->access_token,
        'token_type_hint' => 'access_token'
    ));
    $_SESSION['username'] = $userData->username;
    var_dump($token);    
    var_dump($userData);
//    header('Location: ' . $_SERVER['PHP_SELF']);
}

if (isset($_SESSION['access_token'])) 
{
    echo '<h3>Logged In</h3>';
    echo '<h4>'.$_SESSION['refresh_token']. 'd</h4>';
    echo '<pre>';
    echo '</pre>';
} 
else
{
    echo '<h3>Not logged in</h3>';
    echo '<p><a href="?action=login">Log In</a></p>';
}

function oauthRequest($url, $post) 
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    curl_setopt($ch, CURLOPT_USERPWD, OAUTH_CLIENT_ID.':'.OAUTH_CLIENT_SECRET);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}




?>
