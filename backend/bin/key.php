<?php
declare(strict_types=1);
// Print once, then store securely as APP_KEY in the private backend environment.
echo base64_encode(random_bytes(32)), PHP_EOL;
