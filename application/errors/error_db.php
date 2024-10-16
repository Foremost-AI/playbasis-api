<?php
const dev = defined('ENVIRONMENT') && ENVIRONMENT === 'development';
echo json_encode(array("success" => false, "error_code" => "0800", "message" => (dev ? "error - DB" : "There is an internal server error")));
?>
