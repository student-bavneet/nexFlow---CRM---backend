<?php
session_set_cookie_params(["path" => "/"]);
session_start();
$_SESSION["user_id"] = 1;
$_SESSION["organization_id"] = 1;
$_SESSION["role"] = "super_admin";
echo "Session initialized for user 1";
