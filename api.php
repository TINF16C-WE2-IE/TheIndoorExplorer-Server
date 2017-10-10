<?php
/**
 * Created by PhpStorm.
 * User: Leonard
 * Date: 10.10.2017
 * Time: 18:10
 */
require ('databasecon.php');

session_start();
$username = $_SESSION["Username"]?? "";
if(isset($_GET['maplist'])) getMapList($con,"");

function getMapList($con,$username)
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
$con = null;
