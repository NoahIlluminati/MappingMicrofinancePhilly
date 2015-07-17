<?php
$loc_name = $_GET['loc_name'];
echo htmlspecialchars($loc_name);
echo htmlspecialchars(implode($_GET['types']));
echo htmlspecialchars(implode($_GET['areas']));
?>
