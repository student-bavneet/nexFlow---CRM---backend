<?php
session_name("nexflow_client_portal");
session_start();
$_SESSION["client_portal_logged_in"] = true;
$_SESSION["client_portal_user_id"] = 1;
$_SESSION["client_portal_org_id"] = 1;
$_SESSION["client_portal_company_id"] = 13;
$_SESSION["client_portal_contact_id"] = 39;

$_SERVER["REQUEST_METHOD"] = "GET";
$_GET["action"] = "get";
$_GET["id"] = "14";
include "c:/xampp/htdocs/nexFlow/api/client-contracts.php";
