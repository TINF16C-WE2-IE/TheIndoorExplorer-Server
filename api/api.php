<?php
/**
 * Created by PhpStorm.
 * User: Leonard
 * Date: 10.10.2017
 * Time: 18:10
 */

error_reporting(E_ALL);

use Jsv4\Validator;

require_once('../databasecon.php');
require_once('../jsv4-php/vendor/autoload.php');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    die();
}
session_start();

$personId = $_SESSION['PersonId'] ?? "";
if (isset($_GET['maplist'])) getMapList($con, "");
else if (isset($_GET['jsonmap'])) getJsonMap($con);
else if (isset($_GET['insertupdatemap'])) insertOrUpdateMap($con);
else if (isset($_GET['userinfo'])) getUserInfo($con);
else if (isset($_GET['logout'])) logout();
else if (isset($_GET['deletemap'])) deleteMap($con);
else if (isset ($_GET['publishmap'])) publishMap();

function getMapList($con)
{
    $personId = userLoggedIn(false);

    $sql = $con->prepare('SELECT DISTINCT Map.MapId,Name,IsPrivate 
                          FROM Map LEFT JOIN PersonMap 
                          ON Map.MapId = PersonMap.MapId 
                          WHERE IsPrivate = 0 OR PersonMap.PersonId = ?');
    $sql->execute(array($personId));
    $map = array();
    foreach ($sql as $row) {
        $map[] = array(
            'id' => (int)$row['MapId'],
            'name' => $row['Name'],
            'visibility' => (int)$row['IsPrivate'],
            'permission' => 0,
            'favorite' => false
        );
    }
    $jsonFile = json_encode($map);
    echo $jsonFile;
}

function getJsonMap($con)
{
    $mapId = 0;
    $writePermission = 0;
    if (isset($_GET['mapid'])) {
        $mapId = $_GET['mapid'];
    }

    $personId = userLoggedIn(false);

    $sql = $con->prepare('SELECT Map.MapId,Name, JsonMap, IsPrivate
                          FROM Map LEFT JOIN PersonMap 
                          ON Map.MapId = PersonMap.MapId 
                          WHERE Map.MapId = ? AND ( IsPrivate = 0 OR PersonMap.PersonId = ?)');
    $sql->execute(array($mapId, $personId));
    $row = $sql->fetch();
    $sql = $con->prepare('SELECT WritePermission FROM PersonMap WHERE PersonId = ? AND MapId = ?');
    $sql->execute(array($personId, $mapId));
    $row2 = $sql->fetch();
    if($row2)
    {
        $writePermission = $row2['WritePermission'];
    }
    else
    {
        $writePermission = 0;
    }

    if ($row) {
        $map = array(
            'id' => (int)$row['MapId'],
            'name' => $row['Name'],
            'floors' => json_decode($row['JsonMap'], true),
            'visibility' => (int)$row['IsPrivate'],
            'permission' => (int)$writePermission,
            'favorite' => false
        );
        $jsonFile = json_encode($map);
        echo $jsonFile;
    } else  http_response_code(404);
}


function insertOrUpdateMap($con)
{
    $json = json_decode(file_get_contents('php://input'), true);
    $mapId = $json['id'];
    $mapName = $json['name'];

    $jsonMap = json_encode($json['floors']);
    validateMap($jsonMap);

    $personId = userLoggedIn(true);

    //Neue Map
    if ($mapId == -1) {
        $sql = $con->prepare('INSERT INTO Map (Name, JsonMap, isPrivate) VALUES(?,?,?)');
        $sql->execute(array($mapName, $jsonMap, 1));
        $mapId = $con->lastInsertId();

        $sql = $con->prepare('INSERT INTO PersonMap (PersonId,MapId,WritePermission) VALUES(?,?,?) ');
        $sql->execute(array($personId, $mapId, 1));

        echo json_encode(array('id' => (int)$mapId));
    } //Map update
    else {
        $sql = $con->prepare('SELECT WritePermission FROM PersonMap WHERE MapId = ? AND PersonId = ?');
        $sql->execute(array($mapId, $personId));
        $row = $sql->fetch();

        if ($row) {
            if ($row['WritePermission'] == 1) {
                $sql = $con->prepare('UPDATE Map SET Name=?, JsonMap=? WHERE MapId = ?');
                $sql->execute(array($mapName, $jsonMap, $mapId));
                http_response_code(201);

            } else {
                http_response_code(403);
            }
        } else {
            http_response_code(404);
        }
    }
}

function getUserInfo($con)
{
    $personId = userLoggedIn(true);

    $sql = $con->prepare('SELECT Username FROM Person WHERE PersonId = ? ');
    $sql->execute(array($personId));
    $row = $sql->fetch();
    if ($row) {
        $info = array(
            'id' => (int)$personId,
            'username' => $row['Username']
        );

        $jsonFile = json_encode($info);
        echo $jsonFile;
    } else {
        http_response_code(404);
    }
}

function logout()
{
    if (session_destroy()) {
        http_response_code(204);
    } else {
        http_response_code(400);
    }
}

function deleteMap($con)
{
    if (isset($_GET['mapid'])) {
        $mapId = $_GET['mapid'];
    } else {
        http_response_code(404);
        die();
    }
    $personId = userLoggedIn(true);

    $sql = $con->prepare('SELECT WritePermission FROM PersonMap WHERE PersonId = ? AND MapId = ?');
    $sql->execute(array($personId, $mapId));
    $row = $sql->fetch();

    if ($row) {
        if ($row['WritePermission'] == 1) {
            //Delete line from PersonMap table
            $sql = $con->prepare('DELETE FROM PersonMap WHERE PersonId = ? AND MapId = ?');
            $sql->execute(array($personId, $mapId));
            //Delete line from Map table
            $sql = $con->prepare('DELETE FROM Map WHERE MapId = ?');
            $sql->execute(array($mapId));
            http_response_code(204);
        } else {
            http_response_code(403);
        }
    } else {
        http_response_code(404);
    }
}

function userLoggedIn($idNecessary)
{

    if (isset($_SESSION['PersonId'])) {
        return $_SESSION['PersonId'];
    } else if ($idNecessary) {
        http_response_code(401);
        die();
    } else {
        return 0;
    }
}

function validateMap($json)
{
    $mapSchema = json_decode(file_get_contents('schema/map.json'));

    $validation = Validator::validate(json_decode($json), $mapSchema);
    if (!$validation->valid) {
        http_response_code(400);
        die();
    }
}

function publishMap()
{
    include_once("../mailer.php");
    $personId = $_SESSION['PersonId'] ?? 'error';


    if (isset($_GET['mapid'])) {
        $mapId = $_GET['mapid'];
        if (send_mail('indoor-explorer@nubenum.de', "Publish map $mapId", "Please validate and publish the map: $mapId. I am $personId")) {
            http_response_code(204);
        } else {
            http_response_code(418);
        }
    } else {
        http_response_code(404);
        die();
    }
}

$con = null;

