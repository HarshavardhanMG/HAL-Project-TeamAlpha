<?php
session_start();
session_destroy();
// Redirect to the main index page with proper CSS
header("Location: ../index.html");
exit();
?> 