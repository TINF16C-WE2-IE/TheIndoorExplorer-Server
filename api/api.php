<?php
/**
 * Created by PhpStorm.
 * User: Leonard
 * Date: 10.10.2017
 * Time: 18:10
 */
require('../databasecon.php');

session_start();
$personId = $_SESSION['PersonId']?? "";
if(isset($_GET['maplist'])) getMapList($con,"");
else if(isset($_GET['jsonmap'])) getJsonMap($con,$_GET['mapid'],"");
else if(isset($_GET['insertupdatemap'])) insertOrUpdateMap($con);

function getMapList($con,$personId)
{
    $sql = 'SELECT MapId,Name, JsonMap FROM Map WHERE isPrivate = 0';
    $map = array();
    foreach ($con->query($sql) as $row) {
        $map[] = array(
            'id' => $row['MapId'],
            'name' => $row['Name'],
            'permission' => 0,
            'favorite' => false
        );
    }
    $jsonFile = json_encode($map);
    echo $jsonFile;
}
function getJsonMap($con,$mapId,$personId)
{
    $sql=$con->prepare('SELECT MapId,Name,JsonMap FROM Map WHERE MapId = ?');
    $sql->execute(array($mapId));
    $row= $sql->fetch();
    if($row)
    {
        $map = array(
            'id' => $row['MapId'],
            'name' => $row['Name'],
            'map' => json_decode($row ['JsonMap'], true),
            'permission' => 0,
            'favorite' => false
        );
    }else $map = array('error' => 'Error: No map with this id found.');
    $jsonFile = json_encode($map);
    echo $jsonFile;
}

function insertOrUpdateMap($con)
{
    //PRÜFEN OB EINGELOGGT
    $json = json_decode(file_get_contents('php://input'),true);

    $mapId = $json['id'];
    $mapName = json_encode($json['name']);
    $jsonMap = $json['map'];

    if($mapId == '-1')
    {
        $sql = $con->prepare('INSERT INTO Map (Name, JsonMap, isPrivate) VALUES(?,?,?)');
        $sql->execute(array($mapName,$jsonMap,1));
        $mapId=$con->lastInsertId();

        $sql = $con->prepare('INSERT INTO PersonMap (PersonId,MapId,WritePermission) VALUES(?,?,?) ');
        $sql->execute(array($_SESSION['PersonId'],$mapId,1));
    }
}
$con = null;
