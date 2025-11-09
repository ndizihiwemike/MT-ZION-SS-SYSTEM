<?php
try
{
$conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_school LIMIT 1");
$stmt->execute();
$result = $stmt->fetchAll();
foreach($result as $row)
{
DEFINE('WBName', $row[1]);
DEFINE('WBLogo', $row[2]);
DEFINE('WBResSys', $row[3]);
DEFINE('WBResAvi', $row[4]);
}

}catch(PDOException $e)
{
}
?>
