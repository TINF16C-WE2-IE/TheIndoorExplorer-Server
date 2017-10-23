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
else if(isset($_GET['jsonmap'])) getJsonMap($con);
else if(isset($_GET['insertupdatemap'])) insertOrUpdateMap($con);
else if(isset($_GET['userinfo'])) getUserInfo($con);



function getMapList($con)
{
    if(isset($_SESSION['PersonId']))
    {
        $personId = $_SESSION['PersonId'];
    }else
    {
        $personId = 0;
    }
//SELECT DISTINCT Map.MapId,Name,IsPrivate FROM Map LEFT JOIN PersonMap ON Map.MapId = PersonMap.MapId WHERE IsPrivate = 0 OR PersonMap.PersonId = 12
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
    $mapId=0;
    $personId = 0;
    if(isset($_GET['mapid']))
    {
        $mapId = $_GET['mapid'];
    }
    if(isset($_SESSION['PersonId']))
    {
        $personId = $_SESSION['PersonId'];
    }

    //$sql=$con->prepare('SELECT MapId,Name,JsonMap,IsPrivate FROM Map WHERE MapId = ?');
    $sql=$con->prepare('SELECT Map.MapId,Name, JsonMap, IsPrivate 
                          FROM Map LEFT JOIN PersonMap 
                          ON Map.MapId = PersonMap.MapId 
                          WHERE Map.MapId = ? AND ( IsPrivate = 0 OR PersonMap.PersonId = ?)');
    $sql->execute(array($mapId, $personId));
    $row= $sql->fetch();
    if($row)
    {
        $map = array(
            'id' => (int)$row['MapId'],
            'name' => $row['Name'],
            'floors' => json_decode($row ['JsonMap'], true),
            'visibility' => (int)$row['IsPrivate'],
            'permission' => 0,
            'favorite' => false
        );
    }else $map = array('error' => 'Error: No map with this id found.');
    $jsonFile = json_encode($map);
    echo $jsonFile;
}

function insertOrUpdateMap($con)
{
    $json = json_decode(file_get_contents('php://input'),true);

    $mapId = $json['id'];
    $mapName = $json['name'];
    $jsonMap = json_encode( $json['map']);
    $isPrivate = (int)$json['visibility'];

    if(!isset($_SESSION['PersonId'])) echo json_encode(array('error'=>'Error: No user logged in'));
    else
    {
        //Neue Map
        if($mapId == -1)
        {
            $sql = $con->prepare('INSERT INTO Map (Name, JsonMap, isPrivate) VALUES(?,?,?)');
            $sql->execute(array($mapName,$jsonMap,$isPrivate));
            $mapId=$con->lastInsertId();

            $sql = $con->prepare('INSERT INTO PersonMap (PersonId,MapId,WritePermission) VALUES(?,?,?) ');
            $sql->execute(array($_SESSION['PersonId'],$mapId,1));
        }
        //Map update
        else
        {
            $sql = $con->prepare('SELECT WritePermission FROM PersonMap WHERE MapId = ? AND PersonId = ?');
            $sql->execute(array($mapId,$_SESSION['PersonId']));
            $row= $sql->fetch();

            if($row)
            {
                if ($row['WritePermission'] == 1)
                {
                    $sql = $con->prepare('UPDATE Map SET Name=?, JsonMap=?, IsPrivate =? WHERE MapId = ?');
                    $sql->execute(array($mapName,$jsonMap,$isPrivate,$mapId));
                }
                else
                {
                    echo json_encode(array('error' =>'Error: No write permission'));
                }
            }else
            {
                echo json_encode(array('error' =>'Error: No Map and Person combination found'));
            }
        }
    }
}
function getUserInfo($con)
{
    if (!isset($_SESSION['PersonId']))
    {
        echo json_encode(array('info'=>'Info: No user logged in'));
    }
    else
    {
        $sql = $con->prepare('SELECT Username FROM Person WHERE PersonId = ? ');
        $sql->execute(array($_SESSION['PersonId']));
        $row = $sql->fetch();
        if($row)
        {
            $info = array(
                'id'=> (int)$_SESSION['PersonId'],
                'username'=> $row['Username']
            );

            $jsonFile= json_encode($info);
            echo $jsonFile;
        }
        else
        {
            echo json_encode(array('error'=>'Error: no person found'));
        }
    }
}
$con = null;

