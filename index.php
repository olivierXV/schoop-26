<?php
// Convenience for XAMPP (http://localhost/schoop/): send visitors to the real web root. In production, point the web server's
// DocumentRoot at the "public" folder instead so nothing outside it is reachable at all.
header('Location: public/');
exit;
