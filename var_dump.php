<?php
$message = "Line 1\nLine 2\nLine 3";
$message = wordwrap($message, 70);
var_dump( mail('kozackova.maria@yandex.ru', 'My Subject', $message) );
?>