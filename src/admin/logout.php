<?php
session_start();
session_destroy();
// Redirect to the main index page with absolute path
header("Location: ../index.html");
exit();
?> 