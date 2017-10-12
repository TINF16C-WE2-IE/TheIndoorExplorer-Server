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
$con = null;
